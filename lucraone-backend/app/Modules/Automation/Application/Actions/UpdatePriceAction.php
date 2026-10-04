<?php

namespace App\Modules\Automation\Application\Actions;

use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Products\Application\RegistrarPreco;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Services\CalculoMargem;
use App\Modules\Tenancy\Application\TenantContext;
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
    public const VARIACAO_MAXIMA_PERCENTUAL = CalculoMargem::VARIACAO_MAXIMA_PERCENTUAL;

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
        $valor = $regra->action_config['amount'] ?? 0;

        return app(TenantContext::class)->withTenant($regra->tenant_id, fn () => app(RegistrarPreco::class)->automatizar($produtoId, $tipo, $operacao, $valor, "automação: {$regra->name}")
        );
    }
}
