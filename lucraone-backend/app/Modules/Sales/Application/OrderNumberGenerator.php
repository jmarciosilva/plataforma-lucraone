<?php

namespace App\Modules\Sales\Application;

use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Tenancy\Application\TenantContext;

/**
 * Numeração sequencial de pedidos por tenant e por mês.
 *
 * Formato: PED-AAAAMM-0001
 */
class OrderNumberGenerator
{
    public function __construct(
        private TenantContext $context
    ) {}

    public function next(): string
    {
        $prefixo = 'PED-'.now()->format('Ym').'-';

        $ultimo = Order::query()
            ->where('tenant_id', $this->context->id())
            ->where('order_number', 'like', $prefixo.'%')
            ->lockForUpdate()
            ->max('order_number');

        $sequencia = $ultimo
            ? ((int) substr($ultimo, strlen($prefixo))) + 1
            : 1;

        return $prefixo.str_pad((string) $sequencia, 4, '0', STR_PAD_LEFT);
    }
}
