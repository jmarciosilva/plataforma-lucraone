<?php

namespace App\Modules\Reporting\Infrastructure\Console;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Reporting\Application\InventoryReportService;
use App\Modules\Reporting\Application\SalesReportService;
use App\Modules\Reporting\Domain\ReportPeriod;
use App\Modules\Reporting\Infrastructure\Mail\SalesSummaryMail;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Envia o resumo de vendas para quem pode ver relatórios.
 *
 * Não há tela de configuração de destinatários: o público é derivado da
 * permissão `view-reports`. Quem pode ver o relatório no painel recebe o
 * resumo — assim não existe uma segunda lista para manter em dia.
 */
class SendSalesSummaryCommand extends Command
{
    protected $signature = 'relatorios:enviar-resumo
        {--dias=7 : tamanho do período coberto pelo resumo}
        {--tenant= : envia apenas para este estabelecimento}';

    protected $description = 'Envia por e-mail o resumo de vendas do período para quem tem permissão de ver relatórios';

    public function __construct(
        private TenantContext $context,
        private SalesReportService $vendas,
        private InventoryReportService $estoque,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dias = max(1, (int) $this->option('dias'));

        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($query) => $query->whereKey($this->option('tenant')))
            ->get();

        if ($tenants->isEmpty()) {
            $this->warn('nenhum estabelecimento encontrado.');

            return self::SUCCESS;
        }

        $enviados = 0;

        foreach ($tenants as $tenant) {
            $enviados += $this->enviarPara($tenant, $dias);
        }

        $this->info("resumo enviado para {$enviados} destinatário(s).");

        return self::SUCCESS;
    }

    private function enviarPara(Tenant $tenant, int $dias): int
    {
        return $this->context->withTenant($tenant->id, function () use ($tenant, $dias) {
            $destinatarios = $this->destinatarios($tenant);

            if ($destinatarios === []) {
                $this->line("· {$tenant->name}: ninguém com permissão de relatórios, pulando.");

                return 0;
            }

            $periodo = ReportPeriod::fromInput(
                now()->subDays($dias - 1)->toDateString(),
                now()->toDateString(),
                ReportPeriod::DIA,
                $tenant->timezone ?: 'UTC',
            );

            $linhasEstoque = $this->estoque->linhas();

            Mail::to($destinatarios)->send(new SalesSummaryMail(
                estabelecimento: $tenant->name,
                periodo: $periodo->rotulo(),
                totais: $this->vendas->totais($periodo),
                topProdutos: $this->vendas->topProdutos($periodo, [], 5),
                estoque: $this->estoque->porSituacao($linhasEstoque),
            ));

            $this->line("· {$tenant->name}: ".count($destinatarios).' destinatário(s).');

            return count($destinatarios);
        });
    }

    /**
     * @return array<int, string>
     */
    private function destinatarios(Tenant $tenant): array
    {
        return $tenant->activeUsers()
            ->get()
            ->filter(fn (User $user) => $user->hasAnyPermission(
                ['view-reports', 'manage-sales'],
                $tenant->id
            ))
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
