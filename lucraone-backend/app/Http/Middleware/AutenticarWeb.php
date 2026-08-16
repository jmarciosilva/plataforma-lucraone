<?php

namespace App\Http\Middleware;

use App\Modules\Tenancy\Application\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige sessão web autenticada e, por padrão, um estabelecimento em uso.
 *
 * Redireciona em vez de devolver 401: quem acessa é um navegador.
 *
 * A resolução do estabelecimento acontece aqui, e não num middleware global,
 * porque só neste ponto a sessão já foi iniciada e o usuário é conhecido.
 * Sem isso o TenantScope fica sem contexto e para de filtrar — as consultas
 * passariam a enxergar dados de todos os estabelecimentos.
 *
 * Uso:
 *   'auth.web'              exige estabelecimento resolvido
 *   'auth.web:sem-tenant'   só exige a sessão — para as telas que definem
 *                           o estabelecimento (senão seria um ciclo)
 */
class AutenticarWeb
{
    public function __construct(
        private TenantResolver $resolver
    ) {}

    public function handle(Request $request, Closure $next, ?string $modo = null): Response
    {
        if (! Auth::guard('web')->check()) {
            return redirect()
                ->guest(route('login'))
                ->with('erro', 'entre para continuar.');
        }

        // Conta desativada enquanto a sessão estava aberta
        if (! Auth::guard('web')->user()->isActive()) {
            return $this->encerrarSessao($request, 'esta conta está inativa.');
        }

        if ($modo === 'sem-tenant') {
            return $next($request);
        }

        if (! $this->resolver->resolve($request)) {
            // Ou a pessoa tem vários vínculos e ainda não escolheu, ou o
            // estabelecimento que estava na sessão deixou de valer (acesso
            // revogado enquanto ela navegava).
            return redirect()->route('estabelecimentos.escolher');
        }

        return $next($request);
    }

    private function encerrarSessao(Request $request, string $motivo): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('erro', $motivo);
    }
}
