<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <x-form-group nome="legal_name" rotulo="razão social" obrigatorio>
        <x-input nome="legal_name" :valor="$company->legal_name" />
    </x-form-group>

    <x-form-group nome="trade_name" rotulo="nome fantasia">
        <x-input nome="trade_name" :valor="$company->trade_name" />
    </x-form-group>

    <x-form-group nome="document" rotulo="cnpj" ajuda="somente uma empresa com o mesmo documento por tenant">
        <x-input nome="document" :valor="$company->document" placeholder="00.000.000/0000-00" />
    </x-form-group>

    <x-form-group nome="status" rotulo="status" obrigatorio>
        <x-select nome="status" :opcoes="$statusOptions" :valor="$company->status" />
    </x-form-group>

    <x-form-group nome="state_registration" rotulo="inscrição estadual">
        <x-input nome="state_registration" :valor="$company->state_registration" />
    </x-form-group>

    <x-form-group nome="municipal_registration" rotulo="inscrição municipal">
        <x-input nome="municipal_registration" :valor="$company->municipal_registration" />
    </x-form-group>

    <x-form-group nome="email" rotulo="email">
        <x-input tipo="email" nome="email" :valor="$company->email" />
    </x-form-group>

    <x-form-group nome="phone" rotulo="telefone">
        <x-input nome="phone" :valor="$company->phone" />
    </x-form-group>
</div>
