<?php

namespace App\Modules\Terminals\Application;

use App\Modules\Terminals\Domain\Exceptions\TerminalNotResolvedException;
use App\Modules\Terminals\Domain\Models\Terminal;

/**
 * Mantém o Terminal autenticado durante a requisição.
 *
 * Equivalente de máquina do TenantContext, e separado dele de propósito: o
 * TenantContext responde "em qual estabelecimento esta requisição acontece",
 * enquanto este responde "qual máquina está operando". Juntá-los faria o
 * caminho humano carregar um conceito que não tem.
 *
 * Singleton por requisição, como o TenantContext — registrado no
 * TerminalsServiceProvider e limpo pelo middleware. Não guarda estado entre
 * requisições: cada requisição resolve o seu Terminal a partir do token.
 *
 * Os vínculos são lidos do próprio Terminal, nunca de cabeçalho. É o que torna
 * impossível a um cliente de máquina escolher contexto.
 */
class TerminalContext
{
    private ?Terminal $terminal = null;

    public function set(Terminal $terminal): void
    {
        $this->terminal = $terminal;
    }

    public function resolved(): bool
    {
        return $this->terminal !== null;
    }

    /**
     * @throws TerminalNotResolvedException se nenhuma máquina foi resolvida
     */
    public function terminal(): Terminal
    {
        if ($this->terminal === null) {
            throw new TerminalNotResolvedException('Nenhum Terminal foi resolvido nesta requisição');
        }

        return $this->terminal;
    }

    public function terminalId(): string
    {
        return $this->terminal()->id;
    }

    public function tenantId(): string
    {
        return $this->terminal()->tenant_id;
    }

    public function companyId(): string
    {
        return $this->terminal()->company_id;
    }

    public function branchId(): string
    {
        return $this->terminal()->branch_id;
    }

    public function clear(): void
    {
        $this->terminal = null;
    }
}
