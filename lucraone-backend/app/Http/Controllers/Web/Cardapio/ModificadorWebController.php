<?php

namespace App\Http\Controllers\Web\Cardapio;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreModifierRequest;
use App\Http\Requests\UpdateModifierRequest;
use App\Modules\Menu\Domain\Models\Modifier;
use App\Modules\Menu\Domain\Models\ModifierGroup;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\Gate;

class ModificadorWebController extends Controller
{
    public function create(Product $produto, ModifierGroup $modificador)
    {
        Gate::authorize('update', $modificador);

        return view('modificadores.opcoes.create', [
            'product' => $produto,
            'group' => $modificador,
        ]);
    }

    public function store(StoreModifierRequest $request, Product $produto, ModifierGroup $modificador, TenantContext $tenant)
    {
        Gate::authorize('update', $modificador);

        $data = $request->validated();
        $data['tenant_id'] = $tenant->tenant()->id;
        $data['group_id'] = $modificador->id;
        $data['available'] = $request->boolean('available', true);

        Modifier::create($data);

        return redirect()
            ->route('produtos.modificadores.index', $produto)
            ->with('success', 'Opção adicionada.');
    }

    public function edit(Product $produto, ModifierGroup $modificador, Modifier $opcao)
    {
        Gate::authorize('update', $modificador);

        return view('modificadores.opcoes.edit', [
            'product' => $produto,
            'group' => $modificador,
            'modifier' => $opcao,
        ]);
    }

    public function update(UpdateModifierRequest $request, Product $produto, ModifierGroup $modificador, Modifier $opcao)
    {
        Gate::authorize('update', $modificador);

        $opcao->update($request->validated());

        return redirect()
            ->route('produtos.modificadores.index', $produto)
            ->with('success', 'Opção atualizada.');
    }

    public function destroy(Product $produto, ModifierGroup $modificador, Modifier $opcao)
    {
        Gate::authorize('update', $modificador);

        $opcao->delete();

        return redirect()
            ->route('produtos.modificadores.index', $produto)
            ->with('success', 'Opção removida.');
    }

    public function toggleAvailable(Product $produto, ModifierGroup $modificador, Modifier $opcao)
    {
        Gate::authorize('update', $modificador);

        $opcao->update(['available' => ! $opcao->available]);

        return back()->with('success', $opcao->available ? 'Opção disponibilizada.' : 'Opção indisponibilizada.');
    }
}
