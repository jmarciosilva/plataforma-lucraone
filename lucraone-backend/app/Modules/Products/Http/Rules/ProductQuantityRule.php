<?php

namespace App\Modules\Products\Http\Rules;

use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Domain\Services\ProductQuantity;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/** Adaptador HTTP; a autoridade de quantidade permanece no domínio. */
class ProductQuantityRule implements DataAwareRule, ValidationRule
{
    private array $data = [];

    public function __construct(private ?string $productId = null) {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $productId = $this->productId ?? Arr::get($this->data, preg_replace('/quantity$/', 'product_id', $attribute));
        if (! is_string($productId)) {
            return; // A regra do identificador é responsável por este erro.
        }
        $product = Product::query()->find($productId);
        if (! $product) {
            return;
        }

        try {
            ProductQuantity::normalize($product->unit, $value);
        } catch (InvalidArgumentException $exception) {
            $fail($exception->getMessage());
        }
    }
}
