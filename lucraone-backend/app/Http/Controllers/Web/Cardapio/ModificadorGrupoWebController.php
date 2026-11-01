<?php

namespace App\Http\Controllers\Web\Cardapio;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreModifierGroupRequest;
use App\Http\Requests\UpdateModifierGroupRequest;
use App\Modules\Menu\Application\MenuService;
use App\Modules\Menu\Domain\ModifierGroupType;
use App\Modules\Menu\Domain\Models\ModifierGroup;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\BranchContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\Gate;

class ModificadorGrupoWebController extends Controller
{
    public function __construct(private MenuService $menuService) {}

    public function index(Product $produto, BranchContext $branch, TenantContext $tenant)
    {
        Gate::authorize('viewAny', ModifierGroup::class);

        $branchId = $branch->id();
        $tenantId = $tenant->tenant()->id;

        $groups = $this->menuService->resolveModifierGroups($produto->id, $branchId, $tenantId);
        $hasOverride = $branchId ? $this->menuService->hasBranchOverride($produto->id, $branchId, $tenantId) : false;
        $alerts = $this->menuService->choiceGroupsWithoutAvailableModifiers($groups);

        return view('modificadores.index', [
            'product' => $produto,
            'groups' => $groups,
            'hasOverride' => $hasOverride,
            'alerts' => $alerts,
            'branch' => $branch->branch(),
            'typeLabels' => ModifierGroupType::LABELS,
        ]);
    }

    public function create(Product $produto, BranchContext $branch)
    {
        Gate::authorize('create', ModifierGroup::class);

        return view('modificadores.create', [
            'product' => $produto,
            'branch' => $branch->branch(),
            'types' => ModifierGroupType::ALL,
            'typeLabels' => ModifierGroupType::LABELS,
        ]);
    }

    public function store(StoreModifierGroupRequest $request, Product $produto, TenantContext $tenant, BranchContext $branch)
    {
        Gate::authorize('create', ModifierGroup::class);

        $data = $request->validated();
        $data['tenant_id'] = $tenant->tenant()->id;
        $data['product_id'] = $produto->id;

        if (! isset($data['branch_id'])) {
            $data['branch_id'] = $branch->id();
        }

        ModifierGroup::create($data);

        return redirect()
            ->route('produtos.modificadores.index', $produto)
            ->with('success', 'Grupo de modificadores criado.');
    }

    public function edit(Product $produto, ModifierGroup $modificador, BranchContext $branch)
    {
        Gate::authorize('update', $modificador);

        return view('modificadores.edit', [
            'product' => $produto,
            'group' => $modificador,
            'branch' => $branch->branch(),
            'types' => ModifierGroupType::ALL,
            'typeLabels' => ModifierGroupType::LABELS,
        ]);
    }

    public function update(UpdateModifierGroupRequest $request, Product $produto, ModifierGroup $modificador)
    {
        Gate::authorize('update', $modificador);

        $modificador->update($request->validated());

        return redirect()
            ->route('produtos.modificadores.index', $produto)
            ->with('success', 'Grupo atualizado.');
    }

    public function destroy(Product $produto, ModifierGroup $modificador)
    {
        Gate::authorize('delete', $modificador);

        $modificador->delete();

        return redirect()
            ->route('produtos.modificadores.index', $produto)
            ->with('success', 'Grupo removido.');
    }
}
