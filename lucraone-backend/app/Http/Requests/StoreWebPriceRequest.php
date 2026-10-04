<?php

namespace App\Http\Requests;

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

    protected function prepareForValidation(): void
    {
        foreach (['amount', 'margem_desejada'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && preg_match('/^\d+(?:[.,]\d{1,2})?$/D', $value)) {
                $this->merge([$field => str_replace(',', '.', $value)]);
            }
        }
    }

    public function rules(TenantContext $context): array
    {
        return [
            'currency' => ['required', 'string', 'size:3'],
            'amount' => ['bail', 'required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D', 'numeric', 'gt:0'],
            'margem_desejada' => ['exclude_unless:type,cost', 'nullable', 'regex:/^\d{1,4}(?:\.\d{1,2})?$/D', 'numeric', 'between:0,1000'],
            'type' => ['required', Rule::in(['cost', 'sale', 'suggested_retail'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.regex' => 'Informe um valor com até duas casas de centavos. Exemplo: 12,90.',
            'amount.gt' => 'O valor deve ser maior que zero.',
            'margem_desejada.regex' => 'Informe uma margem numérica com até duas casas decimais.',
            'margem_desejada.between' => 'A margem desejada deve estar entre 0% e 1000%.',
        ];
    }
}
