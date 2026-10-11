@php
    $address = $address ?? new \App\Modules\Companies\Domain\Models\Address(['country' => 'BR']);
    $prefix = $prefix ?? 'address.';
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <x-form-group nome="{{ $prefix }}street" rotulo="logradouro" class="sm:col-span-2">
        <x-input nome="{{ $prefix }}street" :valor="old($prefix . 'street', $address->street)" />
    </x-form-group>

    <x-form-group nome="{{ $prefix }}number" rotulo="número">
        <x-input nome="{{ $prefix }}number" :valor="old($prefix . 'number', $address->number)" />
    </x-form-group>

    <x-form-group nome="{{ $prefix }}complement" rotulo="complemento">
        <x-input nome="{{ $prefix }}complement" :valor="old($prefix . 'complement', $address->complement)" />
    </x-form-group>

    <x-form-group nome="{{ $prefix }}district" rotulo="bairro">
        <x-input nome="{{ $prefix }}district" :valor="old($prefix . 'district', $address->district)" />
    </x-form-group>

    <x-form-group nome="{{ $prefix }}city" rotulo="cidade">
        <x-input nome="{{ $prefix }}city" :valor="old($prefix . 'city', $address->city)" />
    </x-form-group>

    <x-form-group nome="{{ $prefix }}state" rotulo="uf">
        <x-input nome="{{ $prefix }}state" :valor="old($prefix . 'state', $address->state)" maxlength="2" />
    </x-form-group>

    <x-form-group nome="{{ $prefix }}postal_code" rotulo="cep">
        <x-input nome="{{ $prefix }}postal_code" :valor="old($prefix . 'postal_code', $address->postal_code)" />
    </x-form-group>

    <x-form-group nome="{{ $prefix }}country" rotulo="país">
        <x-input nome="{{ $prefix }}country" :valor="old($prefix . 'country', $address->country ?? 'BR')" maxlength="2" />
    </x-form-group>
</div>
