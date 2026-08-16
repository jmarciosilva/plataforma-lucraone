<?php

namespace App\Modules\Reporting\Application;

use App\Modules\Reporting\Domain\ReportPeriod;

/**
 * KPIs comerciais do estabelecimento, com comparação contra o período anterior
 * de mesmo tamanho.
 *
 * É o que alimenta a faixa comercial do dashboard e o topo dos relatórios.
 */
class DashboardSummaryService
{
    public function __construct(
        private SalesReportService $vendas,
        private InventoryReportService $estoque,
        private CustomerReportService $clientes,
    ) {}

    public function resumo(ReportPeriod $periodo, array $filtros = []): array
    {
        $atual = $this->vendas->totais($periodo, $filtros);
        $anterior = $this->vendas->totais($periodo->anterior(), $filtros);

        $linhasEstoque = $this->estoque->linhas($filtros);
        $situacoes = $this->estoque->porSituacao($linhasEstoque);
        $resumoEstoque = $this->estoque->resumo($linhasEstoque);

        return [
            'periodo' => $periodo->toArray(),
            'faturamento' => [
                'valor' => $atual['faturamento'],
                'anterior' => $anterior['faturamento'],
                'variacao' => $this->variacao($atual['faturamento'], $anterior['faturamento']),
            ],
            'pedidos' => [
                'valor' => $atual['pedidos_faturados'],
                'anterior' => $anterior['pedidos_faturados'],
                'variacao' => $this->variacao($atual['pedidos_faturados'], $anterior['pedidos_faturados']),
            ],
            'ticket_medio' => [
                'valor' => $atual['ticket_medio'],
                'anterior' => $anterior['ticket_medio'],
                'variacao' => $this->variacao($atual['ticket_medio'], $anterior['ticket_medio']),
            ],
            'carteira_em_aberto' => $atual['carteira_em_aberto'],
            'itens_vendidos' => $atual['itens_vendidos'],
            'pedidos_cancelados' => $atual['pedidos_cancelados'],
            'estoque' => [
                'valor_custo' => $resumoEstoque['valor_custo'],
                'valor_venda' => $resumoEstoque['valor_venda'],
                'margem_potencial' => $resumoEstoque['margem_potencial'],
                'itens' => $resumoEstoque['itens'],
                'baixo' => $situacoes[InventoryReportService::SITUACAO_BAIXO],
                'excesso' => $situacoes[InventoryReportService::SITUACAO_EXCESSO],
            ],
            'clientes' => $this->clientes->totais($periodo, $filtros),
        ];
    }

    /**
     * Variação percentual contra o período anterior.
     *
     * Sem base anterior não existe percentual — devolve null em vez de 100%,
     * que faria o primeiro mês de operação parecer um crescimento.
     */
    private function variacao(float|int $atual, float|int $anterior): ?float
    {
        if ((float) $anterior == 0.0) {
            return null;
        }

        return round((((float) $atual - (float) $anterior) / (float) $anterior) * 100, 1);
    }
}
