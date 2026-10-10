<?php

namespace App\Modules\Terminals\Application;

use App\Modules\Terminals\Domain\Exceptions\MachineCredentialRefused;
use App\Modules\Terminals\Domain\MachineTokenAbility;
use App\Modules\Terminals\Domain\Models\Terminal;
use Carbon\CarbonImmutable;

/**
 * Emite a credencial de máquina de um Terminal.
 *
 * Um Terminal operacional é uma instalação física, e a instalação é única e
 * imutável no domínio desde o PDV-BE-03. Logo há no máximo uma credencial
 * vigente: emitir de novo é ROTAÇÃO, não acúmulo. Revogar antes de criar
 * mantém essa invariante sem depender de limpeza posterior e impede que a
 * tabela de PAT cresça sem limite a cada re-emissão.
 *
 * A ordem — revogar, depois criar — é intencional: se a criação falhar, o
 * Terminal fica sem credencial e precisa de nova emissão, que é o estado
 * seguro. A ordem inversa poderia deixar duas credenciais vivas.
 */
class IssueTerminalMachineCredential
{
    public function __construct(
        private TerminalAuthenticationEligibility $eligibility,
        private RevokeTerminalMachineCredentials $revogacao,
    ) {}

    public function issue(Terminal $terminal): IssuedTerminalMachineCredential
    {
        // Mesma política da autenticação: não se emite credencial para um
        // Terminal que não poderia usá-la. PENDING, BLOCKED, REVOKED ou
        // estrutura fora de operação recusam aqui.
        if (! $this->eligibility->allows($terminal)) {
            throw new MachineCredentialRefused('terminal-not-authenticable');
        }

        $this->revogacao->revoke($terminal);

        $expiresAt = CarbonImmutable::createFromInterface(
            now()->addMinutes((int) config('pdv.machine_credentials.ttl_minutes'))
        );

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
    }
}
