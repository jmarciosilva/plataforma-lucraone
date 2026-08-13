<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Identity\Domain\Models\User;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        $actions = ['create', 'update', 'delete', 'login', 'logout', 'export'];
        $entities = ['User', 'Role', 'Permission', 'Branch', 'Company'];

        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'action' => $this->faker->randomElement($actions),
            'entity_type' => $this->faker->randomElement($entities),
            'entity_id' => (string) Str::ulid(),
            'changes' => [
                'old_values' => ['name' => 'Old Name'],
                'new_values' => ['name' => 'New Name'],
            ],
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'request_id' => (string) Str::ulid(),
            'endpoint' => '/api/' . $this->faker->word(),
            'method' => $this->faker->randomElement(['GET', 'POST', 'PUT', 'DELETE']),
            'status_code' => $this->faker->randomElement([200, 201, 204, 400, 401, 403, 404, 500]),
            'description' => $this->faker->sentence(),
        ];
    }

    public function forTenant($tenantId): static
    {
        return $this->state(function (array $attributes) use ($tenantId) {
            return ['tenant_id' => $tenantId];
        });
    }

    public function forUser($userId): static
    {
        return $this->state(function (array $attributes) use ($userId) {
            return ['user_id' => $userId];
        });
    }

    public function create(): static
    {
        return $this->state(function (array $attributes) {
            return ['action' => 'create'];
        });
    }

    public function update(): static
    {
        return $this->state(function (array $attributes) {
            return ['action' => 'update'];
        });
    }

    public function delete(): static
    {
        return $this->state(function (array $attributes) {
            return ['action' => 'delete'];
        });
    }
}
