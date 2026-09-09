<?php

namespace App\Modules\Automation\Infrastructure\Console;

use App\Modules\Automation\Domain\Models\AutomationLog;
use Illuminate\Console\Command;

/**
 * Apara o histórico de execuções.
 *
 * Cada gatilho gera uma linha por regra — inclusive as ignoradas. Sem poda, a
 * tabela cresce sem teto e a tela de histórico fica inútil. Falhas ficam mais
 * tempo que execuções normais: são elas que alguém volta para investigar.
 */
class PruneAutomationLogsCommand extends Command
{
    protected $signature = 'automacoes:limpar-logs
        {--dias=30 : idade máxima das execuções bem-sucedidas e ignoradas}
        {--dias-falhas=90 : idade máxima das falhas}';

    protected $description = 'Remove execuções antigas de automação, preservando falhas por mais tempo';

    public function handle(): int
    {
        $dias = max(1, (int) $this->option('dias'));
        $diasFalhas = max($dias, (int) $this->option('dias-falhas'));

        $removidas = AutomationLog::withoutGlobalScopes()
            ->whereIn('result', [AutomationLog::EXECUTADO, AutomationLog::IGNORADO])
            ->where('ran_at', '<', now()->subDays($dias))
            ->delete();

        $falhasRemovidas = AutomationLog::withoutGlobalScopes()
            ->where('result', AutomationLog::FALHOU)
            ->where('ran_at', '<', now()->subDays($diasFalhas))
            ->delete();

        $this->info("execuções removidas: {$removidas} · falhas removidas: {$falhasRemovidas}");

        return self::SUCCESS;
    }
}
