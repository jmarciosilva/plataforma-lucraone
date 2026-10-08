<?php

namespace App\Modules\Terminals\Application;

use App\Modules\Terminals\Domain\Exceptions\InvalidTerminalAssignment;
use App\Modules\Terminals\Domain\Exceptions\PairingFailed;
use App\Modules\Terminals\Domain\InstallationId;
use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Domain\Models\TerminalPairingCode;
use App\Modules\Terminals\Domain\PairingCode;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

class ConsumeTerminalPairingCode
{
    public function consume(#[SensitiveParameter] string $code, #[SensitiveParameter] string $installationId): TerminalProvisioningResult
    {
        $parts = PairingCode::parse($code);
        if ($parts === null) {
            throw new PairingFailed('invalid-code');
        }
        [$selector, $hash] = $parts;
        try {
            $outcome = DB::transaction(function () use ($selector, $hash, $installationId) {
                $candidate = TerminalPairingCode::where('selector', $selector)->first();
                if ($candidate === null) {
                    return new PairingFailed('invalid-code');
                }
                // Mesma ordem da emissão: Terminal antes do pairing. O lookup
                // inicial não bloqueia e o pairing é relido após adquirir locks.
                $terminal = Terminal::withoutGlobalScopes()->whereKey($candidate->terminal_id)->lockForUpdate()->first();
                $pairing = TerminalPairingCode::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                if ($pairing->consumed_at !== null) {
                    return new PairingFailed('consumed');
                }
                if ($pairing->attempts >= (int) config('pdv.pairing.max_attempts')) {
                    return new PairingFailed('attempts-exceeded');
                }
                if ($pairing->invalidated_at !== null) {
                    return new PairingFailed('invalidated');
                }
                if ($pairing->expires_at->lessThanOrEqualTo(now())) {
                    return new PairingFailed('expired');
                }
                $pairing->attempts++;
                if (! hash_equals($pairing->code_hash, $hash)) {
                    return $this->reject($pairing, 'invalid-code');
                }
                if ($terminal === null) {
                    return $this->reject($pairing, 'terminal-not-pairable');
                }
                try {
                    app(PairingEligibility::class)->ensure($terminal);
                } catch (PairingFailed $failure) {
                    return $this->reject($pairing, $failure->reason);
                }
                try {
                    $uuid = InstallationId::normalize($installationId);
                } catch (InvalidTerminalAssignment) {
                    return $this->reject($pairing, 'invalid-installation');
                }
                if (Terminal::withoutGlobalScopes()->where('installation_id', $uuid)->exists()) {
                    return $this->reject($pairing, 'installation-already-bound');
                }
                $terminal->update(['installation_id' => $uuid, 'status' => Terminal::STATUS_ACTIVE]);
                $pairing->consumed_at = now();
                $pairing->save();

                return new TerminalProvisioningResult(
                    $terminal->id, $terminal->tenant_id, $terminal->company_id,
                    $terminal->branch_id, $terminal->installation_id, $terminal->status
                );
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains($exception->getMessage(), 'terminals_installation_id_unique')
                || str_contains($exception->getMessage(), 'terminals.installation_id')) {
                throw new PairingFailed('installation-already-bound');
            }
            throw new PairingFailed('storage-failure');
        } catch (QueryException) {
            throw new PairingFailed('storage-failure');
        }
        // Falhas de domínio saem após commit, preservando attempts persistentes.
        if ($outcome instanceof PairingFailed) {
            throw $outcome;
        }

        return $outcome;
    }

    private function reject(TerminalPairingCode $pairing, string $reason): PairingFailed
    {
        if ($pairing->attempts >= (int) config('pdv.pairing.max_attempts')) {
            $pairing->invalidated_at = now();
        }
        $pairing->save();

        return new PairingFailed($reason);
    }
}
