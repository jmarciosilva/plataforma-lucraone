<?php

namespace Tests\Unit\Products;

use App\Modules\Products\Domain\Services\ProductQuantity;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProductQuantityTest extends TestCase
{
    #[DataProvider('validQuantities')]
    public function test_normalizes_valid_quantities_without_rounding(string $unit, mixed $input, string $expected): void
    {
        $this->assertSame($expected, ProductQuantity::normalize($unit, $input));
    }

    public static function validQuantities(): array
    {
        $cases = [];
        foreach ([1, 2, 10, 100, '2', '2.0', '2.00', '2.000', '99999999999'] as $value) {
            $cases[] = ['UN', $value, explode('.', (string) $value)[0].'.000'];
        }
        foreach (['0.001', '0.010', '0.100', '0.250', '0.500', '1', '1.0', '1.00', '1.000', '1.5', '1.50', '1.500', '2.375', '10.999', '99999999999.999'] as $value) {
            $parts = explode('.', $value);
            $cases[] = ['KG', $value, $parts[0].'.'.str_pad($parts[1] ?? '', 3, '0')];
        }
        $cases[] = ['KG', 0.1, '0.100'];

        return $cases;
    }

    #[DataProvider('invalidQuantities')]
    public function test_rejects_invalid_quantities(string $unit, mixed $input, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        ProductQuantity::normalize($unit, $input);
    }

    public static function invalidQuantities(): array
    {
        $cases = [];
        foreach (['UN', 'KG'] as $unit) {
            foreach (['0', '-1', '-0.001'] as $value) {
                $cases[] = [$unit, $value, 'A quantidade deve ser maior que zero.'];
            }
            foreach (['1e3', '1E3', '1e-2', '2E+5'] as $value) {
                $cases[] = [$unit, $value, 'Informe a quantidade usando números decimais, sem notação científica.'];
            }
            foreach (['NaN', 'INF', 'Infinity', NAN, INF, '1,5', [], true, null] as $value) {
                $cases[] = [$unit, $value, 'Informe uma quantidade decimal válida.'];
            }
            foreach (['100000000000', '999999999999999999999999999999'] as $value) {
                $cases[] = [$unit, $value, 'A quantidade informada é maior que o limite permitido.'];
            }
        }
        foreach (['0.5', '1.2', '1.001', '0.999', '2.0001'] as $value) {
            $cases[] = ['UN', $value, 'Produtos vendidos por unidade devem usar quantidade inteira.'];
        }
        foreach (['0.0001', '1.2345', '2.9999', '1.0000'] as $value) {
            $cases[] = ['KG', $value, 'Produtos vendidos por peso aceitam até 3 casas decimais.'];
        }

        return $cases;
    }
}
