<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuSectionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sectionId = $this->route('cardapio')?->id;

        return [
            'product_id' => [
                'required',
                'string',
                Rule::unique('menu_section_items', 'product_id')->where('menu_section_id', $sectionId),
            ],
            'description' => ['nullable', 'string'],
            'price_override' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'price_currency' => ['nullable', 'string', 'size:3', 'required_with:price_override'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'available' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'price_currency.required_with' => 'A moeda é obrigatória quando o preço override está preenchido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Se price_override for vazio (string vazia), normaliza para null
        if ($this->price_override === '' || $this->price_override === null) {
            $this->merge(['price_override' => null, 'price_currency' => null]);
        }
    }
}
