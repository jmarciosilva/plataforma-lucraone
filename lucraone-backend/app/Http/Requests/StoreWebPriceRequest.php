<?php

namespace App\Http\Requests;

use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreWebPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $this->user() && $product
            ? Gate::forUser($this->user())->allows('update', $product)
            : false;
    }

    public function rules(TenantContext $context): array
    {
        return [
            'currency' => ['required', 'string', 'size:3'],
            'amount' => ['required', 'numeric', 'min:0'],
            'type' => ['required', Rule::in(['cost', 'sale', 'suggested_retail'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
