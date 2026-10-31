<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Products\Domain\Models\Price;
use Illuminate\Support\Facades\DB;

/**
 * Valor do estoque de um estabelecimento, em quantidade e em dinheiro.
 *
 * Fotografia do momento (não é período): quanto há em estoque, quanto isso vale
 * a CUSTO (capital parado) e a PREÇO DE VENDA (receita potencial), e a margem
 * potencial embutida. Estoque é por empresa; sem filtro de empresa, soma todas
 * as empresas do tenant.
 */
class ValorDeEstoque
{
    /**
     * @param  list<string>|null  $companyIds  null = todas as empresas do tenant
     * @return array{itens:float, produtos:int, valor_custo:float, valor_venda:float, margem_potencial:?float, produtos_sem_custo:int}
     */
    public function resumo(string $tenantId, ?array $companyIds = null): array
    {
        $inventarios = DB::table('inventories as i')
            ->where('i.tenant_id', $tenantId)
            ->when($companyIds !== null, fn ($q) => $q->whereIn('i.company_id', $companyIds))
            ->get(['i.product_id', 'i.quantity_on_hand']);

        $ids = $inventarios->pluck('product_id')->unique()->values();
        $precos = $ids->isEmpty()
            ? collect()
            : Price::query()->whereIn('product_id', $ids->all())
                ->whereIn('type', [Price::TYPE_COST, Price::TYPE_SALE])
                ->where('currency', 'BRL')
                ->get()
                ->groupBy('product_id');

        $itens = 0.0;
        $produtos = 0;
        $valorCusto = 0.0;
        $valorVenda = 0.0;
        $custoComAmbos = 0.0;
        $vendaComAmbos = 0.0;
        $semCusto = 0;

        foreach ($inventarios as $linha) {
            $qtd = (float) $linha->quantity_on_hand;
            $itens += $qtd;
            if ($qtd != 0.0) {
                $produtos++;
            }

            $preco = $precos->get($linha->product_id);
            $custo = $preco?->firstWhere('type', Price::TYPE_COST)?->amount;
            $venda = $preco?->firstWhere('type', Price::TYPE_SALE)?->amount;

            if ($custo !== null) {
                $valorCusto += $qtd * (float) $custo;
            } elseif ($qtd != 0.0) {
                $semCusto++;
            }
            if ($venda !== null) {
                $valorVenda += $qtd * (float) $venda;
            }
            if ($custo !== null && $venda !== null) {
                $custoComAmbos += $qtd * (float) $custo;
                $vendaComAmbos += $qtd * (float) $venda;
            }
        }

        return [
            'itens' => $itens,
            'produtos' => $produtos,
            'valor_custo' => $valorCusto,
            'valor_venda' => $valorVenda,
            'margem_potencial' => $vendaComAmbos > 0 ? ($vendaComAmbos - $custoComAmbos) / $vendaComAmbos * 100 : null,
            'produtos_sem_custo' => $semCusto,
        ];
    }
}
