<?php

namespace App\Modules\Reporting\Application;

use App\Modules\Reporting\Domain\ReportPeriod;
use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Sales\Domain\Models\OrderItem;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de vendas.
 *
 * O que conta como faturamento é `Order::scopeRevenue()` — enviados e
 * concluídos. Pedidos em rascunho, aguardando ou confirmados aparecem como
 * carteira em aberto, nunca como receita.
 *
 * O recorte é pela data de criação do pedido: é a data em que a venda foi
 * feita, e é a única que todo pedido tem, o que permite comparar receita e
 * carteira no mesmo eixo.
 */
class SalesReportService
{
    public function __construct(
        private DateBucket $baldes
    ) {}

    /**
     * Série temporal de faturamento e pedidos, com os baldes vazios preenchidos.
     *
     * Um dia sem venda precisa aparecer como zero, não sumir: o gráfico de uma
     * semana parada tem que mostrar a semana parada.
     */
    public function serie(ReportPeriod $periodo, array $filtros = []): array
    {
        $expressao = $this->baldes->expressao('orders.created_at', $periodo);

        $linhas = $this->base($periodo, $filtros)
            ->revenue()
            ->selectRaw("{$expressao} as balde")
            ->selectRaw('COUNT(*) as pedidos')
            ->selectRaw('SUM(orders.total) as faturamento')
            ->groupBy('balde')
            ->get()
            ->keyBy('balde');

        return collect($this->baldes->baldes($periodo))
            ->map(fn (string $rotulo, string $chave) => [
                'chave' => $chave,
                'rotulo' => $rotulo,
                'pedidos' => (int) ($linhas[$chave]->pedidos ?? 0),
                'faturamento' => (float) ($linhas[$chave]->faturamento ?? 0),
            ])
            ->values()
            ->all();
    }

    public function totais(ReportPeriod $periodo, array $filtros = []): array
    {
        $faturamento = (float) $this->base($periodo, $filtros)->revenue()->sum('total');
        $pedidosFaturados = $this->base($periodo, $filtros)->revenue()->count();
        $pedidosTotais = $this->base($periodo, $filtros)->count();
        $cancelados = $this->base($periodo, $filtros)->where('status', Order::STATUS_CANCELLED)->count();
        $emAberto = (float) $this->base($periodo, $filtros)->backlog()->sum('total');

        return [
            'faturamento' => $faturamento,
            'pedidos_faturados' => $pedidosFaturados,
            'pedidos_totais' => $pedidosTotais,
            'pedidos_cancelados' => $cancelados,
            'carteira_em_aberto' => $emAberto,
            'ticket_medio' => $pedidosFaturados > 0 ? $faturamento / $pedidosFaturados : 0.0,
            'itens_vendidos' => (float) $this->itensBase($periodo, $filtros)->sum('order_items.quantity'),
        ];
    }

    /**
     * Distribuição dos pedidos por situação — mostra onde a carteira empaca.
     */
    public function porSituacao(ReportPeriod $periodo, array $filtros = []): array
    {
        $contagem = $this->base($periodo, $filtros)
            ->select('status', DB::raw('COUNT(*) as total'), DB::raw('SUM(total) as valor'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return collect(array_keys(Order::TRANSICOES))
            ->map(fn (string $status) => [
                'status' => $status,
                'pedidos' => (int) ($contagem[$status]->total ?? 0),
                'valor' => (float) ($contagem[$status]->valor ?? 0),
            ])
            ->all();
    }

    /**
     * Produtos mais vendidos no período, por quantidade e receita.
     */
    public function topProdutos(ReportPeriod $periodo, array $filtros = [], int $limite = 10): array
    {
        return $this->itensBase($periodo, $filtros)
            ->select(
                'order_items.product_id',
                DB::raw('MAX(order_items.name) as nome'),
                DB::raw('MAX(order_items.sku) as sku'),
                DB::raw('SUM(order_items.quantity) as quantidade'),
                DB::raw('SUM(order_items.total) as receita'),
            )
            ->groupBy('order_items.product_id')
            ->orderByDesc('receita')
            ->limit($limite)
            ->get()
            ->map(fn ($linha) => [
                'product_id' => $linha->product_id,
                'nome' => $linha->nome,
                'sku' => $linha->sku,
                'quantidade' => (float) $linha->quantidade,
                'receita' => (float) $linha->receita,
            ])
            ->all();
    }

    /**
     * Query de pedidos do período, já com os filtros da tela aplicados.
     */
    private function base(ReportPeriod $periodo, array $filtros)
    {
        return Order::query()
            ->whereBetween('orders.created_at', [$periodo->inicio, $periodo->fim])
            ->when(
                ! empty($filtros['company_id']),
                fn ($query) => $query->where('orders.company_id', $filtros['company_id'])
            )
            ->when(
                ! empty($filtros['branch_id']),
                fn ($query) => $query->where('orders.branch_id', $filtros['branch_id'])
            );
    }

    /**
     * Itens dos pedidos faturados no período.
     */
    private function itensBase(ReportPeriod $periodo, array $filtros)
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$periodo->inicio, $periodo->fim])
            ->whereIn('orders.status', [Order::STATUS_SHIPPED, Order::STATUS_COMPLETED])
            ->when(
                ! empty($filtros['company_id']),
                fn ($query) => $query->where('orders.company_id', $filtros['company_id'])
            )
            ->when(
                ! empty($filtros['branch_id']),
                fn ($query) => $query->where('orders.branch_id', $filtros['branch_id'])
            );
    }
}
