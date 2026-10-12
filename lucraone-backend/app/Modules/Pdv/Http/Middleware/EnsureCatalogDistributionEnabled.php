<?php

namespace App\Modules\Pdv\Http\Middleware;

use App\Modules\Pdv\Http\Responses\PdvErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCatalogDistributionEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('pdv_catalog.enabled', false)) {
            return PdvErrorResponse::make($request, 'catalog_distribution_unavailable', 'Distribuição de catálogo indisponível neste ambiente.', 503);
        }

        return $next($request);
    }
}
