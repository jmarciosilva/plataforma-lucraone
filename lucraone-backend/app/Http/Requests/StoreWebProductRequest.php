<?php

namespace App\Http\Requests;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Core\Http\Rules\IdentificadorDisponivel;
use App\Modules\Products\Domain\Models\Category;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Http\Concerns\ValidatesFiscalClassification;
use App\Modules\Products\Http\Rules\BarcodeAvailable;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreWebProductRequest extends FormRequest
{
    use ValidatesFiscalClassification;

    public function authorize(): bool
    {
        return $this->user()
            ? Gate::forUser($this->user())->allows('create', Product::class)
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
            'sku_automatico' => ['sometimes', 'boolean'],
            'sku' => [
                $this->boolean('sku_automatico') ? 'exclude' : 'nullable',
                'string',
                'max:100',
                new IdentificadorDisponivel(
                    tabela: 'products',
                    coluna: 'sku',
                    entidade: 'produto',
                    campo: 'SKU',
                    tenantId: $context->id(),
                ),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:14',
                new BarcodeAvailable($context->id()),
            ],
            'unit' => ['required', 'string', Rule::in(array_keys(Product::UNITS))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            ...$this->fiscalClassificationRules(),
            'status' => ['required', Rule::in(['active', 'inactive', 'discontinued'])],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => [
                'string',
                Rule::exists(Category::class, 'id')->where('tenant_id', $context->id()),
            ],
        ];
    }

    public function messages(): array
    {
        return $this->fiscalClassificationMessages();
    }
}
