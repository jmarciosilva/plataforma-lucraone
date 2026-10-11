<?php

namespace App\Modules\Pdv\Application;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Domain\Models\PdvOperatorSession;
use RuntimeException;

/**
 * Mantém a sessão operacional e o OPERADOR da requisição.
 *
 * O terceiro contexto por requisição, ao lado do TenantContext e do
 * TerminalContext, e separado dos dois pelo mesmo motivo: eles respondem "em
 * qual estabelecimento" e "em qual máquina", e este responde "qual pessoa está
 * no caixa".
 *
 * Existe principalmente para a auditoria. Nas rotas de máquina
 * `$request->user()` é o Terminal, então sem um lugar explícito para a pessoa
 * toda ação humana seria registrada como ação da máquina. Quem precisa do ator
 * humano lê daqui, nunca do guard.
 *
 * Singleton por requisição, preenchido pelo middleware e limpo por ele quando a
 * sessão é recusada — um contexto remanescente faria a requisição seguinte
 * atribuir a ação à pessoa errada.
 */
class OperatorSessionContext
{
    private ?PdvOperatorSession $session = null;

    private ?User $operator = null;

    public function set(PdvOperatorSession $session, ?User $operator): void
    {
        $this->session = $session;
        $this->operator = $operator;
    }

    public function resolved(): bool
    {
        return $this->session !== null;
    }

    public function session(): PdvOperatorSession
    {
        if ($this->session === null) {
            throw new RuntimeException('Nenhuma sessão operacional foi resolvida nesta requisição');
        }

        return $this->session;
    }

    public function operator(): ?User
    {
        return $this->operator;
    }

    public function clear(): void
    {
        $this->session = null;
        $this->operator = null;
    }
}
