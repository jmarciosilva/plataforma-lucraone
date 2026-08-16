<?php

namespace App\Modules\Products\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'string', 'uuid'],
            'sku' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,inactive,discontinued'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['string', 'uuid'],
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
