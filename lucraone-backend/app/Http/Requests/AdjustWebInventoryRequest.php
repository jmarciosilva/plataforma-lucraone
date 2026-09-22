<?php

namespace App\Http\Requests;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Domain\Models\ProductPackage;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdjustWebInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            ? Gate::forUser($this->user())->allows('create', Inventory::class)
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
            // Com embalagem, quantity é o número de embalagens, e não a quantidade base.
            'quantity' => $this->filled('package_id')
                ? ['required', 'integer', 'min:1', 'max:100000']
                : ['required', 'numeric', 'min:0.001'],
            'reason' => ['nullable', 'string', 'max:255'],
            'package_id' => [
                'nullable',
                'string',
                // Ajuste absoluto, reserva e liberação continuam na unidade base.
                Rule::prohibitedIf(! in_array($this->input('type'), [InventoryMovement::TYPE_IN, InventoryMovement::TYPE_OUT], true)),
                Rule::exists(ProductPackage::class, 'id')
                    ->where('tenant_id', $context->id())
                    ->where('product_id', $this->input('product_id')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'package_id.prohibited' => 'embalagem só pode ser usada em entrada ou saída, não em ajuste absoluto, reserva ou liberação.',
            'package_id.exists' => 'a embalagem não pertence a este produto.',
            ...($this->filled('package_id') ? [
                'quantity.integer' => 'a quantidade de embalagens deve ser inteira e maior ou igual a 1.',
                'quantity.min' => 'a quantidade de embalagens deve ser inteira e maior ou igual a 1.',
            ] : []),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $product = Product::query()->find($this->input('product_id'));

            if ($product && $this->filled('company_id') && $product->company_id !== $this->input('company_id')) {
                $validator->errors()->add('company_id', 'o produto informado não pertence a esta empresa.');
            }

            // Defensivo: a embalagem pode ter sido criada antes de a unidade do produto mudar.
            if ($product && $this->filled('package_id') && $product->unit !== 'UN') {
                $validator->errors()->add('package_id', 'embalagem indisponível para produto vendido em '.$product->unit.'.');
            }
        });
    }
}
