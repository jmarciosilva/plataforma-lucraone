<?php

namespace App\Http\Requests;

use App\Modules\Products\Http\Rules\BarcodeAvailable;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StoreWebProductPackageRequest extends FormRequest
{
    /**
     * Cadastrar embalagem é alterar o produto: vale a mesma permissão.
     */
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $this->user() && $product
            ? Gate::forUser($this->user())->allows('update', $product)
            : false;
    }

    public function rules(TenantContext $context): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:14', new BarcodeAvailable($context->id())],
            // Fator 1 seria código alternativo da mesma unidade — outro conceito.
            'factor' => ['required', 'integer', 'min:2', 'max:100000'],
        ];
    }

    /**
     * Nesta primeira versão, embalagem só existe para produto vendido por unidade.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->route('product')?->unit !== 'UN') {
                    $validator->errors()->add('unit', 'embalagens comerciais estão disponíveis apenas para produtos com unidade UN.');
                }
            },
        ];
    }
}
