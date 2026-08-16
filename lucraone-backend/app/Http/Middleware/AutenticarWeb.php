<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige sessão web autenticada. Visitante vai para a tela de login.
 *
 * Diferente do guard de API: aqui redirecionamos em vez de devolver 401,
 * porque quem acessa é um navegador, não um cliente HTTP.
 */
class AutenticarWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('web')->check()) {
            return redirect()
                ->guest(route('login'))
                ->with('erro', 'entre para continuar.');
        }

        return $next($request);
    }
}
