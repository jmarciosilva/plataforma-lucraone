<?php

namespace App\Modules\Automation\Application;

use App\Modules\Automation\Application\Actions\ActionRegistry;
use App\Modules\Automation\Domain\ConditionEvaluator;
use App\Modules\Automation\Domain\Models\AutomationLog;
use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Automation\Domain\TriggerCatalog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avalia as regras de um gatilho e executa as que batem.
 *
 * Cada regra é isolada: se uma falha, as outras seguem. Toda passagem por aqui
 * gera uma linha em automation_logs — executada, ignorada ou falhou — porque
 * "por que essa regra não rodou?" é a pergunta que o operador mais faz.
 */
class RuleEngine
{
    public function __construct(
        private ConditionEvaluator $avaliador,
        private ActionRegistry $acoes,
    ) {}

    /**
     * @return array<int, AutomationLog> logs gerados nesta passagem
     */
    public function processar(string $tenantId, string $gatilho, array $payload): array
    {
        if (! TriggerCatalog::existe($gatilho)) {
            return [];
        }

        $regras = AutomationRule::query()
            ->where('tenant_id', $tenantId)
            ->forTrigger($gatilho)
            ->active()
            ->get();

        return $regras
            ->map(fn (AutomationRule $regra) => $this->processarRegra($regra, $gatilho, $payload))
            ->all();
    }

    private function processarRegra(AutomationRule $regra, string $gatilho, array $payload): AutomationLog
    {
        $inicio = microtime(true);

        if (! $this->avaliador->satisfaz($gatilho, $regra->conditions ?? [], $payload)) {
            return $this->registrar($regra, $gatilho, $payload, AutomationLog::IGNORADO, [
                'message' => 'condições da regra não foram satisfeitas.',
                'duration_ms' => $this->decorrido($inicio),
            ]);
        }

        try {
            $resultado = $this->acoes->resolver($regra->action)->executar($regra, $payload);

            $regra->registrarExecucao();

            return $this->registrar($regra, $gatilho, $payload, AutomationLog::EXECUTADO, [
                'message' => 'ação executada.',
                'outcome' => $resultado,
                'duration_ms' => $this->decorrido($inicio),
            ]);
        } catch (Throwable $excecao) {
            // Uma regra quebrada não pode derrubar o gatilho nem as demais
            // regras: registra e segue.
            Log::warning('automação falhou', [
                'rule_id' => $regra->id,
                'tenant_id' => $regra->tenant_id,
                'trigger' => $gatilho,
                'error' => $excecao->getMessage(),
            ]);

            return $this->registrar($regra, $gatilho, $payload, AutomationLog::FALHOU, [
                'message' => mb_substr($excecao->getMessage(), 0, 500),
                'duration_ms' => $this->decorrido($inicio),
            ]);
        }
    }

    private function registrar(
        AutomationRule $regra,
        string $gatilho,
        array $payload,
        string $resultado,
        array $extras,
    ): AutomationLog {
        return AutomationLog::create([
            'tenant_id' => $regra->tenant_id,
            'automation_rule_id' => $regra->id,
            'trigger' => $gatilho,
            'result' => $resultado,
            'action' => $regra->action,
            'message' => $extras['message'] ?? null,
            'payload' => $payload,
            'outcome' => $extras['outcome'] ?? null,
            'duration_ms' => $extras['duration_ms'] ?? null,
            'ran_at' => now(),
        ]);
    }

    private function decorrido(float $inicio): int
    {
        return (int) round((microtime(true) - $inicio) * 1000);
    }
}
