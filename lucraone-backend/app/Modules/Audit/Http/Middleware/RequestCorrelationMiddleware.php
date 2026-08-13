<?php

namespace App\Modules\Audit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestCorrelationMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $requestId = $request->header('X-Request-ID') ?? Str::ulid();

        $request->headers->set('X-Request-ID', $requestId);

        return $next($request)
            ->header('X-Request-ID', $requestId);
    }
}
