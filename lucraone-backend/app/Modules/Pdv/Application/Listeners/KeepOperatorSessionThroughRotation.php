<?php

namespace App\Modules\Pdv\Application\Listeners;

use App\Modules\Pdv\Application\OperatorSessionLifecycle;
use App\Modules\Terminals\Domain\Events\TerminalCredentialRotated;

/**
 * Mantém o turno do operador durante uma rotação legítima da credencial de
 * máquina.
 *
 * Síncrono e sem fila, de propósito: ele roda dentro da transação que confirma a
 * rotação, e é esse acoplamento que faz a troca ser atômica. Enfileirar
 * produziria uma janela em que a credencial nova já vale e a sessão ainda aponta
 * para a antiga — exatamente o bug que esta entrega existe para fechar.
 *
 * Só renovação passa. Recuperação administrativa é consequência de perda,
 * suspeita de comprometimento ou substituição física de equipamento, e nenhum
 * desses cenários deve herdar a sessão humana anterior: lá a credencial antiga é
 * revogada, a amarração se rompe e o operador precisa se autenticar de novo. É
 * uma decisão de segurança, não um detalhe de implementação.
 */
final class KeepOperatorSessionThroughRotation
{
    public function __construct(private OperatorSessionLifecycle $sessoes) {}

    public function handle(TerminalCredentialRotated $evento): void
    {
        if (! $evento->isRenewal()) {
            return;
        }

        $this->sessoes->repointAfterRotation(
            $evento->terminal,
            $evento->previousTokenId,
            $evento->promotedTokenId,
            $evento->installationId,
        );
    }
}
