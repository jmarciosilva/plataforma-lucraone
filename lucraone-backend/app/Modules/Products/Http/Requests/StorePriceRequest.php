<?php

namespace App\Modules\Products\Http\Requests;

use App\Modules\Products\Domain\Models\Price;
use Illuminate\Foundation\Http\FormRequest;

class StorePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'string', 'uuid'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'type' => ['required', 'in:cost,sale,suggested_retail'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Valor do preço é obrigatório',
            'amount.numeric' => 'Valor deve ser um número',
            'currency.required' => 'Moeda é obrigatória',
            'type.required' => 'Tipo de preço é obrigatório',
        ];
    }
}
