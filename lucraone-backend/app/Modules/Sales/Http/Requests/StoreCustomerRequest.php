<?php

namespace App\Modules\Sales\Http\Requests;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Sales\Domain\Models\Customer;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'document' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', Rule::in([Customer::STATUS_ACTIVE, Customer::STATUS_INACTIVE])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'o nome do cliente é obrigatório.',
            'company_id.required' => 'a empresa do cliente é obrigatória.',
        ];
    }
}
