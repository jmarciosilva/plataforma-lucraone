<?php

namespace App\Modules\Sales\Http\Requests;

use App\Modules\Sales\Domain\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(Order::TRANSICOES))],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'status de pedido inválido.',
        ];
    }
}
