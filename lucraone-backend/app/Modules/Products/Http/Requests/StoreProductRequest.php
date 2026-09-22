<?php

namespace App\Modules\Products\Http\Requests;

use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(TenantContext $context): array
    {
        return [
            'company_id' => ['required', 'string'],
            'sku' => ['required', 'string', 'max:100'],
            'barcode' => [
                'nullable',
                'string',
                'max:14',
                Rule::unique('products', 'barcode')->where('tenant_id', $context->id()),
            ],
            // Opcional na API para não quebrar clientes atuais; ausente vira UN.
            'unit' => ['sometimes', 'string', Rule::in(array_keys(Product::UNITS))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,inactive,discontinued'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['string'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.required' => 'SKU é obrigatório',
            'sku.unique' => 'SKU já existe',
            'name.required' => 'Nome do produto é obrigatório',
            'company_id.required' => 'Empresa é obrigatória',
        ];
    }
}
