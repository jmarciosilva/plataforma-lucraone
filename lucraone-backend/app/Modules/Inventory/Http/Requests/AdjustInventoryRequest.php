<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustInventoryRequest extends FormRequest
{
    public function rules(TenantContext $context): array
    {
        return [
            'company_id' => [
                'required',
                'string',
                Rule::exists(Company::class, 'id')->where('tenant_id', $context->id()),
            ],
            'type' => [
                'required',
                Rule::in([
                    InventoryMovement::TYPE_IN,
                    InventoryMovement::TYPE_OUT,
                    InventoryMovement::TYPE_ADJUSTMENT,
                    InventoryMovement::TYPE_RESERVATION,
                    InventoryMovement::TYPE_RELEASE,
                ]),
            ],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['nullable', 'string', 'max:255'],
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
