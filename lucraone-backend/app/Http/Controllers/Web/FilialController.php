<?php

namespace App\Http\Controllers\Web;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Tenancy\Application\BranchContext;
use App\Modules\Tenancy\Application\BranchResolver;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Escolha e troca da filial ativa no painel web.
 *
 * Análogo ao EstabelecimentoController, mas para a camada de filial.
 * Acessível somente com tenant já resolvido (usa middleware 'auth.web').
 */
class FilialController
{
    public function __construct(
        private BranchResolver $resolver,
        private BranchContext $context,
        private TenantContext $tenantContext,
    ) {}

    public function escolher(Request $request): View|RedirectResponse
    {
        $tenantId = $this->tenantContext->id();

        $filiais = Branch::where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->with('company')
            ->orderBy('name')
            ->get();

        if ($filiais->count() === 1) {
            $this->resolver->definir($request, $filiais->first()->id);

            return redirect()->intended(route('dashboard'));
        }

        return view('auth.escolher-filial', [
            'filiais' => $filiais,
            'atual' => $this->context->id(),
        ]);
    }

    public function definir(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'branch_id' => ['required', 'string'],
        ]);

        if (! $this->resolver->definir($request, $dados['branch_id'])) {
            return back()->with('erro', 'filial inválida ou inativa.');
        }

        return redirect()->intended(route('dashboard'))->with('sucesso', 'filial alterada.');
    }
}
