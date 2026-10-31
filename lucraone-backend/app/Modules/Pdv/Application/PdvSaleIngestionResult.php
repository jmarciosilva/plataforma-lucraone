<?php

namespace App\Modules\Pdv\Application;

use Carbon\CarbonInterface;

/**
 * Resultado da ingestão de uma venda do PDV.
 *
 * `duplicate` distingue o reenvio idempotente (o mesmo `idempotency_key` já
 * aceito antes) da gravação nova. A controller o traduz em 409/201.
 */
final class PdvSaleIngestionResult
{
    public function __construct(
        public readonly string $saleId,
        public readonly CarbonInterface $receivedAt,
        public readonly bool $duplicate,
    ) {}
}
