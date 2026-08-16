@php
    $companyOptions = $companies->mapWithKeys(fn ($company) => [
        $company->id => $company->trade_name ?: $company->legal_name,
    ])->all();
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-form-group nome="name" rotulo="nome" obrigatorio>
            <x-input nome="name" :valor="$customer->name" placeholder="nome do cliente" />
        </x-form-group>

        <x-form-group nome="company_id" rotulo="empresa" obrigatorio>
            <x-select nome="company_id" :opcoes="$companyOptions" :valor="$customer->company_id" vazio="selecione" />
        </x-form-group>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-form-group nome="email" rotulo="e-mail">
            <x-input tipo="email" nome="email" :valor="$customer->email" />
        </x-form-group>

        <x-form-group nome="phone" rotulo="telefone">
            <x-input nome="phone" :valor="$customer->phone" />
        </x-form-group>

        <x-form-group nome="document" rotulo="documento" ajuda="cpf ou cnpj">
            <x-input nome="document" :valor="$customer->document" />
        </x-form-group>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-form-group nome="status" rotulo="situação" obrigatorio>
            <x-select nome="status" :opcoes="$statusOptions" :valor="$customer->status" />
        </x-form-group>
    </div>

    <x-form-group nome="notes" rotulo="observações">
        <x-input tipo="textarea" nome="notes" :valor="$customer->notes" placeholder="preferências, histórico, combinados..." />
    </x-form-group>
</div>
