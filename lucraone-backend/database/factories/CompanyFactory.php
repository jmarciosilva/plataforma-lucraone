<?php

namespace Database\Factories;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => Tenant::factory(),
            'legal_name' => $this->faker->company().' LTDA',
            'trade_name' => $this->faker->company(),
            'document' => $this->generateCNPJ(),
            'state_registration' => $this->faker->numerify('##########'),
            'municipal_registration' => $this->faker->numerify('###########'),
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'status' => 'ACTIVE',
        ];
    }

    public function forCurrentTenant(?string $tenantId): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ACTIVE',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'INACTIVE',
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'SUSPENDED',
        ]);
    }

    /**
     * Gerar CNPJ válido para testes.
     */
    private function generateCNPJ(): string
    {
        return sprintf(
            '%02d.%03d.%03d/%04d-%02d',
            random_int(10, 99),
            random_int(100, 999),
            random_int(100, 999),
            random_int(1000, 9999),
            random_int(10, 99)
        );
    }
}
