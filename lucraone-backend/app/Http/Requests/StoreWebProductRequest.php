<?php

namespace App\Http\Requests;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Products\Domain\Models\Category;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Http\Rules\BarcodeAvailable;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreWebProductRequest extends FormRequest
{
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
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'sku')->where('tenant_id', $context->id()),
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
            'status' => ['required', Rule::in(['active', 'inactive', 'discontinued'])],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => [
                'string',
                Rule::exists(Category::class, 'id')->where('tenant_id', $context->id()),
            ],
        ];
    }
}
