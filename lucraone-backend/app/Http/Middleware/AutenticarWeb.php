<?php

namespace App\Http\Middleware;

use App\Modules\Tenancy\Application\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige sessão web autenticada e fixa o estabelecimento em uso.
 *
 * Redireciona em vez de devolver 401: quem acessa é um navegador.
 *
 * A resolução do estabelecimento acontece aqui, e não no middleware global,
 * porque só neste ponto a sessão já foi iniciada e o usuário é conhecido.
 * Sem isso o TenantScope fica sem contexto e para de filtrar — todas as
 * consultas passariam a enxergar dados de todos os estabelecimentos.
 */
class AutenticarWeb
{
    public function __construct(
        private TenantResolver $resolver
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('web')->check()) {
            return redirect()
                ->guest(route('login'))
                ->with('erro', 'entre para continuar.');
        }

        $usuario = Auth::guard('web')->user();

        // Conta desativada durante a sessão: encerra o acesso
        if (! $usuario->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('erro', 'esta conta está inativa.');
        }

        if (! $this->resolver->resolve($request)) {
            // Sem estabelecimento resolvido: ou não há vínculo ativo, ou há
            // mais de um e a pessoa ainda não escolheu.
            // O seletor de estabelecimento chega no F3.2; até lá, volta ao login.
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('erro', 'não foi possível determinar o estabelecimento.');
        }

        return $next($request);
    }
}
