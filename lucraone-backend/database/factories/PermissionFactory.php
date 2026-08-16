<?php

namespace Database\Factories;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        // O nome é único por tenant. Sorteando ação e recurso de forma
        // independente, duas permissões do mesmo tenant colidiam em 1 de cada
        // 20 execuções — sortear a combinação com unique() elimina a variação.
        $combinacoes = [];

        foreach (['create', 'read', 'update', 'delete'] as $acao) {
            foreach (['role', 'permission', 'user', 'branch', 'company'] as $recurso) {
                $combinacoes[] = [$acao, $recurso];
            }
        }

        [$action, $resource] = $this->faker->unique()->randomElement($combinacoes);

        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => Tenant::factory(),
            'name' => "{$action}-{$resource}",
            'description' => ucfirst($action)." $resource",
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
                'description' => ucfirst($action)." {$resource}",
            ];
        });
    }
}
