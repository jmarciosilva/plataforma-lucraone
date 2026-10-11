<?php

namespace App\Modules\Pdv\Application;

use App\Modules\Pdv\Domain\Models\PdvOperatorSession;

/**
 * O que o login (ou a recuperação) devolve ao controller.
 *
 * `credencial` só vem preenchida quando há um segredo NOVO a entregar — no
 * início da sessão e na rotação da recuperação. Nos demais caminhos é null, e é
 * isso que impede o GET de sessão de reentregar a credencial: não existe no
 * objeto o que publicar.
 */
final class OperatorSessionResult
{
    public function __construct(
        public readonly PdvOperatorSession $session,
        public readonly ?string $credencial,
        public readonly bool $recuperada,
    ) {}
}
