<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Tenancy\Domain\Models\Tenant;

class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $action = $this->faker->randomElement(['create', 'read', 'update', 'delete']);
        $resource = $this->faker->randomElement(['role', 'permission', 'user', 'branch', 'company']);

        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => Tenant::factory(),
            'name' => "{$action}-{$resource}",
            'description' => ucfirst($action) . " $resource",
        ];
    }

    public function forTenant($tenantId): static
    {
        return $this->state(function (array $attributes) use ($tenantId) {
            return [
                'tenant_id' => $tenantId,
            ];
        });
    }

    public function forAction($action, $resource): static
    {
        return $this->state(function (array $attributes) use ($action, $resource) {
            return [
                'name' => "{$action}-{$resource}",
                'description' => ucfirst($action) . " {$resource}",
            ];
        });
    }
}
