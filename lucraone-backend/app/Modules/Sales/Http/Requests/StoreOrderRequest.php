<?php

namespace App\Modules\Sales\Http\Requests;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Sales\Domain\Models\Customer;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(TenantContext $context): array
    {
        return [
            'company_id' => [
                'required',
                'string',
                Rule::exists(Company::class, 'id')->where('tenant_id', $context->id()),
            ],
            'customer_id' => [
                'nullable',
                'string',
                Rule::exists(Customer::class, 'id')->where('tenant_id', $context->id()),
            ],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => [
                'required',
                'string',
                Rule::exists(Product::class, 'id')->where('tenant_id', $context->id()),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'a empresa do pedido é obrigatória.',
            'items.*.quantity.min' => 'a quantidade do item precisa ser maior que zero.',
        ];
    }
}
