<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Modules\Tenancy\Application\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $usuario = $request->autenticar();

        // Troca o id da sessão para que um id capturado antes do login não
        // sirva depois dele (session fixation).
        $request->session()->regenerate();

        $usuario->forceFill(['last_login_at' => now()])->save();

        $estabelecimentos = $usuario->estabelecimentosDisponiveis();

        // Um vínculo só: não há o que escolher.
        if ($estabelecimentos->count() === 1) {
            $request->session()->put(
                TenantResolver::SESSAO_TENANT,
                $estabelecimentos->first()->id
            );

            return redirect()->intended(route('dashboard'));
        }

        // Vários vínculos: a pessoa decide onde vai operar.
        return redirect()->route('estabelecimentos.escolher');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        // Invalidar descarta os dados da sessão (inclusive o estabelecimento
        // em uso); regenerar o token impede que o CSRF antigo continue válido.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('sucesso', 'você saiu do painel.');
    }
}
