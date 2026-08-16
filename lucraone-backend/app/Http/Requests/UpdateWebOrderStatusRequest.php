<?php

namespace App\Http\Requests;

use App\Modules\Sales\Domain\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateWebOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $this->user() && $order instanceof Order
            ? Gate::forUser($this->user())->allows('update', $order)
            : false;
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
