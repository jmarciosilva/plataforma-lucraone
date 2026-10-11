<?php

namespace App\Modules\Pdv\Application\Listeners;

use App\Modules\Pdv\Application\OperatorSessionLifecycle;
use App\Modules\Terminals\Domain\Events\TerminalMachineCredentialsRevoked;

/**
 * Encerra os turnos de um Terminal cujas credenciais de máquina foram apagadas.
 *
 * O par simétrico do KeepOperatorSessionThroughRotation: aquele preserva a
 * sessão quando a credencial é substituída por protocolo; este a encerra quando
 * a credencial é APAGADA — recuperação administrativa, bloqueio, revogação ou
 * emissão direta fora do protocolo.
 *
 * Sem ele, a sessão continuaria marcada ACTIVE no banco. Ela já não
 * autenticaria — a amarração com o PAT apagado nunca mais bate —, mas a linha
 * ativa teria dois efeitos ruins: a tela administrativa mostraria "caixa
 * aberto" para um caixa que não existe mais, e o próximo operador receberia 409
 * de conflito num terminal que está livre.
 *
 * Síncrono, dentro da transação da revogação: ou as credenciais somem e os
 * turnos encerram juntos, ou nada acontece.
 */
final class EndOperatorSessionsOnCredentialRevocation
{
    public function __construct(private OperatorSessionLifecycle $sessoes) {}

    public function handle(TerminalMachineCredentialsRevoked $evento): void
    {
        $this->sessoes->revokeForTerminal($evento->terminal, 'machine-credentials-revoked');
    }
}
