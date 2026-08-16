<?php

namespace App\Http\Requests;

use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            ? Gate::forUser($this->user())->allows('create', Tenant::class)
            : false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('tenants', 'slug')],
            'status' => ['required', Rule::in(['TRIAL', 'ACTIVE', 'SUSPENDED', 'CANCELLED'])],
            'plan' => ['required', Rule::in(['free', 'standard', 'enterprise'])],
            'timezone' => ['required', Rule::in(['America/Sao_Paulo', 'America/Manaus', 'America/Bahia', 'UTC'])],
            'locale' => ['required', Rule::in(['pt-BR', 'en-US'])],
            'currency' => ['required', Rule::in(['BRL', 'USD'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug($this->string('name')->toString())]);
        }
    }
}
