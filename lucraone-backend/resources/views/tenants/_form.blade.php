@props([
    'tenant',
    'statusOptions',
    'planoOptions',
    'timezoneOptions',
    'localeOptions',
    'moedaOptions',
])

<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <x-form-group nome="name" rotulo="nome" obrigatorio>
        <x-input nome="name" :valor="$tenant->name" autocomplete="organization" />
    </x-form-group>

    <x-form-group nome="slug" rotulo="slug" ajuda="se ficar vazio, será gerado a partir do nome">
        <x-input nome="slug" :valor="$tenant->slug" />
    </x-form-group>

    <x-form-group nome="status" rotulo="status" obrigatorio>
        <x-select nome="status" :opcoes="$statusOptions" :valor="$tenant->status" />
    </x-form-group>

    <x-form-group nome="plan" rotulo="plano" obrigatorio>
        <x-select nome="plan" :opcoes="$planoOptions" :valor="$tenant->plan" />
    </x-form-group>

    <x-form-group nome="timezone" rotulo="timezone" obrigatorio>
        <x-select nome="timezone" :opcoes="$timezoneOptions" :valor="$tenant->timezone" />
    </x-form-group>

    <x-form-group nome="locale" rotulo="locale" obrigatorio>
        <x-select nome="locale" :opcoes="$localeOptions" :valor="$tenant->locale" />
    </x-form-group>

    <x-form-group nome="currency" rotulo="moeda" obrigatorio>
        <x-select nome="currency" :opcoes="$moedaOptions" :valor="$tenant->currency" />
    </x-form-group>
</div>
