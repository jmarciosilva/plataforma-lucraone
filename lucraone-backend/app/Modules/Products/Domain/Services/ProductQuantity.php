<?php

namespace App\Modules\Products\Domain\Services;

use App\Modules\Products\Domain\Exceptions\InvalidProductQuantity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** Quantidades base: order_items, inventories e movimentos usam decimal(14, 3). */
class ProductQuantity
{
    public const SCALE = 3;

    public const MAX = '99999999999.999';

    public static function normalize(string $unit, mixed $quantity): string
    {
        if (! is_string($quantity) && ! is_int($quantity) && ! is_float($quantity)) {
            throw new InvalidProductQuantity('Informe uma quantidade decimal válida.');
        }

        // Compatibilidade com números JSON e chamadas internas existentes.
        // A partir daqui, formato, comparação e escala usam somente decimal exato.
        $text = is_float($quantity) ? json_encode($quantity, JSON_PRESERVE_ZERO_FRACTION) : (string) $quantity;
        if (! is_string($text)) {
            throw new InvalidProductQuantity('Informe uma quantidade decimal válida.');
        }
        if (preg_match('/^-?\d+(?:\.\d+)?[eE][+-]?\d+$/D', $text)) {
            throw new InvalidProductQuantity('Informe a quantidade usando números decimais, sem notação científica.');
        }
        if (! preg_match('/^-?\d+(?:\.\d+)?$/D', $text)) {
            throw new InvalidProductQuantity('Informe uma quantidade decimal válida.');
        }

        $decimal = BigDecimal::of($text);
        if ($decimal->compareTo('0') <= 0) {
            throw new InvalidProductQuantity('A quantidade deve ser maior que zero.');
        }
        self::ensureFits($decimal);

        $fraction = explode('.', $text)[1] ?? '';
        if ($unit === 'UN') {
            if (trim($fraction, '0') !== '') {
                throw new InvalidProductQuantity('Produtos vendidos por unidade devem usar quantidade inteira.');
            }
        } elseif ($unit === 'KG' && strlen($fraction) > self::SCALE) {
            throw new InvalidProductQuantity('Produtos vendidos por peso aceitam até 3 casas decimais.');
        }

        // Unidades legadas não cadastráveis mantêm a escala de persistência anterior.
        return (string) $decimal->toScale(self::SCALE, in_array($unit, ['UN', 'KG'], true) ? RoundingMode::Unnecessary : RoundingMode::HalfUp);
    }

    /** Também protege somas de itens, entradas e reservas antes da persistência. */
    public static function ensureFits(BigDecimal $quantity): void
    {
        if ($quantity->compareTo(self::MAX) > 0) {
            throw new InvalidProductQuantity('A quantidade informada é maior que o limite permitido.');
        }
    }
}
