<?php

namespace App\Modules\Branches\Domain;

/**
 * Valores válidos para o segmento operacional de uma filial.
 *
 * Aprovados na Decisão E (MS-02): SUPERMARKET e RESTAURANT para o MVP.
 * Novos segmentos entram aqui e na validação de StoreBranchRequest.
 */
class BranchSegment
{
    public const SUPERMARKET = 'SUPERMARKET';

    public const RESTAURANT = 'RESTAURANT';

    public const LABELS = [
        self::SUPERMARKET => 'Supermercado / Mercado',
        self::RESTAURANT => 'Restaurante',
    ];

    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(string $segment): string
    {
        return self::LABELS[$segment] ?? $segment;
    }
}
