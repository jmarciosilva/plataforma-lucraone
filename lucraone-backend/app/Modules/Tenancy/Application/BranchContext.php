<?php

namespace App\Modules\Tenancy\Application;

use App\Modules\Branches\Domain\Models\Branch;

/**
 * Mantém o contexto da filial ativa durante a requisição.
 *
 * Análogo ao TenantContext, mas para a camada de filial. Quando não resolvido
 * o sistema opera em visão consolidada de tenant (comportamento atual do
 * supermercado, preservado pela Decisão B-2).
 */
class BranchContext
{
    private ?string $branchId = null;

    private ?Branch $branch = null;

    public function set(string $branchId): void
    {
        $this->branchId = $branchId;
        $this->branch = null;
    }

    /**
     * ID da filial ativa, ou null quando em visão consolidada.
     */
    public function id(): ?string
    {
        return $this->branchId;
    }

    /**
     * Modelo Branch da filial ativa, ou null quando em visão consolidada.
     */
    public function branch(): ?Branch
    {
        if ($this->branchId === null) {
            return null;
        }

        if ($this->branch === null) {
            $this->branch = Branch::find($this->branchId);
        }

        return $this->branch;
    }

    /**
     * Segmento da filial ativa, ou null quando em visão consolidada.
     */
    public function segment(): ?string
    {
        return $this->branch()?->segment;
    }

    public function resolved(): bool
    {
        return $this->branchId !== null;
    }

    public function clear(): void
    {
        $this->branchId = null;
        $this->branch = null;
    }
}
