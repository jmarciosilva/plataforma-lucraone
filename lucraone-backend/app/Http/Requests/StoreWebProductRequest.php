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

    /**
     * Aceita os preços no formato do painel ("12,90") antes de validar, igual ao
     * StoreWebPriceRequest, e garante uma moeda padrão para o cadastro.
     */
    protected function prepareForValidation(): void
    {
        foreach (['cost_price', 'sale_price'] as $campo) {
            $valor = $this->input($campo);
            if (is_string($valor) && preg_match('/^\d+(?:[.,]\d{1,2})?$/D', $valor)) {
                $this->merge([$campo => str_replace(',', '.', $valor)]);
            }
        }

        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper(trim($this->input('currency')))]);
        }
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
            'currency' => ['required', 'string', 'size:3'],
            'cost_price' => ['bail', 'required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D', 'numeric', 'gt:0'],
            'sale_price' => ['bail', 'required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D', 'numeric', 'gt:0'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => [
                'string',
                Rule::exists(Category::class, 'id')->where('tenant_id', $context->id()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->fiscalClassificationMessages(),
            'currency.required' => 'Informe a moeda dos preços.',
            'currency.size' => 'Informe uma moeda com três letras, como BRL.',
            'cost_price.required' => 'Informe o preço de custo.',
            'cost_price.regex' => 'Informe o preço de custo com até duas casas de centavos. Exemplo: 8,50.',
            'cost_price.gt' => 'O preço de custo deve ser maior que zero.',
            'sale_price.required' => 'Informe o preço de venda.',
            'sale_price.regex' => 'Informe o preço de venda com até duas casas de centavos. Exemplo: 12,90.',
            'sale_price.gt' => 'O preço de venda deve ser maior que zero.',
        ];
    }
}
