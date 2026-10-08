<?php

namespace App\Modules\Terminals\Domain;

use App\Modules\Terminals\Domain\Exceptions\InvalidTerminalAssignment;
use SensitiveParameter;

class InstallationId
{
    public static function normalize(#[SensitiveParameter] string $value): string
    {
        if (! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $value)) {
            throw new InvalidTerminalAssignment('Installation ID must be a UUID v4.');
        }

        return strtolower($value);
    }
}
