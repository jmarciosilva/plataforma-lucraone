<?php

namespace App\Modules\Pdv\Http\Middleware;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\TokenAbility;
use App\Modules\Pdv\Http\Responses\PdvErrorResponse;
use App\Modules\Tenancy\Application\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class RecoveryAdministrationContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;
        if (! $user instanceof User || ! $token instanceof PersonalAccessToken
            || ! $token->can(TokenAbility::ESCRITA) || ! app(TenantResolver::class)->resolve($request)) {
            return PdvErrorResponse::make($request, 'forbidden', 'Operação administrativa não autorizada.', 403);
        }

        return $next($request);
    }
}
