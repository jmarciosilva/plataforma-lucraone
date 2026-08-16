<?php

namespace App\Modules\Reporting\Application;

use App\Modules\Reporting\Domain\ReportPeriod;
use App\Modules\Sales\Domain\Models\Customer;
use App\Modules\Sales\Domain\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de clientes: quem compra, quanto e há quanto tempo.
 *
 * Só conta pedido faturado — cliente com três rascunhos abandonados não é
 * cliente recorrente.
 */
class CustomerReportService
{
    public const SEGMENTO_VIP = 'vip';

    public const SEGMENTO_RECORRENTE = 'recorrente';

    public const SEGMENTO_NOVO = 'novo';

    public const SEGMENTO_SEM_COMPRA = 'sem_compra';

    /**
     * Clientes ordenados por quanto gastaram no período.
     */
    public function top(ReportPeriod $periodo, array $filtros = [], int $limite = 10): array
    {
        return collect($this->agregado($periodo, $filtros))
            ->sortByDesc('total_gasto')
            ->take($limite)
            ->values()
            ->all();
    }

    /**
     * Distribuição dos clientes em faixas de relacionamento.
     *
     * vip        = 3+ pedidos faturados no período
     * recorrente = 2 pedidos
     * novo       = 1 pedido
     * sem compra = cadastrado, sem pedido faturado no período
     */
    public function segmentacao(ReportPeriod $periodo, array $filtros = []): array
    {
        $comCompra = collect($this->agregado($periodo, $filtros));

        $cadastrados = Customer::query()
            ->when(
                ! empty($filtros['company_id']),
                fn ($query) => $query->where('company_id', $filtros['company_id'])
            )
            ->count();

        $segmentos = [
            self::SEGMENTO_VIP => $comCompra->where('pedidos', '>=', 3)->count(),
            self::SEGMENTO_RECORRENTE => $comCompra->where('pedidos', 2)->count(),
            self::SEGMENTO_NOVO => $comCompra->where('pedidos', 1)->count(),
        ];

        $segmentos[self::SEGMENTO_SEM_COMPRA] = max(0, $cadastrados - $comCompra->count());

        return $segmentos;
    }

    public function totais(ReportPeriod $periodo, array $filtros = []): array
    {
        $comCompra = collect($this->agregado($periodo, $filtros));

        $cadastrados = Customer::query()
            ->when(
                ! empty($filtros['company_id']),
                fn ($query) => $query->where('company_id', $filtros['company_id'])
            )
            ->count();

        $novosNoPeriodo = Customer::query()
            ->whereBetween('created_at', [$periodo->inicio, $periodo->fim])
            ->when(
                ! empty($filtros['company_id']),
                fn ($query) => $query->where('company_id', $filtros['company_id'])
            )
            ->count();

        return [
            'cadastrados' => $cadastrados,
            'novos_no_periodo' => $novosNoPeriodo,
            'compraram_no_periodo' => $comCompra->count(),
            'ticket_medio' => $comCompra->count() > 0
                ? (float) $comCompra->sum('total_gasto') / max(1, (int) $comCompra->sum('pedidos'))
                : 0.0,
            'gasto_medio_por_cliente' => $comCompra->count() > 0
                ? (float) $comCompra->sum('total_gasto') / $comCompra->count()
                : 0.0,
        ];
    }

    /**
     * Uma linha por cliente que faturou no período.
     */
    private function agregado(ReportPeriod $periodo, array $filtros): array
    {
        return Order::query()
            ->revenue()
            ->whereNotNull('customer_id')
            ->whereBetween('orders.created_at', [$periodo->inicio, $periodo->fim])
            ->when(
                ! empty($filtros['company_id']),
                fn ($query) => $query->where('orders.company_id', $filtros['company_id'])
            )
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->select(
                'orders.customer_id',
                DB::raw('MAX(customers.name) as nome'),
                DB::raw('MAX(customers.email) as email'),
                DB::raw('COUNT(*) as pedidos'),
                DB::raw('SUM(orders.total) as total_gasto'),
                DB::raw('MAX(orders.created_at) as ultima_compra'),
            )
            ->groupBy('orders.customer_id')
            ->get()
            ->map(fn ($linha) => [
                'customer_id' => $linha->customer_id,
                'nome' => $linha->nome,
                'email' => $linha->email,
                'pedidos' => (int) $linha->pedidos,
                'total_gasto' => (float) $linha->total_gasto,
                'ticket_medio' => $linha->pedidos > 0 ? (float) $linha->total_gasto / (int) $linha->pedidos : 0.0,
                'ultima_compra' => $linha->ultima_compra,
            ])
            ->all();
    }
}
