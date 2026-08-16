<?php

namespace Database\Factories;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'name' => $this->faker->city().' Branch',
            'code' => 'BR-'.$this->faker->numerify('####'),
            'document_override' => null,
            'email' => $this->faker->email(),
            'phone' => $this->faker->phoneNumber(),
            'timezone' => 'America/Sao_Paulo',
            'status' => 'ACTIVE',
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
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
}
