<?php

namespace App\Http\Controllers\Web\Cardapio;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuSectionItemRequest;
use App\Http\Requests\UpdateMenuSectionItemRequest;
use App\Modules\Menu\Domain\Models\MenuSection;
use App\Modules\Menu\Domain\Models\MenuSectionItem;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\BranchContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\Gate;

class CardapioItemWebController extends Controller
{
    public function create(MenuSection $cardapio, BranchContext $branch)
    {
        Gate::authorize('update', $cardapio);

        $products = Product::query()
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        return view('cardapio.itens.create', [
            'section' => $cardapio,
            'products' => $products,
            'branch' => $branch->branch(),
        ]);
    }

    public function store(StoreMenuSectionItemRequest $request, MenuSection $cardapio, TenantContext $tenant)
    {
        Gate::authorize('update', $cardapio);

        $data = $request->validated();
        $data['tenant_id'] = $tenant->tenant()->id;
        $data['menu_section_id'] = $cardapio->id;
        $data['available'] = $request->boolean('available', true);

        MenuSectionItem::create($data);

        return redirect()
            ->route('cardapio.show', $cardapio)
            ->with('success', 'Item adicionado ao cardápio.');
    }

    public function edit(MenuSection $cardapio, MenuSectionItem $item, BranchContext $branch)
    {
        Gate::authorize('update', $cardapio);

        $item->load('product');

        return view('cardapio.itens.edit', [
            'section' => $cardapio,
            'item' => $item,
            'branch' => $branch->branch(),
        ]);
    }

    public function update(UpdateMenuSectionItemRequest $request, MenuSection $cardapio, MenuSectionItem $item)
    {
        Gate::authorize('update', $cardapio);

        $item->update($request->validated());

        return redirect()
            ->route('cardapio.show', $cardapio)
            ->with('success', 'Item atualizado.');
    }

    public function destroy(MenuSection $cardapio, MenuSectionItem $item)
    {
        Gate::authorize('update', $cardapio);

        $item->delete();

        return redirect()
            ->route('cardapio.show', $cardapio)
            ->with('success', 'Item removido do cardápio.');
    }

    public function toggleAvailable(MenuSection $cardapio, MenuSectionItem $item)
    {
        Gate::authorize('update', $cardapio);

        $item->update(['available' => ! $item->available]);

        return back()->with('success', $item->available ? 'Item disponibilizado.' : 'Item indisponibilizado.');
    }
}
