<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuSectionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string'],
            'price_override' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'price_currency' => ['nullable', 'string', 'size:3', 'required_with:price_override'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'available' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->price_override === '' || $this->price_override === null) {
            $this->merge(['price_override' => null, 'price_currency' => null]);
        }
    }
}
