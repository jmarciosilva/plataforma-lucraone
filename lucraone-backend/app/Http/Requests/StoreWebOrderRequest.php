<?php

namespace App\Http\Requests;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Sales\Domain\Models\Customer;
use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreWebOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            ? Gate::forUser($this->user())->allows('create', Order::class)
            : false;
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
            'customer_name' => ['nullable', 'string', 'max:255', 'required_without:customer_id'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.required_without' => 'selecione um cliente existente ou informe o nome de um novo.',
        ];
    }

    /**
     * O formulário manda os dois campos; quem escolheu cliente existente não
     * deve criar um cliente novo com o texto deixado para trás.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('customer_id')) {
            $this->merge([
                'customer_name' => null,
                'customer_email' => null,
                'customer_phone' => null,
            ]);
        }
    }
}
