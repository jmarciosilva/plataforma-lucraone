<?php

namespace Tests\Unit;

use App\Modules\Products\Domain\Services\CalculoMargem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CalculoMargemTest extends TestCase
{
    public static function valores(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/margem-decimal.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    #[DataProvider('valores')]
    public function test_margem_tem_mesma_formula_e_precisao_do_frontend(?string $cost, string $sale, ?string $expected): void
    {
        $this->assertSame($expected, CalculoMargem::percentual($cost, $sale));
    }
}
