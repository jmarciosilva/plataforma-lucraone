<?php

namespace App\Modules\Terminals\Domain;

use SensitiveParameter;

class PairingCode
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

    /** Selector (6) + separador (1) + segredo (12). */
    public const LENGTH = 19;

    public const SELECTOR_LENGTH = 6;

    public static function random(int $length): string
    {
        $value = '';
        for ($i = 0; $i < $length; $i++) {
            $value .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $value;
    }

    /**
     * Extrai somente o selector, para uso como chave de rate limiting.
     *
     * O selector é a parte PÚBLICA do código: é ele que identifica a linha no
     * banco, e já viaja em claro. O segredo nunca sai desta função — nem no
     * retorno, nem em exceção —, porque o chamador é a camada HTTP e o destino
     * do valor é uma chave de cache.
     *
     * Tolerante de propósito: aceita um código cujo segredo esteja errado ou
     * ausente, pois é exatamente a tentativa malformada que precisa ser contada.
     * Devolve null quando não há selector plausível, e aí o chamador se limita
     * ao freio por IP — inventar chave a partir da string inteira colocaria o
     * segredo no cache.
     */
    public static function selectorFrom(#[SensitiveParameter] string $code): ?string
    {
        $selector = strtoupper(substr($code, 0, self::SELECTOR_LENGTH));

        if (! preg_match('/\A['.self::ALPHABET.']{'.self::SELECTOR_LENGTH.'}\z/', $selector)) {
            return null;
        }

        return $selector;
    }

    public static function parse(#[SensitiveParameter] string $code): ?array
    {
        if (strlen($code) !== self::LENGTH) {
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
