<?php

namespace Database\Factories;

use App\Modules\Automation\Domain\Models\AutomationLog;
use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Automation\Domain\TriggerCatalog;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class AutomationLogFactory extends Factory
{
    protected $model = AutomationLog::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'automation_rule_id' => AutomationRule::factory(),
            'trigger' => TriggerCatalog::ESTOQUE_BAIXO,
            'result' => AutomationLog::EXECUTADO,
            'action' => 'create_notification',
            'message' => 'ação executada.',
            'payload' => ['produto' => 'produto teste'],
            'outcome' => [],
            'duration_ms' => 12,
            'ran_at' => now(),
        ];
    }

    public function forRule(AutomationRule $rule): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $rule->tenant_id,
            'automation_rule_id' => $rule->id,
            'trigger' => $rule->trigger,
            'action' => $rule->action,
        ]);
    }

    public function failed(string $mensagem = 'falhou'): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => AutomationLog::FALHOU,
            'message' => $mensagem,
        ]);
    }
}
