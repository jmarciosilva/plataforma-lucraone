<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Reporting\Application\CustomerReportService;
use App\Modules\Reporting\Application\DashboardSummaryService;
use App\Modules\Reporting\Application\InventoryReportService;
use App\Modules\Reporting\Application\SalesReportService;
use App\Modules\Reporting\Application\TrendAnalysisService;
use App\Modules\Reporting\Domain\ReportPeriod;
use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportWebController extends Controller
{
    private const STATUS_ROTULOS = OrderWebController::STATUS_ROTULOS;

    private const GRANULARIDADES = [
        ReportPeriod::DIA => 'por dia',
        ReportPeriod::SEMANA => 'por semana',
        ReportPeriod::MES => 'por mês',
        ReportPeriod::ANO => 'por ano',
    ];

    public function __construct(
        private SalesReportService $vendas,
        private InventoryReportService $estoque,
        private CustomerReportService $clientes,
        private DashboardSummaryService $resumo,
        private TrendAnalysisService $tendencias,
    ) {}

    /**
     * Visão geral: KPIs comerciais, faturamento no tempo e os dois rankings.
     */
    public function index(Request $request, TenantContext $context)
    {
        Gate::authorize('view-reports');

        $periodo = $this->periodo($request, $context);
        $filtros = $this->filtros($request);

        $analise = $this->tendencias->analisar($periodo, $filtros);

        return view('reports.index', [
            ...$this->comuns($request, $context, $periodo),
            'resumo' => $this->resumo->resumo($periodo, $filtros),
            'serie' => $analise['serie'],
            'analise' => $analise,
            'topProdutos' => $this->vendas->topProdutos($periodo, $filtros, 5),
            'topClientes' => $this->clientes->top($periodo, $filtros, 5),
            'breadcrumbs' => $this->breadcrumbs(),
        ]);
    }

    public function sales(Request $request, TenantContext $context)
    {
        Gate::authorize('view-reports');

        $periodo = $this->periodo($request, $context);
        $filtros = $this->filtros($request);

        return view('reports.sales', [
            ...$this->comuns($request, $context, $periodo),
            'totais' => $this->vendas->totais($periodo, $filtros),
            'serie' => $this->vendas->serie($periodo, $filtros),
            'porSituacao' => $this->vendas->porSituacao($periodo, $filtros),
            'topProdutos' => $this->vendas->topProdutos($periodo, $filtros, 10),
            'statusRotulos' => self::STATUS_ROTULOS,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => 'vendas']],
        ]);
    }

    public function inventory(Request $request, TenantContext $context)
    {
        Gate::authorize('view-reports');

        $periodo = $this->periodo($request, $context);
        $filtros = $this->filtros($request);
        $linhas = $this->estoque->linhas($filtros + array_filter(['situacao' => $request->input('situacao')]));

        return view('reports.inventory', [
            ...$this->comuns($request, $context, $periodo),
            'linhas' => $linhas,
            'resumoEstoque' => $this->estoque->resumo($linhas),
            'porSituacao' => $this->estoque->porSituacao($linhas),
            'situacaoOptions' => $this->situacaoOptions(),
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => 'estoque']],
        ]);
    }

    public function customers(Request $request, TenantContext $context)
    {
        Gate::authorize('view-reports');

        $periodo = $this->periodo($request, $context);
        $filtros = $this->filtros($request);

        return view('reports.customers', [
            ...$this->comuns($request, $context, $periodo),
            'totaisClientes' => $this->clientes->totais($periodo, $filtros),
            'segmentacao' => $this->clientes->segmentacao($periodo, $filtros),
            'topClientes' => $this->clientes->top($periodo, $filtros, 20),
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => 'clientes']],
        ]);
    }

    /**
     * Exportação em CSV do relatório aberto.
     *
     * Streaming: um estoque grande não precisa caber na memória de uma vez.
     */
    public function export(Request $request, TenantContext $context, string $tipo): StreamedResponse
    {
        Gate::authorize('view-reports');

        $periodo = $this->periodo($request, $context);
        $filtros = $this->filtros($request);

        [$cabecalho, $linhas] = match ($tipo) {
            'sales' => $this->csvVendas($periodo, $filtros),
            'inventory' => $this->csvEstoque($filtros + array_filter(['situacao' => $request->input('situacao')])),
            'customers' => $this->csvClientes($periodo, $filtros),
            default => abort(404),
        };

        $nome = "relatorio-{$tipo}-{$periodo->inicio->toDateString()}-a-{$periodo->fim->toDateString()}.csv";

        return response()->streamDownload(function () use ($cabecalho, $linhas) {
            $saida = fopen('php://output', 'w');

            // BOM para o Excel abrir acentuação corretamente
            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, $cabecalho, ';');

            foreach ($linhas as $linha) {
                fputcsv($saida, $linha, ';');
            }

            fclose($saida);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csvVendas(ReportPeriod $periodo, array $filtros): array
    {
        $linhas = collect($this->vendas->serie($periodo, $filtros))
            ->map(fn (array $balde) => [
                $balde['chave'],
                $balde['rotulo'],
                $balde['pedidos'],
                number_format($balde['faturamento'], 2, ',', ''),
            ]);

        return [['periodo', 'rotulo', 'pedidos', 'faturamento'], $linhas];
    }

    private function csvEstoque(array $filtros): array
    {
        $linhas = collect($this->estoque->linhas($filtros))
            ->map(fn (array $linha) => [
                $linha['sku'],
                $linha['produto'],
                $linha['empresa'],
                number_format($linha['quantidade'], 3, ',', ''),
                number_format($linha['reservado'], 3, ',', ''),
                number_format($linha['valor_custo'], 2, ',', ''),
                number_format($linha['valor_venda'], 2, ',', ''),
                $linha['situacao'],
            ]);

        return [
            ['sku', 'produto', 'empresa', 'quantidade', 'reservado', 'valor_custo', 'valor_venda', 'situacao'],
            $linhas,
        ];
    }

    private function csvClientes(ReportPeriod $periodo, array $filtros): array
    {
        $linhas = collect($this->clientes->top($periodo, $filtros, 1000))
            ->map(fn (array $linha) => [
                $linha['nome'],
                $linha['email'],
                $linha['pedidos'],
                number_format($linha['total_gasto'], 2, ',', ''),
                number_format($linha['ticket_medio'], 2, ',', ''),
            ]);

        return [['cliente', 'email', 'pedidos', 'total_gasto', 'ticket_medio'], $linhas];
    }

    /**
     * Dados que toda tela de relatório precisa: filtros, empresas e período.
     */
    private function comuns(Request $request, TenantContext $context, ReportPeriod $periodo): array
    {
        return [
            'tenantNome' => $context->tenant()->name,
            'periodo' => $periodo,
            'companies' => $this->companies(),
            'granularidades' => self::GRANULARIDADES,
            'presets' => $this->presets(),
            'querystring' => $request->query(),
        ];
    }

    private function periodo(Request $request, TenantContext $context): ReportPeriod
    {
        return ReportPeriod::fromInput(
            $request->input('inicio'),
            $request->input('fim'),
            $request->input('granularidade'),
            $context->tenant()->timezone ?: 'UTC',
        );
    }

    private function filtros(Request $request): array
    {
        return array_filter([
            'company_id' => $request->input('company_id'),
        ]);
    }

    private function breadcrumbs(): array
    {
        return [
            ['label' => 'dashboard', 'url' => route('dashboard')],
            ['label' => 'relatórios', 'url' => route('reports.index')],
        ];
    }

    private function companies()
    {
        return Company::query()
            ->where('status', 'ACTIVE')
            ->orderBy('trade_name')
            ->orderBy('legal_name')
            ->get();
    }

    /**
     * Atalhos de intervalo — ninguém quer brigar com calendário para pedir
     * "últimos 30 dias".
     */
    private function presets(): array
    {
        $hoje = now();

        return [
            'hoje' => ['rotulo' => 'hoje', 'inicio' => $hoje->toDateString(), 'fim' => $hoje->toDateString()],
            '7' => ['rotulo' => '7 dias', 'inicio' => $hoje->copy()->subDays(6)->toDateString(), 'fim' => $hoje->toDateString()],
            '30' => ['rotulo' => '30 dias', 'inicio' => $hoje->copy()->subDays(29)->toDateString(), 'fim' => $hoje->toDateString()],
            '90' => ['rotulo' => '90 dias', 'inicio' => $hoje->copy()->subDays(89)->toDateString(), 'fim' => $hoje->toDateString()],
        ];
    }

    private function situacaoOptions(): array
    {
        return [
            '' => 'todas',
            InventoryReportService::SITUACAO_OK => 'ok',
            InventoryReportService::SITUACAO_BAIXO => 'baixo estoque',
            InventoryReportService::SITUACAO_EXCESSO => 'excesso',
        ];
    }

    /**
     * Situações de pedido que contam como faturamento — usado pela legenda.
     */
    public static function statusDeFaturamento(): array
    {
        return [Order::STATUS_SHIPPED, Order::STATUS_COMPLETED];
    }
}
