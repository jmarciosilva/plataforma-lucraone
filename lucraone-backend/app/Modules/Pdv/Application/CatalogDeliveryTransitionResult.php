<?php

namespace App\Modules\Pdv\Application;

use App\Modules\Pdv\Domain\Models\PdvCatalogDelivery;

final readonly class CatalogDeliveryTransitionResult
{
    private function __construct(
        public ?PdvCatalogDelivery $delivery,
        public ?string $errorCode = null,
        public int $httpStatus = 200,
        public string $message = '',
    ) {}

    public static function success(PdvCatalogDelivery $delivery): self
    {
        return new self($delivery);
    }

    public static function error(string $code, string $message, int $status): self
    {
        return new self(null, $code, $status, $message);
    }

    public function successful(): bool
    {
        return $this->delivery !== null;
    }
}
