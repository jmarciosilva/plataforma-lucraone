<?php

namespace App\Modules\Automation\Application\Actions;

use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\PriceHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Atualiza o preço do produto que veio no gatilho.
 *
 * É a única ação que muda dado de negócio, então carrega travas: variação
 * percentual limitada, preço nunca negativo, e toda mudança gravada em
 * price_histories com a regra como motivo. Uma regra mal configurada pode
 * reprecificar o catálogo — o histórico é o que permite desfazer.
 */
class UpdatePriceAction implements ActionHandler
{
    public const CHAVE = 'update_price';

    public const OPERACAO_PERCENTUAL = 'percentual';

    public const OPERACAO_DEFINIR = 'definir';

    public const OPERACOES = [
        self::OPERACAO_PERCENTUAL => 'ajustar por percentual',
        self::OPERACAO_DEFINIR => 'definir valor fixo',
    ];

    /**
     * Teto de variação por execução. Uma regra que tentasse -90% seria recusada
     * antes de tocar no preço.
     */
    public const VARIACAO_MAXIMA_PERCENTUAL = 50.0;

    public static function rotulo(): string
    {
        return 'atualizar preço do produto';
    }

    public static function regrasDeConfiguracao(): array
    {
        return [
            'action_config.price_type' => ['required', Rule::in([Price::TYPE_SALE, Price::TYPE_COST])],
            'action_config.operation' => ['required', Rule::in(array_keys(self::OPERACOES))],
            'action_config.amount' => ['required', 'numeric'],
        ];
    }

    public function executar(AutomationRule $regra, array $payload): array
    {
        $produtoId = $payload['product_id'] ?? null;

        if (! $produtoId) {
            throw new InvalidArgumentException('este gatilho não carrega um produto para reprecificar.');
        }

        $tipo = (string) ($regra->action_config['price_type'] ?? Price::TYPE_SALE);
        $operacao = (string) ($regra->action_config['operation'] ?? self::OPERACAO_PERCENTUAL);
        $valor = (float) ($regra->action_config['amount'] ?? 0);

        return DB::transaction(function () use ($regra, $produtoId, $tipo, $operacao, $valor) {
            /*
            | O lock vai dentro de um tap: encadear ->lockForUpdate() direto
            | devolve o query builder cru, e o registro deixaria de ser
            | hidratado como Price. O bloqueio da linha é o mesmo.
            */
            $preco = Price::query()
                ->where('product_id', $produtoId)
                ->where('type', $tipo)
                ->tap(fn ($query) => $query->lockForUpdate())
                ->first();

            if (! $preco) {
                throw new InvalidArgumentException("o produto não tem preço de {$tipo} cadastrado.");
            }

            $anterior = (float) $preco->amount;
            $novo = $this->calcular($anterior, $operacao, $valor);

            if ($novo === $anterior) {
                return ['product_id' => $produtoId, 'price_type' => $tipo, 'unchanged' => true];
            }

            $preco->forceFill(['amount' => $novo])->save();

            PriceHistory::create([
                'tenant_id' => $regra->tenant_id,
                'price_id' => $preco->id,
                'product_id' => $produtoId,
                'old_amount' => $anterior,
                'new_amount' => $novo,
                'currency' => $preco->currency,
                'changed_by' => null,
                'reason' => "automação: {$regra->name}",
                'changed_at' => now(),
            ]);

            return [
                'product_id' => $produtoId,
                'price_type' => $tipo,
                'old_amount' => $anterior,
                'new_amount' => $novo,
            ];
        });
    }

    private function calcular(float $anterior, string $operacao, float $valor): float
    {
        if ($operacao === self::OPERACAO_DEFINIR) {
            if ($valor < 0) {
                throw new InvalidArgumentException('preço não pode ser negativo.');
            }

            return round($valor, 2);
        }

        if (abs($valor) > self::VARIACAO_MAXIMA_PERCENTUAL) {
            throw new InvalidArgumentException(
                'variação de '.$valor.'% acima do limite de '.self::VARIACAO_MAXIMA_PERCENTUAL.'% por execução.'
            );
        }

        return round(max(0.0, $anterior * (1 + ($valor / 100))), 2);
    }
}
