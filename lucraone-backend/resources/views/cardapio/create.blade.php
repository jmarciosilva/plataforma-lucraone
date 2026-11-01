<x-layouts.app title="nova seção" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-atencao"></span>
        cadastrar nova seção do cardápio
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('cardapio.index') }}">voltar</x-button>
    </x-slot:acoes>

    <x-card>
        <form method="POST" action="{{ route('cardapio.store') }}" class="space-y-6">
            @csrf

            <x-form-group nome="name" rotulo="nome da seção" obrigatorio>
                <x-input nome="name" :valor="old('name')" placeholder="ex: entradas, bebidas, sobremesas" autofocus />
            </x-form-group>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <x-form-group nome="position" rotulo="posição" ajuda="ordem de exibição (0 = primeiro)">
                    <x-input tipo="number" nome="position" min="0" :valor="old('position', '0')" />
                </x-form-group>

                <x-form-group nome="available" rotulo="disponibilidade">
                    <x-select nome="available" :opcoes="['1' => 'disponível', '0' => 'indisponível']" :valor="old('available', '1')" />
                </x-form-group>
            </div>

            <div class="flex justify-end gap-2">
                <x-button variante="secundario" href="{{ route('cardapio.index') }}">cancelar</x-button>
                <x-button tipo="submit">criar seção</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
