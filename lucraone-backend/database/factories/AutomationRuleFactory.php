<?php

namespace Database\Factories;

use App\Modules\Automation\Application\Actions\CreateNotificationAction;
use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Automation\Domain\Models\Notification;
use App\Modules\Automation\Domain\TriggerCatalog;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class AutomationRuleFactory extends Factory
{
    protected $model = AutomationRule::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'regra '.$this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'trigger' => TriggerCatalog::ESTOQUE_BAIXO,
            'conditions' => [],
            'action' => CreateNotificationAction::CHAVE,
            'action_config' => [
                'title' => 'aviso automático',
                'message' => 'a regra disparou.',
                'level' => Notification::NIVEL_ATENCAO,
            ],
            'active' => true,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes) => ['tenant_id' => $tenantId]);
    }

    public function trigger(string $trigger): static
    {
        return $this->state(fn (array $attributes) => ['trigger' => $trigger]);
    }

    public function conditions(array $conditions): static
    {
        return $this->state(fn (array $attributes) => ['conditions' => $conditions]);
    }

    public function action(string $action, array $config): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => $action,
            'action_config' => $config,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }
}
