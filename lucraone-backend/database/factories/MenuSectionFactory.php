<?php

namespace Database\Factories;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Menu\Domain\Models\MenuSection;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MenuSectionFactory extends Factory
{
    protected $model = MenuSection::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => Tenant::factory(),
            'branch_id' => Branch::factory(),
            'name' => $this->faker->words(2, true),
            'position' => $this->faker->numberBetween(0, 10),
            'available' => true,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'available' => false,
        ]);
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(function () use ($tenant) {
            $branch = Branch::factory()->forTenant($tenant)->create();

            return [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
            ];
        });
    }
}
