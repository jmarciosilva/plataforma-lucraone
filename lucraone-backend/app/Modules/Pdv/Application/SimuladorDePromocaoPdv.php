<?php

namespace App\Modules\Pdv\Application;

use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Simula o impacto de uma promoção (desconto no preço de venda) de um produto
 * na lucratividade, com base nas vendas recentes do próprio produto.
 *
 * Responde à pergunta da dona do mercado: "se eu der X% de desconto neste
 * produto, quanto perco de margem e quantas unidades a mais preciso vender para
 * manter o mesmo lucro?" (ponto de equilíbrio).
 *
 * A base de volume são as vendas confirmadas do período informado. Com pouco
 * histórico a estimativa é fraca — melhora conforme as vendas acumulam.
 */
class SimuladorDePromocaoPdv
{
    /**
     * @return array<string, mixed>
     */
    public function simular(
        string $tenantId,
        ?string $branchId,
        ?string $terminalId,
        string $productId,
        float $descontoPercentual,
        CarbonInterface $inicioUtc,
        CarbonInterface $fimUtc,
    ): array {
        $produto = Product::withoutGlobalScopes()->withTrashed()
            ->where('tenant_id', $tenantId)->whereKey($productId)->first();
        if ($produto === null) {
            return ['ok' => false, 'erro' => 'Produto não encontrado neste estabelecimento.'];
        }

        $precos = Price::query()->where('product_id', $produto->id)
            ->whereIn('type', [Price::TYPE_COST, Price::TYPE_SALE])->where('currency', 'BRL')->get();
        $custo = $precos->firstWhere('type', Price::TYPE_COST)?->amount;
        $venda = $precos->firstWhere('type', Price::TYPE_SALE)?->amount;

        if ($venda === null || $custo === null) {
            return ['ok' => false, 'erro' => 'O produto precisa de preço de custo e de venda cadastrados para simular.'];
        }

        $precoVenda = (float) $venda;
        $custoUnit = (float) $custo;
        $novoPreco = round($precoVenda * (1 - $descontoPercentual / 100), 2);

        $margemAtual = $precoVenda - $custoUnit;
        $margemPromo = $novoPreco - $custoUnit;

        $volume = (float) DB::table('pdv_received_sale_items as it')
            ->join('pdv_received_sales as s', 's.id', '=', 'it.sale_id')
            ->where('s.tenant_id', $tenantId)
            ->where('s.event_type', 'SALE_CONFIRMED')
            ->whereBetween('s.confirmed_at', [$inicioUtc, $fimUtc])
            ->when($branchId, fn ($q) => $q->where('s.branch_id', $branchId))
            ->when($terminalId, fn ($q) => $q->where('s.terminal_id', $terminalId))
            ->where('it.product_id', $produto->id)
            ->sum('it.quantity');

        $lucroBase = $volume * $margemAtual;
        $lucroPromo = $volume * $margemPromo;

        $vendeAbaixoDoCusto = $margemPromo <= 0;
        $pontoEquilibrio = $vendeAbaixoDoCusto ? null : $lucroBase / $margemPromo;
        $unidadesAdicionais = $pontoEquilibrio === null ? null : max(0.0, $pontoEquilibrio - $volume);
        $aumentoNecessarioPct = ($pontoEquilibrio === null || $volume <= 0) ? null : ($unidadesAdicionais / $volume * 100);

        return [
            'ok' => true,
            'produto' => $produto->name,
            'sku' => $produto->sku,
            'desconto_percentual' => $descontoPercentual,
            'preco_venda_atual' => $precoVenda,
            'preco_promocional' => $novoPreco,
            'custo' => $custoUnit,
            'margem_atual_unit' => $margemAtual,
            'margem_promo_unit' => $margemPromo,
            'margem_atual_pct' => $precoVenda > 0 ? $margemAtual / $precoVenda * 100 : null,
            'margem_promo_pct' => $novoPreco > 0 ? $margemPromo / $novoPreco * 100 : null,
            'volume_base' => $volume,
            'lucro_base' => $lucroBase,
            'lucro_promo_mesmo_volume' => $lucroPromo,
            'impacto_lucro' => $lucroPromo - $lucroBase,
            'vende_abaixo_do_custo' => $vendeAbaixoDoCusto,
            'ponto_equilibrio_unidades' => $pontoEquilibrio,
            'unidades_adicionais' => $unidadesAdicionais,
            'aumento_necessario_pct' => $aumentoNecessarioPct,
        ];
    }
}
