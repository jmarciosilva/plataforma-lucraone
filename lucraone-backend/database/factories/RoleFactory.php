<?php

namespace Database\Factories;

use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->unique()->word(),
            'description' => $this->faker->sentence(),
        ];
    }

    public function admin(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'admin',
                'description' => 'Administrator role with full access',
            ];
        });
    }

    public function manager(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'manager',
                'description' => 'Manager role with limited administrative access',
            ];
        });
    }

    public function user(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'user',
                'description' => 'Regular user role',
            ];
        });
    }

    public function viewer(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'viewer',
                'description' => 'Viewer role with read-only access',
            ];
        });
    }

    public function forTenant($tenantId): static
    {
        return $this->state(function (array $attributes) use ($tenantId) {
            return [
                'tenant_id' => $tenantId,
            ];
        });
    }
}
