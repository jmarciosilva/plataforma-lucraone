<?php

namespace App\Modules\Terminals\Application;

use Carbon\CarbonImmutable;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

final class MachineCredentialExpiration
{
    public function deadline(int $minutes): CarbonImmutable
    {
        $global = (int) config('sanctum.expiration');

        return CarbonImmutable::instance(now())->addMinutes($global > 0 ? min($minutes, $global) : $minutes);
    }

    public function effective(PersonalAccessToken|TransientToken|null $token): ?CarbonImmutable
    {
        if (! $token instanceof PersonalAccessToken) {
            return null;
        }
        $explicit = $token->expires_at ? CarbonImmutable::instance($token->expires_at) : null;
        $global = (int) config('sanctum.expiration');
        $limit = $global > 0 ? CarbonImmutable::instance($token->created_at)->addMinutes($global) : null;

        return $explicit && $limit ? $explicit->min($limit) : ($explicit ?? $limit);
    }

    public function valid(PersonalAccessToken $token): bool
    {
        $deadline = $this->effective($token);

        return $deadline !== null && $deadline->isFuture();
    }
}
