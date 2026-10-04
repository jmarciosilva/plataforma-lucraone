<?php

namespace App\Modules\Products\Domain\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/** Margem percentual sobre o custo; valores financeiros permanecem decimais. */
class CalculoMargem
{
    public const VARIACAO_MAXIMA_PERCENTUAL = '50';

    public static function valor(mixed $amount): string
    {
        if (! is_string($amount) && ! is_int($amount) && ! is_float($amount)) {
            throw new InvalidArgumentException('Valor monetário inválido.');
        }
        $text = is_float($amount) ? json_encode($amount, JSON_PRESERVE_ZERO_FRACTION) : (string) $amount;
        if (! is_string($text) || ! preg_match('/^\d{1,10}(?:[.,]\d{1,2})?$/D', $text)) {
            throw new InvalidArgumentException('Valor monetário inválido.');
        }

        return (string) BigDecimal::of(str_replace(',', '.', $text))->toScale(2);
    }

    public static function percentual(?string $cost, string $sale): ?string
    {
        if ($cost === null || BigDecimal::of($cost)->compareTo('0') <= 0) {
            return null;
        }

        return (string) BigDecimal::of($sale)->minus($cost)->multipliedBy('100')->dividedBy($cost, 4, RoundingMode::HalfUp);
    }

    public static function exibir(?string $value): string
    {
        return $value === null ? '—' : str_replace('.', ',', (string) BigDecimal::of($value)->toScale(2, RoundingMode::HalfUp));
    }

    public static function ajustar(string $current, string $operation, mixed $value): string
    {
        $text = is_float($value) ? json_encode($value, JSON_PRESERVE_ZERO_FRACTION) : (string) $value;
        $decimal = BigDecimal::of($text);
        if ($operation === 'definir') {
            if ($decimal->compareTo('0') < 0) {
                throw new InvalidArgumentException('preço não pode ser negativo.');
            }

            return self::valor((string) $decimal->toScale(2, RoundingMode::HalfUp));
        }
        if ($decimal->abs()->compareTo(self::VARIACAO_MAXIMA_PERCENTUAL) > 0) {
            throw new InvalidArgumentException('variação de '.$decimal.'% acima do limite de '.self::VARIACAO_MAXIMA_PERCENTUAL.'% por execução.');
        }

        // Multiplicar por (100 + percentual) e dividir uma única vez evita arredondamento intermediário.
        return self::valor((string) BigDecimal::of($current)->multipliedBy($decimal->plus('100'))->dividedBy('100', 2, RoundingMode::HalfUp));
    }
}
