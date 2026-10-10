<?php

namespace App\Modules\Terminals\Http\Middleware;

use App\Modules\Pdv\Http\Responses\PdvErrorResponse;
use App\Modules\Terminals\Application\TerminalCredentialLifecycle;
use App\Modules\Terminals\Domain\MachineTokenAbility;
use App\Modules\Terminals\Domain\Models\Terminal;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class EnsureCredentialAbility
{
    public function handle(Request $request, Closure $next, string $action)
    {
        $subject = $request->user();
        $token = $subject instanceof Terminal ? $subject->currentAccessToken() : null;
        $allowed = $token instanceof PersonalAccessToken && ! in_array('*', $token->abilities ?? [], true)
            && ($action === 'renew' ? app(TerminalCredentialLifecycle::class)->canRenew($token) : $token->can(MachineTokenAbility::CREDENTIAL_CONFIRM));
        if (! $allowed) {
            return PdvErrorResponse::make($request, 'forbidden', 'Esta credencial não autoriza a operação.', 403);
        }

        return $next($request);
    }
}
