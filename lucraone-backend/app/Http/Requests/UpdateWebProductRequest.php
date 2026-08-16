<?php

namespace App\Http\Requests;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Products\Domain\Models\Category;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateWebProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $this->user() && $product
            ? Gate::forUser($this->user())->allows('update', $product)
            : false;
    }

    public function rules(TenantContext $context): array
    {
        $product = $this->route('product');

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
                Rule::unique('products', 'sku')
                    ->where('tenant_id', $context->id())
                    ->ignore($product?->id),
            ],
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
