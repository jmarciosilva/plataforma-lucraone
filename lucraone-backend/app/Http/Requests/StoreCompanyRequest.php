<?php

namespace App\Http\Requests;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            ? Gate::forUser($this->user())->allows('create', Company::class)
            : false;
    }

    public function rules(TenantContext $context): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'document' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('companies', 'document')->where('tenant_id', $context->id()),
            ],
            'state_registration' => ['nullable', 'string', 'max:64'],
            'municipal_registration' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE', 'SUSPENDED'])],
        ];
    }
}
