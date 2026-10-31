<?php

namespace App\Modules\Pdv\Application;

use App\Modules\Pdv\Domain\Models\PdvReceivedSale;
use App\Modules\Products\Domain\Models\Price;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resumo agregado das vendas do PDV num recorte (estabelecimento + filtros + dia).
 *
 * Fonte única dos números usados tanto na tela dedicada (/pdv/sales) quanto no
 * dashboard geral (/dashboard). Centralizar aqui evita que as duas telas
 * divirjam no cálculo — sobretudo na lucratividade, que cruza o preço de venda
 * praticado com o custo atual cadastrado do produto.
 *
 * A janela (início/fim) já chega em UTC, recortada no fuso de quem chama.
 */
class ResumoVendasPdv
{
    /**
     * Resumo agregado das vendas num período (uma janela qualquer em UTC).
     *
     * @return array{total:float, quantidade:int, ticket_medio:float, total_cancelado:float, eventos:int, lucro:float, margem:?float, receita_avaliada:float, itens_sem_custo:int}
     */
    public function noPeriodo(string $tenantId, ?string $branchId, ?string $terminalId, CarbonInterface $inicioUtc, CarbonInterface $fimUtc): array
    {
        $base = PdvReceivedSale::query()
            ->where('tenant_id', $tenantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($terminalId, fn ($q) => $q->where('terminal_id', $terminalId));

        $confirmadas = (clone $base)
            ->where('event_type', PdvReceivedSale::EVENT_CONFIRMED)
            ->whereBetween('confirmed_at', [$inicioUtc, $fimUtc])
            ->get(['id', 'total_amount']);

        $total = $confirmadas->sum(fn ($v) => (float) $v->total_amount);
        $quantidade = $confirmadas->count();

        $totalCancelado = (clone $base)
            ->where('event_type', PdvReceivedSale::EVENT_CANCELLED)
            ->whereBetween('cancelled_at', [$inicioUtc, $fimUtc])
            ->get(['total_amount'])
            ->sum(fn ($v) => (float) $v->total_amount);

        $eventos = (clone $base)
            ->where(function ($q) use ($inicioUtc, $fimUtc) {
                $q->whereBetween('confirmed_at', [$inicioUtc, $fimUtc])
                    ->orWhereBetween('cancelled_at', [$inicioUtc, $fimUtc]);
            })
            ->count();

        return [
            'total' => $total,
            'quantidade' => $quantidade,
            'ticket_medio' => $quantidade > 0 ? $total / $quantidade : 0.0,
            'total_cancelado' => $totalCancelado,
            'eventos' => $eventos,
            ...$this->lucratividade($confirmadas->pluck('id')),
        ];
    }

    /**
     * Vendas confirmadas do período distribuídas por forma de pagamento.
     *
     * Pagamento simples: o total da venda vai para `payment_method`. Pagamento
     * dividido (split): cada entrada vai para a sua própria forma. Assim o total
     * por forma soma exatamente o faturamento confirmado do período.
     *
     * @return list<array{metodo:string, rotulo:string, total:float, percentual:float}>
     */
    public function porFormaDePagamento(string $tenantId, ?string $branchId, ?string $terminalId, CarbonInterface $inicioUtc, CarbonInterface $fimUtc): array
    {
        $escopo = function ($q) use ($tenantId, $branchId, $terminalId, $inicioUtc, $fimUtc) {
            return $q->where('s.tenant_id', $tenantId)
                ->where('s.event_type', PdvReceivedSale::EVENT_CONFIRMED)
                ->whereBetween('s.confirmed_at', [$inicioUtc, $fimUtc])
                ->when($branchId, fn ($qq) => $qq->where('s.branch_id', $branchId))
                ->when($terminalId, fn ($qq) => $qq->where('s.terminal_id', $terminalId));
        };

        // Vendas de pagamento simples (sem entradas de split).
        $simples = $escopo(DB::table('pdv_received_sales as s'))
            ->whereNotExists(fn ($q) => $q->from('pdv_received_sale_payment_entries as e')->whereColumn('e.sale_id', 's.id'))
            ->selectRaw('s.payment_method as metodo, sum(s.total_amount) as total')
            ->groupBy('s.payment_method')
            ->get();

        // Entradas de split, cada uma na sua forma.
        $split = $escopo(DB::table('pdv_received_sale_payment_entries as e')->join('pdv_received_sales as s', 's.id', '=', 'e.sale_id'))
            ->selectRaw('e.method as metodo, sum(e.amount) as total')
            ->groupBy('e.method')
            ->get();

        $baldes = array_fill_keys(PdvReceivedSale::PAYMENT_METHODS, 0.0);
        foreach ([$simples, $split] as $conjunto) {
            foreach ($conjunto as $linha) {
                $metodo = (string) ($linha->metodo ?? '');
                if ($metodo === '') {
                    continue;
                }
                $baldes[$metodo] = ($baldes[$metodo] ?? 0.0) + (float) $linha->total;
            }
        }

        $total = array_sum($baldes);

        $resultado = [];
        foreach ($baldes as $metodo => $valor) {
            $resultado[] = [
                'metodo' => $metodo,
                'rotulo' => PdvReceivedSale::paymentLabel($metodo),
                'total' => $valor,
                'percentual' => $total > 0 ? $valor / $total * 100 : 0.0,
            ];
        }

        return $resultado;
    }

    /**
     * Lucro e margem a partir dos itens das vendas confirmadas informadas.
     *
     * Lucro do item = total do item (preço praticado) − custo atual do produto
     * × quantidade. A margem é sobre a receita dos itens COM custo conhecido,
     * para não diluir o percentual com itens que não dá para avaliar.
     *
     * @param  Collection<int, string>  $idsConfirmadas
     * @return array{lucro:float, margem:?float, receita_avaliada:float, itens_sem_custo:int}
     */
    public function lucratividade(Collection $idsConfirmadas): array
    {
        if ($idsConfirmadas->isEmpty()) {
            return ['lucro' => 0.0, 'margem' => null, 'receita_avaliada' => 0.0, 'itens_sem_custo' => 0];
        }

        $itens = DB::table('pdv_received_sale_items as it')
            ->join('pdv_received_sales as s', 's.id', '=', 'it.sale_id')
            ->whereIn('it.sale_id', $idsConfirmadas->all())
            ->get(['it.product_id', 'it.quantity', 'it.total_amount', 's.currency']);

        $produtoIds = $itens->pluck('product_id')->filter()->unique()->values();
        $custos = $produtoIds->isEmpty()
            ? collect()
            : Price::query()->whereIn('product_id', $produtoIds->all())->where('type', Price::TYPE_COST)->get()
                ->mapWithKeys(fn (Price $p) => [$p->product_id.'|'.strtoupper($p->currency) => (float) $p->amount]);

        $lucro = 0.0;
        $receitaAvaliada = 0.0;
        $semCusto = 0;
        foreach ($itens as $item) {
            $chave = $item->product_id.'|'.strtoupper((string) $item->currency);
            if ($item->product_id !== null && $custos->has($chave)) {
                $custoItem = $custos->get($chave) * (float) $item->quantity;
                $lucro += (float) $item->total_amount - $custoItem;
                $receitaAvaliada += (float) $item->total_amount;
            } else {
                $semCusto++;
            }
        }

        return [
            'lucro' => $lucro,
            'margem' => $receitaAvaliada > 0 ? $lucro / $receitaAvaliada * 100 : null,
            'receita_avaliada' => $receitaAvaliada,
            'itens_sem_custo' => $semCusto,
        ];
    }
}
