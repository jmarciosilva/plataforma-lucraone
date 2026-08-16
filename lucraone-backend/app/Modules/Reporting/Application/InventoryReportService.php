<?php

namespace App\Modules\Reporting\Application;

use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Products\Domain\Models\Price;

/**
 * Relatório de estoque: situação e valor parado na prateleira.
 *
 * O valor a custo responde "quanto dinheiro está imobilizado"; o valor a venda
 * responde "quanto isso vira se vender tudo". A diferença é a margem potencial.
 *
 * Estoque é uma foto do agora — não tem recorte de período.
 */
class InventoryReportService
{
    public const SITUACAO_OK = 'ok';

    public const SITUACAO_BAIXO = 'baixo';

    public const SITUACAO_EXCESSO = 'excesso';

    public function linhas(array $filtros = []): array
    {
        $inventarios = Inventory::query()
            ->with(['product', 'company', 'stockLevel'])
            ->when(
                ! empty($filtros['company_id']),
                fn ($query) => $query->where('company_id', $filtros['company_id'])
            )
            ->get();

        $precos = $this->precosPorProduto($inventarios->pluck('product_id')->all());

        $linhas = $inventarios->map(function (Inventory $inventario) use ($precos) {
            $quantidade = (float) $inventario->quantity_on_hand;
            $custo = (float) ($precos[$inventario->product_id][Price::TYPE_COST] ?? 0);
            $venda = (float) ($precos[$inventario->product_id][Price::TYPE_SALE] ?? 0);

            return [
                'inventory_id' => $inventario->id,
                'product_id' => $inventario->product_id,
                'produto' => $inventario->product?->name ?? 'produto removido',
                'sku' => $inventario->product?->sku,
                'empresa' => $inventario->company?->trade_name ?: $inventario->company?->legal_name,
                'quantidade' => $quantidade,
                'reservado' => (float) $inventario->reserved,
                'disponivel' => (float) $inventario->available,
                'preco_custo' => $custo,
                'preco_venda' => $venda,
                'valor_custo' => round($quantidade * $custo, 2),
                'valor_venda' => round($quantidade * $venda, 2),
                'situacao' => $this->situacao($inventario),
            ];
        });

        if (! empty($filtros['situacao'])) {
            $linhas = $linhas->where('situacao', $filtros['situacao']);
        }

        return $linhas->sortByDesc('valor_custo')->values()->all();
    }

    /**
     * Recebe as linhas já calculadas para não repetir a consulta.
     */
    public function resumo(array $linhas): array
    {
        $linhas = collect($linhas);

        $valorCusto = (float) $linhas->sum('valor_custo');
        $valorVenda = (float) $linhas->sum('valor_venda');

        return [
            'itens' => $linhas->count(),
            'unidades' => (float) $linhas->sum('quantidade'),
            'reservado' => (float) $linhas->sum('reservado'),
            'valor_custo' => $valorCusto,
            'valor_venda' => $valorVenda,
            'margem_potencial' => round($valorVenda - $valorCusto, 2),
            'margem_percentual' => $valorCusto > 0
                ? round((($valorVenda - $valorCusto) / $valorCusto) * 100, 1)
                : null,
            'sem_preco_custo' => $linhas->where('preco_custo', 0.0)->count(),
        ];
    }

    /**
     * Quantos itens em cada situação — alimenta os alertas da tela.
     */
    public function porSituacao(array $linhas): array
    {
        $linhas = collect($linhas);

        return [
            self::SITUACAO_OK => $linhas->where('situacao', self::SITUACAO_OK)->count(),
            self::SITUACAO_BAIXO => $linhas->where('situacao', self::SITUACAO_BAIXO)->count(),
            self::SITUACAO_EXCESSO => $linhas->where('situacao', self::SITUACAO_EXCESSO)->count(),
        ];
    }

    private function situacao(Inventory $inventario): string
    {
        $nivel = $inventario->stockLevel;

        if (! $nivel) {
            return self::SITUACAO_OK;
        }

        if ((float) $inventario->quantity_on_hand <= (float) $nivel->reorder_point) {
            return self::SITUACAO_BAIXO;
        }

        if ($nivel->max_qty !== null && (float) $inventario->quantity_on_hand > (float) $nivel->max_qty) {
            return self::SITUACAO_EXCESSO;
        }

        return self::SITUACAO_OK;
    }

    /**
     * Preços de custo e venda indexados por produto, numa consulta só.
     */
    private function precosPorProduto(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return Price::query()
            ->whereIn('product_id', $productIds)
            ->whereIn('type', [Price::TYPE_COST, Price::TYPE_SALE])
            ->get()
            ->groupBy('product_id')
            ->map(fn ($precos) => $precos->keyBy('type')->map(fn ($preco) => (float) $preco->amount)->all())
            ->all();
    }
}
