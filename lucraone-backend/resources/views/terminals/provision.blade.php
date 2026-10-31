<x-layouts.app title="terminais pdv" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-sol"></span>
        cadastrar terminal pdv
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('terminals.index') }}">voltar</x-button>
    </x-slot:acoes>

    @if ($errors->any())
        <x-alert tipo="erro" class="mb-6">{{ $errors->first() }}</x-alert>
    @endif

    <x-section-label>dados do terminal</x-section-label>

    <x-card>
        <form method="POST" action="{{ route('terminals.provision.store') }}" class="space-y-6">
            @csrf

            <x-form-group nome="installation_id" rotulo="id de instalação" obrigatorio>
                <x-input nome="installation_id" :valor="old('installation_id')" placeholder="ex: 3f2504e0-4f89-41d3-9a0c-0305e82c3301" autocomplete="off" spellcheck="false" />
                <x-form-hint>UUID gerado pelo PDV na instalação. Copie-o da tela do aplicativo e cole aqui.</x-form-hint>
            </x-form-group>

            <x-form-group nome="name" rotulo="nome do terminal" obrigatorio>
                <x-input nome="name" :valor="old('name')" placeholder="ex: caixa 001, pdv recepção" autocomplete="off" />
                <x-form-hint>Nome para identificar este caixa no painel. Mín. 2, máx. 255 caracteres.</x-form-hint>
            </x-form-group>

            <x-form-group nome="branch_id" rotulo="filial" obrigatorio>
                <x-select
                    nome="branch_id"
                    :opcoes="$filiais->mapWithKeys(fn ($f) => [$f->id => $f->name.($f->company ? ' · '.$f->company->legal_name : '')])->toArray()"
                    :valor="old('branch_id')"
                    vazio="selecione a filial"
                />
                <x-form-hint>O terminal ficará vinculado à filial escolhida.</x-form-hint>
            </x-form-group>

            <div class="flex items-center justify-end gap-3 pt-2">
                <x-button variante="secundario" href="{{ route('terminals.index') }}">cancelar</x-button>
                <x-button tipo="submit">cadastrar</x-button>
            </div>
        </form>
    </x-card>

    <x-card class="mt-4">
        <p class="text-sm font-semibold lowercase text-grafite">o que acontece a seguir</p>
        <ul class="mt-3 space-y-1.5 text-sm text-aco list-inside list-disc">
            <li>O terminal é criado já no estado <span class="font-semibold lowercase text-grafite">ativo</span>, vinculado à filial.</li>
            <li>Uma credencial de máquina (token) é gerada e exibida <span class="font-semibold text-grafite">uma única vez</span> na tela seguinte.</li>
            <li>Insira esse token na configuração do PDV. A partir daí o aplicativo autentica-se sozinho — sem pareamento.</li>
        </ul>
    </x-card>
</x-layouts.app>
