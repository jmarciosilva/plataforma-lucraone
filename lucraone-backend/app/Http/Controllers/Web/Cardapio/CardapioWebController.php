<?php

namespace App\Http\Controllers\Web\Cardapio;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuSectionRequest;
use App\Http\Requests\UpdateMenuSectionRequest;
use App\Modules\Menu\Domain\Models\MenuSection;
use App\Modules\Tenancy\Application\BranchContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CardapioWebController extends Controller
{
    public function index(Request $request, TenantContext $tenant, BranchContext $branch)
    {
        Gate::authorize('viewAny', MenuSection::class);

        $branchId = $branch->id();

        $sections = MenuSection::query()
            ->with(['items.product'])
            ->when($branchId, fn ($q) => $q->forBranch($branchId))
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return view('cardapio.index', [
            'sections' => $sections,
            'branch' => $branch->branch(),
            'tenantNome' => $tenant->tenant()->name,
            'breadcrumbs' => [['label' => 'cardápio', 'rota' => route('cardapio.index')]],
        ]);
    }

    public function create(TenantContext $tenant, BranchContext $branch)
    {
        Gate::authorize('create', MenuSection::class);

        return view('cardapio.create', [
            'branch' => $branch->branch(),
            'tenantNome' => $tenant->tenant()->name,
            'breadcrumbs' => [
                ['label' => 'cardápio', 'rota' => route('cardapio.index')],
                ['label' => 'nova seção'],
            ],
        ]);
    }

    public function store(StoreMenuSectionRequest $request, TenantContext $tenant, BranchContext $branch)
    {
        Gate::authorize('create', MenuSection::class);

        $section = MenuSection::create([
            'tenant_id' => $tenant->tenant()->id,
            'branch_id' => $branch->id(),
            'name' => $request->validated('name'),
            'position' => $request->validated('position', 0),
            'available' => $request->boolean('available', true),
        ]);

        return redirect()
            ->route('cardapio.show', $section)
            ->with('success', 'Seção criada com sucesso.');
    }

    public function show(MenuSection $cardapio, TenantContext $tenant, BranchContext $branch)
    {
        Gate::authorize('view', $cardapio);

        $cardapio->load(['items.product.prices', 'items' => fn ($q) => $q->orderBy('position')]);

        return view('cardapio.show', [
            'section' => $cardapio,
            'branch' => $branch->branch(),
            'tenantNome' => $tenant->tenant()->name,
            'breadcrumbs' => [
                ['label' => 'cardápio', 'rota' => route('cardapio.index')],
                ['label' => $cardapio->name],
            ],
        ]);
    }

    public function edit(MenuSection $cardapio, TenantContext $tenant, BranchContext $branch)
    {
        Gate::authorize('update', $cardapio);

        return view('cardapio.edit', [
            'section' => $cardapio,
            'branch' => $branch->branch(),
            'tenantNome' => $tenant->tenant()->name,
            'breadcrumbs' => [
                ['label' => 'cardápio', 'rota' => route('cardapio.index')],
                ['label' => $cardapio->name, 'rota' => route('cardapio.show', $cardapio)],
                ['label' => 'editar'],
            ],
        ]);
    }

    public function update(UpdateMenuSectionRequest $request, MenuSection $cardapio)
    {
        Gate::authorize('update', $cardapio);

        $cardapio->update($request->validated());

        return redirect()
            ->route('cardapio.show', $cardapio)
            ->with('success', 'Seção atualizada.');
    }

    public function destroy(MenuSection $cardapio)
    {
        Gate::authorize('delete', $cardapio);

        $cardapio->delete();

        return redirect()
            ->route('cardapio.index')
            ->with('success', 'Seção removida.');
    }

    public function toggleAvailable(MenuSection $cardapio)
    {
        Gate::authorize('update', $cardapio);

        $cardapio->update(['available' => ! $cardapio->available]);

        return back()->with('success', $cardapio->available ? 'Seção disponibilizada.' : 'Seção indisponibilizada.');
    }
}
