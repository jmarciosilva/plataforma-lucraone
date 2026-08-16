<?php

namespace App\Http\Requests;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Inventory\Domain\Models\StockLevel;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreWebStockLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            ? Gate::forUser($this->user())->allows('create', StockLevel::class)
            : false;
    }

    public function rules(TenantContext $context): array
    {
        return [
            'product_id' => [
                'required',
                'string',
                Rule::exists(Product::class, 'id')->where('tenant_id', $context->id()),
            ],
            'company_id' => [
                'required',
                'string',
                Rule::exists(Company::class, 'id')->where('tenant_id', $context->id()),
            ],
            'min_qty' => ['required', 'numeric', 'min:0'],
            'max_qty' => ['nullable', 'numeric', 'gte:min_qty'],
            'reorder_point' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $product = Product::query()->find($this->input('product_id'));

            if ($product && $this->filled('company_id') && $product->company_id !== $this->input('company_id')) {
                $validator->errors()->add('company_id', 'o produto informado não pertence a esta empresa.');
            }
        });
    }
}
