<?php

namespace App\Modules\Terminals\Application;

use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\Events\TerminalCredentialRotated;
use App\Modules\Terminals\Domain\Exceptions\CredentialLifecycleFailed;
use App\Modules\Terminals\Domain\MachineTokenAbility;
use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Domain\Models\TerminalCredentialOperation;
use App\Modules\Terminals\Domain\PairingCode;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use SensitiveParameter;

/**
 * Terminal -> parents -> operation/token: all state transitions serialize on Terminal.
 * Delivery uses a client-held secret and HMAC domain separation. The DB only stores
 * hashes; even APP_KEY plus a database dump cannot reconstruct a candidate without
 * the delivery secret. No external calls occur inside these transactions.
 */
final class TerminalCredentialLifecycle
{
    public function __construct(private MachineCredentialExpiration $expiration) {}

    public function prepare(Terminal $subject, PersonalAccessToken $authenticatedToken, string $operationId, #[SensitiveParameter] string $secret): CredentialOperationResult
    {
        return DB::transaction(function () use ($subject, $authenticatedToken, $operationId, $secret) {
            $terminal = $this->lockEligibleTerminal($subject->id);
            $old = $terminal->tokens()->whereKey($authenticatedToken->id)->lockForUpdate()->first();
            if (! $old || ! $this->expiration->valid($old)) {
                throw new AuthenticationException;
            }
            if (! $this->canRenew($old)) {
                throw new CredentialLifecycleFailed('forbidden', 403);
            }
            $this->expirePending($terminal);
            $existing = TerminalCredentialOperation::whereKey($operationId)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->terminal_id !== $terminal->id || $existing->previous_token_id !== $old->id
                    || $existing->kind !== 'RENEWAL' || $existing->status !== 'PENDING'
                    || ! hash_equals($existing->delivery_secret_hash, hash('sha256', $secret))) {
                    throw new CredentialLifecycleFailed('credential_conflict', 409);
                }

                return $this->deliver($terminal, $existing, $secret);
            }
            if (TerminalCredentialOperation::where('terminal_id', $terminal->id)->whereIn('status', ['ISSUED', 'PENDING'])->exists()) {
                throw new CredentialLifecycleFailed('credential_conflict', 409);
            }
            $operation = TerminalCredentialOperation::create($this->identity($terminal) + [
                'id' => $operationId, 'kind' => 'RENEWAL', 'status' => 'PENDING',
                'previous_token_id' => $old->id, 'delivery_secret_hash' => hash('sha256', $secret),
                'expires_at' => $this->expiration->deadline((int) config('pdv.lifecycle.pending_minutes')),
            ]);
            $this->createCandidate($terminal, $operation, $secret);
            $this->audit($terminal, $operation, 'credential.rotation.prepared');

            return $this->deliver($terminal, $operation, $secret);
        }, 3);
    }

    /** Narrow legacy transition: no mutation of existing tokens or wildcard support. */
    public function canRenew(PersonalAccessToken $token): bool
    {
        return ! in_array('*', $token->abilities ?? [], true)
            && ($token->can(MachineTokenAbility::CREDENTIAL_RENEW)
                || ($token->name === config('pdv.machine_credentials.token_name')
                    && $token->abilities === [MachineTokenAbility::TERMINAL_READ]));
    }

    public function confirm(Terminal $subject, PersonalAccessToken $authenticatedToken, string $operationId): CredentialOperationResult
    {
        return DB::transaction(function () use ($subject, $authenticatedToken, $operationId) {
            $terminal = $this->lockEligibleTerminal($subject->id);
            $operation = TerminalCredentialOperation::whereKey($operationId)->where('terminal_id', $terminal->id)->lockForUpdate()->first();
            /** @var PersonalAccessToken|null $candidate */
            $candidate = $terminal->tokens()->whereKey($authenticatedToken->id)->lockForUpdate()->first();
            if (! $candidate || ! $this->expiration->valid($candidate)) {
                throw new AuthenticationException;
            }
            if (! $operation || $operation->candidate_token_id !== $candidate->id || ! $this->matches($terminal, $operation)) {
                throw new CredentialLifecycleFailed('credential_conflict', 409);
            }
            if ($operation->status === 'CONFIRMED') {
                return new CredentialOperationResult($operation, null, $this->expiration->effective($candidate)->toIso8601String());
            }
            if ($operation->status !== 'PENDING' || ! $operation->expires_at->isFuture()) {
                throw new CredentialLifecycleFailed('credential_conflict', 409);
            }
            // Also detects token revocation racing after middleware authentication.
            if ($operation->kind === 'RENEWAL'
                && ! $terminal->tokens()->whereKey($operation->previous_token_id)->exists()) {
                throw new CredentialLifecycleFailed('credential_conflict', 409);
            }
            $candidate->forceFill([
                'name' => config('pdv.machine_credentials.token_name'),
                // Mesmo conjunto da emissão inicial: a candidata promovida vira
                // uma credencial operacional comum, inclusive com a ability de
                // sessão de operador. É assim que um terminal legado adquire a
                // nova ability — pela rotação segura, não por update em massa.
                'abilities' => MachineTokenAbility::paraCredencialDeMaquina(),
                'created_at' => now(),
                'expires_at' => $this->expiration->deadline((int) config('pdv.machine_credentials.ttl_minutes')),
            ])->save();
            // Retain only this Terminal's candidate; never touch a human PAT.
            $terminal->tokens()->whereKeyNot($candidate->id)->delete();
            $operation->update(['status' => 'CONFIRMED', 'confirmed_at' => now()]);
            $this->audit($terminal, $operation, 'credential.rotation.confirmed');
            // Dispatched INSIDE the transaction so interested modules commit
            // with the promotion. Carries facts only; no secret, no authority.
            // Terminals knows nothing about operators — the Pdv module listens.
            TerminalCredentialRotated::dispatch(
                $terminal,
                (string) $terminal->installation_id,
                (int) $operation->previous_token_id,
                (int) $candidate->id,
                (string) $operation->kind,
                (string) $operation->id,
            );

            return new CredentialOperationResult($operation, null, $this->expiration->effective($candidate)->toIso8601String());
        }, 3);
    }

    public function authorizeRecovery(User $actor, Terminal $subject, string $reason): array
    {
        return DB::transaction(function () use ($actor, $subject, $reason) {
            $terminal = $this->lockEligibleTerminal($subject->id);
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if (! Gate::forUser($actor)->allows('update', $terminal)) {
                throw new CredentialLifecycleFailed('forbidden', 403);
            }
            // Administrative recovery is a security boundary: invalidate ALL old
            // machine tokens immediately, including a possibly stolen credential.
            app(RevokeTerminalMachineCredentials::class)->revoke($terminal);
            $selector = PairingCode::random(6);
            $secret = PairingCode::random(12);
            $operation = TerminalCredentialOperation::create($this->identity($terminal) + [
                'id' => (string) Str::uuid(), 'kind' => 'RECOVERY', 'status' => 'ISSUED',
                'selector' => $selector, 'code_hash' => hash('sha256', $secret),
                'authorized_by' => $actor->id, 'reason' => $reason,
                'expires_at' => $this->expiration->deadline((int) config('pdv.lifecycle.recovery_minutes')),
            ]);
            $this->audit($terminal, $operation, 'credential.recovery.authorized', $actor);

            return ['recovery_code' => $selector.'.'.$secret, 'expires_at' => $operation->expires_at->toIso8601String()];
        }, 3);
    }

    public function recover(#[SensitiveParameter] string $code, string $installation, #[SensitiveParameter] string $secret): CredentialOperationResult
    {
        $parts = PairingCode::parse($code);
        if (! $parts) {
            throw new CredentialLifecycleFailed('recovery_failed', 422);
        }
        [$selector, $hash] = $parts;
        // Initial lookup is not authoritative; re-read after acquiring Terminal lock.
        $lookup = TerminalCredentialOperation::where('selector', $selector)->first();
        if (! $lookup) {
            throw new CredentialLifecycleFailed('recovery_failed', 422);
        }
        $result = DB::transaction(function () use ($lookup, $hash, $installation, $secret) {
            try {
                $terminal = $this->lockEligibleTerminal($lookup->terminal_id);
            } catch (AuthenticationException) {
                return new CredentialLifecycleFailed('recovery_failed', 422);
            }
            $operation = TerminalCredentialOperation::whereKey($lookup->id)->lockForUpdate()->firstOrFail();
            if (! in_array($operation->status, ['ISSUED', 'PENDING'], true)
                || ! $operation->expires_at->isFuture()
                || $operation->attempts >= (int) config('pdv.lifecycle.max_attempts')) {
                return new CredentialLifecycleFailed('recovery_failed', 422);
            }
            if (! hash_equals($operation->code_hash, $hash)
                || strtolower($installation) !== $terminal->installation_id
                || ! $this->matches($terminal, $operation)) {
                $operation->attempts++;
                $operation->save();
                if ($operation->attempts >= (int) config('pdv.lifecycle.max_attempts')) {
                    $this->invalidate($terminal, $operation);
                }

                return new CredentialLifecycleFailed('recovery_failed', 422);
            }
            if ($operation->status === 'PENDING') {
                if (! hash_equals($operation->delivery_secret_hash, hash('sha256', $secret))) {
                    return new CredentialLifecycleFailed('recovery_failed', 422);
                }

                return $this->deliver($terminal, $operation, $secret);
            }
            $operation->update(['status' => 'PENDING', 'consumed_at' => now(), 'delivery_secret_hash' => hash('sha256', $secret)]);
            $this->createCandidate($terminal, $operation, $secret);
            $this->audit($terminal, $operation, 'credential.recovery.consumed');

            return $this->deliver($terminal, $operation, $secret);
        }, 3);
        // Failed attempts commit, while storage errors roll back completely.
        if ($result instanceof CredentialLifecycleFailed) {
            throw $result;
        }

        return $result;
    }

    public function lockEligibleTerminal(string $id): Terminal
    {
        $terminal = Terminal::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->first();
        if (! $terminal) {
            throw new AuthenticationException;
        }
        Tenant::whereKey($terminal->tenant_id)->lockForUpdate()->first();
        Company::withoutGlobalScopes()->whereKey($terminal->company_id)->lockForUpdate()->first();
        Branch::withoutGlobalScopes()->whereKey($terminal->branch_id)->lockForUpdate()->first();
        if (! app(TerminalAuthenticationEligibility::class)->allows($terminal)) {
            throw new AuthenticationException;
        }

        return $terminal;
    }

    public function expirePending(Terminal $terminal): void
    {
        $expired = TerminalCredentialOperation::where('terminal_id', $terminal->id)
            ->whereIn('status', ['ISSUED', 'PENDING'])->where('expires_at', '<=', now())->lockForUpdate()->get();
        foreach ($expired as $operation) {
            $this->invalidate($terminal, $operation, 'EXPIRED');
        }
    }

    private function invalidate(Terminal $terminal, TerminalCredentialOperation $operation, string $status = 'INVALIDATED'): void
    {
        if ($operation->candidate_token_id) {
            $terminal->tokens()->whereKey($operation->candidate_token_id)->delete();
        }
        $operation->update(['status' => $status]);
    }

    private function identity(Terminal $terminal): array
    {
        return ['terminal_id' => $terminal->id, 'tenant_id' => $terminal->tenant_id,
            'company_id' => $terminal->company_id, 'branch_id' => $terminal->branch_id,
            'installation_id' => $terminal->installation_id];
    }

    private function matches(Terminal $terminal, TerminalCredentialOperation $operation): bool
    {
        foreach ($this->identity($terminal) as $key => $value) {
            if ($value !== $operation->$key) {
                return false;
            }
        }

        return true;
    }

    private function tokenSecret(TerminalCredentialOperation $operation, #[SensitiveParameter] string $secret): string
    {
        return hash_hmac('sha256', 'pdv-credential-v1|'.$operation->kind.'|'.$operation->terminal_id.'|'.$operation->id.'|'.$secret, app('encrypter')->getKey());
    }

    private function createCandidate(Terminal $terminal, TerminalCredentialOperation $operation, #[SensitiveParameter] string $secret): void
    {
        $token = $terminal->tokens()->create([
            'name' => 'pdv-machine-pending', 'token' => hash('sha256', $this->tokenSecret($operation, $secret)),
            'abilities' => [MachineTokenAbility::CREDENTIAL_CONFIRM], 'expires_at' => $operation->expires_at,
        ]);
        $operation->update(['candidate_token_id' => $token->id]);
    }

    private function deliver(Terminal $terminal, TerminalCredentialOperation $operation, #[SensitiveParameter] string $secret): CredentialOperationResult
    {
        $token = $terminal->tokens()->whereKey($operation->candidate_token_id)->first();
        $derived = $this->tokenSecret($operation, $secret);
        if (! $this->matches($terminal, $operation) || ! $token || ! $this->expiration->valid($token)
            || ! hash_equals($token->token, hash('sha256', $derived))) {
            throw new CredentialLifecycleFailed($operation->kind === 'RECOVERY' ? 'recovery_failed' : 'credential_conflict', $operation->kind === 'RECOVERY' ? 422 : 409);
        }

        return new CredentialOperationResult($operation, $token->id.'|'.$derived, $this->expiration->effective($token)->toIso8601String());
    }

    private function audit(Terminal $terminal, TerminalCredentialOperation $operation, string $action, ?User $actor = null): void
    {
        AuditLog::create([
            'id' => (string) Str::ulid(), 'tenant_id' => $terminal->tenant_id, 'user_id' => $actor?->id,
            'action' => $action, 'entity_type' => 'Terminal', 'entity_id' => $terminal->id,
            'changes' => ['operation_id' => $operation->id, 'kind' => $operation->kind, 'status' => $operation->status],
            'request_id' => request()->header('X-Request-ID'), 'endpoint' => request()->path(),
            'method' => request()->method(), 'ip_address' => request()->ip(),
        ]);
    }
}
