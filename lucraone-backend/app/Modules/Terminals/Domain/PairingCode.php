<?php

namespace App\Modules\Terminals\Domain;

use SensitiveParameter;

class PairingCode
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function random(int $length): string
    {
        $value = '';
        for ($i = 0; $i < $length; $i++) {
            $value .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $value;
    }

    public static function parse(#[SensitiveParameter] string $code): ?array
    {
        if (strlen($code) !== 19) {
            return null;
        }
        $code = strtoupper($code);
        $alphabet = self::ALPHABET;
        if (! preg_match('/\A(['.$alphabet.']{6})\.(['.$alphabet.']{12})\z/', $code, $parts)) {
            return null;
        }

        return [$parts[1], hash('sha256', $parts[2])];
    }
}
