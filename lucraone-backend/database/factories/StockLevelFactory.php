<?php

namespace Database\Factories;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Inventory\Domain\Models\StockLevel;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockLevelFactory extends Factory
{
    protected $model = StockLevel::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'company_id' => Company::factory(),
            'min_qty' => 5,
            'max_qty' => 100,
            'reorder_point' => 10,
        ];
    }
}
