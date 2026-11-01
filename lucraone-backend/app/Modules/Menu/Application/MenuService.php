<?php

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Domain\Models\ModifierGroup;
use Illuminate\Support\Collection;

class MenuService
{
    /**
     * Resolve grupos de modificadores para um produto em uma filial (R2-A).
     *
     * Precedência completa: override da filial > template global.
     * Os dois conjuntos nunca se mesclam.
     *
     * @return Collection<ModifierGroup>
     */
    public function resolveModifierGroups(
        string $productId,
        string $branchId,
        string $tenantId
    ): Collection {
        $branchGroups = ModifierGroup::where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('tenant_id', $tenantId)
            ->with('modifiers')
            ->orderBy('position')
            ->get();

        if ($branchGroups->isNotEmpty()) {
            return $branchGroups;
        }

        return ModifierGroup::where('product_id', $productId)
            ->whereNull('branch_id')
            ->where('tenant_id', $tenantId)
            ->with('modifiers')
            ->orderBy('position')
            ->get();
    }

    /**
     * Verifica se há override de modificadores para um produto em uma filial.
     */
    public function hasBranchOverride(string $productId, string $branchId, string $tenantId): bool
    {
        return ModifierGroup::where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    /**
     * Alerta para grupos CHOICE obrigatórios sem nenhum modifier disponível.
     *
     * @param Collection<ModifierGroup> $groups
     * @return Collection<ModifierGroup>
     */
    public function choiceGroupsWithoutAvailableModifiers(Collection $groups): Collection
    {
        return $groups->filter(function (ModifierGroup $group) {
            return $group->required
                && $group->type === 'CHOICE'
                && ! $group->hasAvailableModifiers();
        });
    }
}
