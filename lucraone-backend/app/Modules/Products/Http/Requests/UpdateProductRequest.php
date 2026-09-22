<?php

namespace App\Modules\Products\Http\Requests;

use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Http\Rules\BarcodeAvailable;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(TenantContext $context): array
    {
        return [
            'sku' => ['sometimes', 'string', 'max:100'],
            'barcode' => [
                'nullable',
                'string',
                'max:14',
                new BarcodeAvailable($context->id(), $this->route('product')),
            ],
            'unit' => ['sometimes', 'string', Rule::in(array_keys(Product::UNITS))],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'in:active,inactive,discontinued'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['string', 'uuid'],
        ];
    }
}
