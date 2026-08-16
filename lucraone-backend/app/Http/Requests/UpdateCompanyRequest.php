<?php

namespace App\Http\Requests;

use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');

        return $this->user() && $company
            ? Gate::forUser($this->user())->allows('update', $company)
            : false;
    }

    public function rules(TenantContext $context): array
    {
        $company = $this->route('company');

        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'document' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('companies', 'document')
                    ->where('tenant_id', $context->id())
                    ->ignore($company?->id),
            ],
            'state_registration' => ['nullable', 'string', 'max:64'],
            'municipal_registration' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE', 'SUSPENDED'])],
        ];
    }
}
