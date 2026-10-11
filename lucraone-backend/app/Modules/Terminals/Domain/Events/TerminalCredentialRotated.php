<?php

namespace App\Modules\Terminals\Domain\Events;

use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Uma rotação de credencial de máquina foi CONFIRMADA.
 *
 * Disparado de dentro da transação que promove a candidata, e não depois dela:
 * quem escuta precisa participar do mesmo commit. Se um listener falhar, a
 * promoção da credencial falha junto — é esse acoplamento que torna a
 * continuidade da sessão do operador atômica em vez de "quase sempre".
 *
 * Carrega apenas fatos já decididos: o Terminal, a instalação, o PAT que saiu e
 * o PAT que entrou. Não carrega segredo, não carrega o token em texto e não
 * concede nada — quem escuta decide o que fazer com a informação, e nenhuma
 * decisão de autorização pode nascer daqui.
 *
 * Existe para que o módulo Terminals não precise conhecer o módulo Pdv. A
 * credencial de máquina não sabe o que é um operador, e não deveria passar a
 * saber só porque o operador tem interesse na rotação dela.
 *
 * `kind` distingue RENEWAL de RECOVERY de propósito: recuperação administrativa
 * nasce de suspeita de comprometimento, perda de equipamento ou substituição
 * física, e nenhum desses cenários deve herdar a sessão humana anterior.
 */
final class TerminalCredentialRotated
{
    use Dispatchable;

    public function __construct(
        public readonly Terminal $terminal,
        public readonly string $installationId,
        public readonly int $previousTokenId,
        public readonly int $promotedTokenId,
        public readonly string $kind,
        public readonly string $operationId,
    ) {}

    /**
     * É uma renovação de rotina do mesmo equipamento?
     */
    public function isRenewal(): bool
    {
        return $this->kind === 'RENEWAL';
    }
}
