<x-layouts.app title="terminais pdv" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-sol"></span>
        cadastrar novo terminal
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('terminals.index') }}">voltar</x-button>
    </x-slot:acoes>

    <x-section-label>dados do terminal</x-section-label>

    <x-card>
        <form method="POST" action="{{ route('terminals.store') }}" class="space-y-6">
            @csrf

            <x-form-group nome="branch_id" rotulo="filial" obrigatorio>
                <x-select
                    nome="branch_id"
                    :opcoes="$filiais->mapWithKeys(fn ($f) => [$f->id => $f->name.($f->company ? ' · '.$f->company->legal_name : '')])->toArray()"
                    :valor="old('branch_id')"
                    vazio="selecione a filial"
                />
                <x-form-hint>O terminal ficará vinculado à filial escolhida.</x-form-hint>
            </x-form-group>

            <x-form-group nome="name" rotulo="nome administrativo" obrigatorio>
                <x-input nome="name" :valor="old('name')" placeholder="ex: caixa 01, pdv recepção" autocomplete="off" />
                <x-form-hint>Nome para identificar este caixa no painel. Mín. 2, máx. 255 caracteres.</x-form-hint>
            </x-form-group>

            <div class="flex items-center justify-end gap-3 pt-2">
                <x-button variante="secundario" href="{{ route('terminals.index') }}">cancelar</x-button>
                <x-button tipo="submit">cadastrar terminal</x-button>
            </div>
        </form>
    </x-card>

    <x-card class="mt-4">
        <p class="text-sm font-semibold lowercase text-grafite">o que acontece a seguir</p>
        <ul class="mt-3 space-y-1.5 text-sm text-aco list-inside list-disc">
            <li>O terminal é criado no estado <span class="font-semibold lowercase text-grafite">aguardando pareamento</span>.</li>
            <li>Na tela de detalhe, gere um código de pareamento e informe ao caixa.</li>
            <li>O aplicativo LucraOne PDV usa o código para parear e se torna <span class="font-semibold lowercase text-grafite">ativo</span>.</li>
        </ul>
    </x-card>
</x-layouts.app>
