<?php

namespace App\Http\Controllers\Web;

use App\Modules\Tenancy\Application\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Escolha e troca do estabelecimento em uso.
 *
 * Uma pessoa pode estar associada a vários (um dono com duas lojas, um
 * contador com vários clientes). O escolhido fica na sessão e é lido pelo
 * TenantResolver a cada requisição.
 */
class EstabelecimentoController
{
    public function escolher(Request $request): View|RedirectResponse
    {
        $estabelecimentos = $request->user()->estabelecimentosDisponiveis();

        // Com um só não há escolha a fazer — define e segue.
        if ($estabelecimentos->count() === 1) {
            $request->session()->put(
                TenantResolver::SESSAO_TENANT,
                $estabelecimentos->first()->id
            );

            return redirect()->intended(route('dashboard'));
        }

        return view('auth.escolher-estabelecimento', [
            'estabelecimentos' => $estabelecimentos,
            'atual' => $request->session()->get(TenantResolver::SESSAO_TENANT),
        ]);
    }

    public function definir(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'tenant_id' => ['required', 'string'],
        ]);

        // O vínculo é quem autoriza — nunca o valor que veio do formulário.
        if (! $request->user()->canAccessTenant($dados['tenant_id'])) {
            return back()->with('erro', 'você não tem acesso a este estabelecimento.');
        }

        $request->session()->put(TenantResolver::SESSAO_TENANT, $dados['tenant_id']);

        return redirect()
            ->route('dashboard')
            ->with('sucesso', 'estabelecimento alterado.');
    }
}
