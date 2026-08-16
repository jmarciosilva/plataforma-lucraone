<?php

namespace Database\Factories;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'company_id' => Company::factory(),
            'customer_id' => null,
            'order_number' => 'PED-'.now()->format('Ym').'-'.strtoupper(Str::random(6)),
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'currency' => 'BRL',
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
        ]);
    }

    public function status(string $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}
