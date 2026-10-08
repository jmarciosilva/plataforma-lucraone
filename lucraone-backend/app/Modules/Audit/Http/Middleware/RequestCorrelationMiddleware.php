<?php

namespace App\Modules\Audit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestCorrelationMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-ID');

        // Identificador opaco, não credencial. Limitar tamanho e caracteres
        // evita conteúdo de controle e valores arbitrariamente grandes no log.
        if (! is_string($requestId) || ! preg_match('/\A[A-Za-z0-9._:-]{1,128}\z/', $requestId)) {
            $requestId = (string) Str::ulid();
        }

        $request->headers->set('X-Request-ID', $requestId);
        Log::withContext(['request_id' => $requestId]);

        try {
            $response = $next($request);
            $response->headers->set('X-Request-ID', $requestId);

            return $response;
        } finally {
            // Não deixar o ID desta requisição no logger da próxima execução.
            Log::withoutContext(['request_id']);
        }
    }
}
