<x-layouts.app title="operadores de pdv" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-sol"></span>
        editar {{ $perfil->username }}
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-pdv-operators')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('pdv-operators.show', $perfil) }}">voltar</x-button>
    </x-slot:acoes>

    @include('pdv-operators._help')

    @if ($identidadeProtegida)
        <x-alert tipo="atencao" class="mb-6">
            o nome e o e-mail desta pessoa não podem ser alterados por este estabelecimento — ela também tem acesso
            a outro. o usuário do pdv, a situação e as filiais continuam editáveis aqui.
        </x-alert>
    @endif

    <x-section-label>dados</x-section-label>

    <x-card>
        <form method="POST" action="{{ route('pdv-operators.update', $perfil) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <x-form-group nome="name" rotulo="nome completo" obrigatorio>
                    <x-input nome="name" :valor="$perfil->user?->name" autocomplete="name" :readonly="$identidadeProtegida" />
                </x-form-group>

                <x-form-group nome="email" rotulo="e-mail" ajuda="opcional.">
                    <x-input tipo="email" nome="email" :valor="$perfil->user?->email" autocomplete="email" :readonly="$identidadeProtegida" />
                </x-form-group>

                <x-form-group nome="username" rotulo="usuário do pdv" obrigatorio ajuda="é o que a pessoa digita no caixa.">
                    <x-input nome="username" :valor="$perfil->username" autocomplete="off" />
                </x-form-group>

                <x-form-group nome="status" rotulo="situação" obrigatorio>
                    <x-select nome="status" :opcoes="$statusOptions" :valor="$perfil->status" />
                </x-form-group>
            </div>

            @include('pdv-operators._filiais', ['filiais' => $filiais, 'filiaisSelecionadas' => $filiaisSelecionadas])

            <div class="flex justify-end gap-2">
                <x-button variante="fantasma" href="{{ route('pdv-operators.show', $perfil) }}">cancelar</x-button>
                <x-button tipo="submit">salvar alterações</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
