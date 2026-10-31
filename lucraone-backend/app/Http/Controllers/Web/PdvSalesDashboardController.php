<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Inventory\Application\ValorDeEstoque;
use App\Modules\Pdv\Application\ResumoVendasPdv;
use App\Modules\Pdv\Application\SimuladorDePromocaoPdv;
use App\Modules\Pdv\Domain\Models\PdvReceivedSale;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Terminals\Domain\Models\Terminal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Painel de vendas do PDV — visão de ERP de mercado.
 *
 * Período selecionável (hoje / 7 dias / 30 dias) a partir de uma data de
 * referência. O recorte usa o fuso da filial (ou do estabelecimento), que é o
 * dia comercial de quem opera o caixa; os horários exibidos usam esse fuso.
 *
 * Reúne: resumo do período (faturamento, ticket, lucratividade), vendas por
 * forma de pagamento, valor do estoque (quantidade e dinheiro) e um simulador
 * de promoção por produto. Totais somam só vendas confirmadas; canceladas
 * entram à parte.
 */
final class PdvSalesDashboardController extends Controller
{
    private const PERIODOS = ['hoje' => 'hoje', '7d' => '7 dias', '30d' => '30 dias'];

    public function __construct(
        private readonly ResumoVendasPdv $resumo,
        private readonly ValorDeEstoque $valorEstoque,
        private readonly SimuladorDePromocaoPdv $simulador,
    ) {}

    public function index(Request $request, TenantContext $context)
    {
        [$tenantId, $branches, $terminals, $branchId, $terminalId, $timezone, $dia, $periodo, $inicioUtc, $fimUtc] = $this->resolver($request, $context);

        $base = PdvReceivedSale::query()
            ->where('tenant_id', $tenantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($terminalId, fn ($q) => $q->where('terminal_id', $terminalId));

        // Resumo do período (faturamento, ticket, lucratividade) via serviço.
        $resumo = $this->resumo->noPeriodo($tenantId, $branchId, $terminalId, $inicioUtc, $fimUtc);

        // Vendas confirmadas do período para o gráfico por hora do dia.
        $confirmadas = (clone $base)
            ->where('event_type', PdvReceivedSale::EVENT_CONFIRMED)
            ->whereBetween('confirmed_at', [$inicioUtc, $fimUtc])
            ->get(['total_amount', 'confirmed_at']);

        // 20 eventos mais recentes do período (confirmados e cancelados).
        $recentes = (clone $base)
            ->where(function ($q) use ($inicioUtc, $fimUtc) {
                $q->whereBetween('confirmed_at', [$inicioUtc, $fimUtc])
                    ->orWhereBetween('cancelled_at', [$inicioUtc, $fimUtc]);
            })
            ->withCount('items')
            ->with(['paymentEntries' => fn ($q) => $q->orderBy('seq')])
            ->orderByDesc('received_at')
            ->limit(20)
            ->get();

        $nomesTerminais = $terminals->pluck('name', 'id');
        $companyIds = $this->empresasDoFiltro($branches, $terminals, $branchId, $terminalId);

        return view('pdv.sales.index', [
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [['label' => 'vendas de pdv']],
            'branches' => $branches,
            'terminals' => $terminals,
            'branchId' => $branchId,
            'terminalId' => $terminalId,
            'timezone' => $timezone,
            'dia' => $dia,
            'periodo' => $periodo,
            'periodos' => self::PERIODOS,
            'cards' => [
                'total_dia' => $resumo['total'],
                'quantidade' => $resumo['quantidade'],
                'ticket_medio' => $resumo['ticket_medio'],
                'total_cancelado' => $resumo['total_cancelado'],
            ],
            'lucratividade' => [
                'lucro' => $resumo['lucro'],
                'margem' => $resumo['margem'],
                'receita_avaliada' => $resumo['receita_avaliada'],
                'itens_sem_custo' => $resumo['itens_sem_custo'],
            ],
            'pagamentos' => $this->resumo->porFormaDePagamento($tenantId, $branchId, $terminalId, $inicioUtc, $fimUtc),
            'estoque' => $this->valorEstoque->resumo($tenantId, $companyIds),
            'produtosSimulaveis' => $this->produtosSimulaveis($tenantId, $companyIds),
            'porHora' => $this->porHora($confirmadas, $timezone),
            'recentes' => $recentes->map(fn (PdvReceivedSale $venda) => $this->linha($venda, $timezone, $nomesTerminais)),
            // Linha de base para a verificação automática: quantos eventos já
            // estão na tela. O ping compara contra isto para avisar de novidades.
            'eventosNoDia' => $this->contarEventos($base, $inicioUtc, $fimUtc),
            'verificadoEm' => CarbonImmutable::now($timezone)->format('H:i:s'),
        ]);
    }

    /**
     * Verificação leve (JSON) para a tela saber, sem recarregar, se chegou
     * venda nova. Devolve só contagens — nada de itens — para responder rápido.
     */
    public function ping(Request $request, TenantContext $context): JsonResponse
    {
        [$tenantId, , , $branchId, $terminalId, $timezone, , , $inicioUtc, $fimUtc] = $this->resolver($request, $context);

        $base = PdvReceivedSale::query()
            ->where('tenant_id', $tenantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($terminalId, fn ($q) => $q->where('terminal_id', $terminalId));

        return response()->json([
            'eventos' => $this->contarEventos($base, $inicioUtc, $fimUtc),
            'verificado_em' => CarbonImmutable::now($timezone)->format('H:i:s'),
        ])->header('Cache-Control', 'no-store, private');
    }

    /**
     * Simulação de promoção de um produto (JSON), com base nas vendas do período.
     */
    public function simular(Request $request, TenantContext $context): JsonResponse
    {
        [$tenantId, , , $branchId, $terminalId, , , , $inicioUtc, $fimUtc] = $this->resolver($request, $context);

        // Validamos a QUERY explicitamente: é um GET, e assim não dependemos de
        // o cliente mandar (ou não) Content-Type application/json, que faria o
        // Laravel ler o corpo em vez da query.
        $dados = validator($request->query(), [
            'product_id' => ['required', 'string'],
            'desconto' => ['required', 'numeric', 'gt:0', 'lt:100'],
        ])->validate();

        $resultado = $this->simulador->simular(
            $tenantId, $branchId, $terminalId,
            $dados['product_id'], (float) $dados['desconto'],
            $inicioUtc, $fimUtc,
        );

        return response()->json($resultado)->header('Cache-Control', 'no-store, private');
    }

    /**
     * Autorização + resolução dos filtros e do período, compartilhada entre a
     * tela, o ping e o simulador.
     *
     * @return array{0:string,1:Collection,2:Collection,3:?string,4:?string,5:string,6:CarbonImmutable,7:string,8:CarbonImmutable,9:CarbonImmutable}
     */
    private function resolver(Request $request, TenantContext $context): array
    {
        $tenantId = $context->id();
        abort_unless($request->user()->canAccessTenant($tenantId), 403);
        Gate::forUser($request->user())->authorize('viewAny', Terminal::class);
        abort_unless($request->user()->hasAnyPermission(['view-branches', 'view-all-branches', 'manage-branches'], $tenantId), 403);

        $branches = Branch::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->orderBy('name')->get(['id', 'name', 'timezone', 'company_id']);
        $terminals = Terminal::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->orderBy('name')->get(['id', 'name', 'company_id']);

        $branchId = $request->filled('branch_id') && $branches->contains('id', $request->string('branch_id')->toString())
            ? $request->string('branch_id')->toString() : null;
        $terminalId = $request->filled('terminal_id') && $terminals->contains('id', $request->string('terminal_id')->toString())
            ? $request->string('terminal_id')->toString() : null;

        $timezone = $branchId !== null
            ? ($branches->firstWhere('id', $branchId)->timezone ?: 'America/Sao_Paulo')
            : ($context->tenant()->timezone ?: 'America/Sao_Paulo');

        // Data de referência (fim do período) no fuso da filial.
        $dia = $this->diaSelecionado($request->query('date'), $timezone);
        $periodo = array_key_exists((string) $request->query('periodo'), self::PERIODOS)
            ? (string) $request->query('periodo') : 'hoje';

        // Janela do período: termina no fim do dia de referência e recua N dias.
        $diasAtras = match ($periodo) {
            '7d' => 6,
            '30d' => 29,
            default => 0,
        };
        $inicioUtc = $dia->subDays($diasAtras)->startOfDay()->utc();
        $fimUtc = $dia->endOfDay()->utc();

        return [$tenantId, $branches, $terminals, $branchId, $terminalId, $timezone, $dia, $periodo, $inicioUtc, $fimUtc];
    }

    /**
     * Empresas (company_id) alcançadas pelo filtro atual, para escopar estoque e
     * simulação. Sem filtro → null (todas as empresas do tenant).
     *
     * @return list<string>|null
     */
    private function empresasDoFiltro(Collection $branches, Collection $terminals, ?string $branchId, ?string $terminalId): ?array
    {
        if ($branchId !== null) {
            $c = $branches->firstWhere('id', $branchId)?->company_id;

            return $c ? [$c] : null;
        }
        if ($terminalId !== null) {
            $c = $terminals->firstWhere('id', $terminalId)?->company_id;

            return $c ? [$c] : null;
        }

        return null;
    }

    /**
     * Produtos do tenant com preço de custo E venda (BRL) — candidatos à
     * simulação de promoção.
     *
     * @param  list<string>|null  $companyIds
     * @return Collection<int, Product>
     */
    private function produtosSimulaveis(string $tenantId, ?array $companyIds): Collection
    {
        return Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->when($companyIds !== null, fn ($q) => $q->whereIn('company_id', $companyIds))
            ->whereHas('prices', fn ($q) => $q->where('type', 'sale')->where('currency', 'BRL'))
            ->whereHas('prices', fn ($q) => $q->where('type', 'cost')->where('currency', 'BRL'))
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'sku']);
    }

    /**
     * Conta os eventos do dia (confirmados e cancelados) dentro da janela.
     *
     * @param  Builder<PdvReceivedSale>  $base
     */
    private function contarEventos(Builder $base, CarbonImmutable $inicioUtc, CarbonImmutable $fimUtc): int
    {
        return (clone $base)
            ->where(function ($q) use ($inicioUtc, $fimUtc) {
                $q->whereBetween('confirmed_at', [$inicioUtc, $fimUtc])
                    ->orWhereBetween('cancelled_at', [$inicioUtc, $fimUtc]);
            })
            ->count();
    }

    private function diaSelecionado(mixed $entrada, string $timezone): CarbonImmutable
    {
        if (is_string($entrada) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $entrada)) {
            try {
                return CarbonImmutable::createFromFormat('Y-m-d', $entrada, $timezone)->startOfDay();
            } catch (\Throwable) {
                // entrada malformada cai no dia de hoje
            }
        }

        return CarbonImmutable::now($timezone)->startOfDay();
    }

    /**
     * @param  Collection<int, PdvReceivedSale>  $confirmadas
     * @return list<array{hora:int, total:float}>
     */
    private function porHora(Collection $confirmadas, string $timezone): array
    {
        $baldes = array_fill(0, 24, 0.0);
        foreach ($confirmadas as $venda) {
            if ($venda->confirmed_at === null) {
                continue;
            }
            $hora = (int) $venda->confirmed_at->copy()->timezone($timezone)->format('G');
            $baldes[$hora] += (float) $venda->total_amount;
        }

        return array_map(fn (int $hora) => ['hora' => $hora, 'total' => $baldes[$hora]], range(0, 23));
    }

    /**
     * @param  Collection<int, string>  $nomesTerminais
     * @return array<string, mixed>
     */
    private function linha(PdvReceivedSale $venda, string $timezone, Collection $nomesTerminais): array
    {
        $momento = ($venda->isCancelled() ? $venda->cancelled_at : $venda->confirmed_at) ?? $venda->received_at;

        return [
            'hora' => $momento?->copy()->timezone($timezone)->format('H:i') ?? '—',
            'terminal' => $nomesTerminais->get($venda->terminal_id, '—'),
            'operador' => $venda->operator_name ?: '—',
            'total' => (float) $venda->total_amount,
            'pagamento' => $this->rotuloPagamento($venda),
            'itens' => (int) $venda->items_count,
            'cancelada' => $venda->isCancelled(),
        ];
    }

    private function rotuloPagamento(PdvReceivedSale $venda): string
    {
        $entries = $venda->paymentEntries;
        if ($entries->count() > 1) {
            $rotulos = $entries->map(fn ($e) => PdvReceivedSale::paymentLabel($e->method))->unique()->values()->all();

            return 'Misto ('.implode(' + ', $rotulos).')';
        }

        return PdvReceivedSale::paymentLabel($venda->payment_method);
    }
}
