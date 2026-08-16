<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockLevelRequest extends FormRequest
{
    public function rules(TenantContext $context): array
    {
        return [
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
            $product = Product::query()->find($this->route('product_id'));

            if ($product && $this->filled('company_id') && $product->company_id !== $this->input('company_id')) {
                $validator->errors()->add('company_id', 'o produto informado não pertence a esta empresa.');
            }
        });
    }
}
