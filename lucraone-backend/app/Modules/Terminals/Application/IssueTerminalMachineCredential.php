<?php

namespace App\Modules\Terminals\Application;

use App\Modules\Terminals\Domain\Exceptions\MachineCredentialRefused;
use App\Modules\Terminals\Domain\MachineTokenAbility;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Support\Facades\DB;

/**
 * Immediate issuance is for initial pairing and internal administrative use.
 * Revoke/create is atomic; HTTP renewal uses TerminalCredentialLifecycle instead.
 * A failed persistence leaves the previous credential intact.
 */
class IssueTerminalMachineCredential
{
    public function __construct(
        private TerminalAuthenticationEligibility $eligibility,
        private RevokeTerminalMachineCredentials $revogacao,
    ) {}

    public function issue(Terminal $terminal): IssuedTerminalMachineCredential
    {
        return DB::transaction(function () use ($terminal) {
            $terminal = Terminal::withoutGlobalScopes()->whereKey($terminal->id)->lockForUpdate()->firstOrFail();
            // Mesma política da autenticação: não se emite credencial para um
            // Terminal que não poderia usá-la. PENDING, BLOCKED, REVOKED ou
            // estrutura fora de operação recusam aqui.
            if (! $this->eligibility->allows($terminal)) {
                throw new MachineCredentialRefused('terminal-not-authenticable');
            }

            $this->revogacao->revoke($terminal);

            $expiresAt = app(MachineCredentialExpiration::class)->deadline((int) config('pdv.machine_credentials.ttl_minutes'));

            // Abilities explícitas e expiração explícita. Sem `['*']`, que é o
            // default do createToken() do Sanctum e faria a credencial valer
            // automaticamente para qualquer ability futura.
            $token = $terminal->createToken(
                (string) config('pdv.machine_credentials.token_name'),
                MachineTokenAbility::paraCredencialDeMaquina(),
                $expiresAt
            );

            return new IssuedTerminalMachineCredential(
                $terminal->id,
                $token->plainTextToken,
                $expiresAt
            );
        }, 3);
    }
}
