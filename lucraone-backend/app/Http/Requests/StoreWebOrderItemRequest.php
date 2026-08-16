<?php

namespace App\Http\Requests;

use App\Modules\Products\Domain\Models\Product;
use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreWebOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $this->user() && $order instanceof Order
            ? Gate::forUser($this->user())->allows('update', $order)
            : false;
    }

    public function rules(TenantContext $context): array
    {
        return [
            'product_id' => [
                'required',
                'string',
                Rule::exists(Product::class, 'id')->where('tenant_id', $context->id()),
            ],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'selecione o produto.',
            'quantity.min' => 'a quantidade precisa ser maior que zero.',
        ];
    }
}
