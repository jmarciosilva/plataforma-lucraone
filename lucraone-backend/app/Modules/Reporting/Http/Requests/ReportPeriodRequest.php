<?php

namespace App\Modules\Reporting\Http\Requests;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Reporting\Domain\ReportPeriod;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReportPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            ? Gate::forUser($this->user())->allows('view-reports')
            : false;
    }

    public function rules(TenantContext $context): array
    {
        return [
            'inicio' => ['nullable', 'date'],
            'fim' => ['nullable', 'date'],
            'granularidade' => ['nullable', Rule::in(ReportPeriod::GRANULARIDADES)],
            'company_id' => [
                'nullable',
                'string',
                Rule::exists(Company::class, 'id')->where('tenant_id', $context->id()),
            ],
            'limite' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function periodo(TenantContext $context): ReportPeriod
    {
        return ReportPeriod::fromInput(
            $this->input('inicio'),
            $this->input('fim'),
            $this->input('granularidade'),
            $context->tenant()->timezone ?: 'UTC',
        );
    }

    public function filtros(): array
    {
        return array_filter([
            'company_id' => $this->input('company_id'),
        ]);
    }

    public function messages(): array
    {
        return [
            'granularidade.in' => 'agrupamento inválido: use day, week, month ou year.',
        ];
    }
}
