@php
    $address = $address ?? new \App\Modules\Companies\Domain\Models\Address(['country' => 'BR']);
    $prefix = $prefix ?? '';
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <x-form-group nome="{{ $prefix }}street" rotulo="logradouro" obrigatorio class="sm:col-span-2">
        <x-input nome="street" :valor="$address->street" />
    </x-form-group>

    <x-form-group nome="{{ $prefix }}number" rotulo="número" obrigatorio>
        <x-input nome="number" :valor="$address->number" />
    </x-form-group>

    <x-form-group nome="{{ $prefix }}complement" rotulo="complemento">
        <x-input nome="complement" :valor="$address->complement" />
    </x-form-group>

    <x-form-group nome="{{ $prefix }}district" rotulo="bairro" obrigatorio>
        <x-input nome="district" :valor="$address->district" />
    </x-form-group>

    <x-form-group nome="{{ $prefix }}city" rotulo="cidade" obrigatorio>
        <x-input nome="city" :valor="$address->city" />
    </x-form-group>

    <x-form-group nome="{{ $prefix }}state" rotulo="uf" obrigatorio>
        <x-input nome="state" :valor="$address->state" maxlength="2" />
    </x-form-group>

    <x-form-group nome="{{ $prefix }}postal_code" rotulo="cep" obrigatorio>
        <x-input nome="postal_code" :valor="$address->postal_code" />
    </x-form-group>

    <x-form-group nome="{{ $prefix }}country" rotulo="país" obrigatorio>
        <x-input nome="country" :valor="$address->country ?? 'BR'" maxlength="2" />
    </x-form-group>

    <label class="flex min-h-11 items-center gap-3 rounded-xl border border-linha bg-white px-3 text-sm text-grafite sm:col-span-2">
        <input type="checkbox" name="is_primary" value="1" class="h-4 w-4 rounded border-linha text-sol focus:ring-sol/30" @checked(old('is_primary', $address->is_primary))>
        endereço principal
    </label>
</div>
