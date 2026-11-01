<x-layouts.app title="editar grupo" :tenantNome="$tenantNome ?? null" :breadcrumbs="$breadcrumbs ?? []">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-atencao"></span>
        editar grupo <strong>{{ $group->name }}</strong>
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('produtos.modificadores.index', $product) }}">voltar</x-button>
    </x-slot:acoes>

    <x-card>
        <form method="POST" action="{{ route('produtos.modificadores.update', [$product, $group]) }}" class="space-y-6">
            @csrf @method('PUT')

            <x-form-group nome="name" rotulo="nome do grupo" obrigatorio>
                <x-input nome="name" :valor="old('name', $group->name)" autofocus />
            </x-form-group>

            <x-form-group nome="type" rotulo="tipo do grupo" obrigatorio>
                <x-select nome="type" :opcoes="$typeLabels" :valor="old('type', $group->type)" />
            </x-form-group>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <x-form-group nome="required" rotulo="obrigatório">
                    <x-select nome="required" :opcoes="['0' => 'não', '1' => 'sim']" :valor="old('required', $group->required ? '1' : '0')" />
                </x-form-group>

                <x-form-group nome="min_selections" rotulo="seleção mínima">
                    <x-input tipo="number" nome="min_selections" min="0" max="255" :valor="old('min_selections', $group->min_selections)" />
                </x-form-group>

                <x-form-group nome="max_selections" rotulo="seleção máxima" ajuda="vazio = ilimitado">
                    <x-input tipo="number" nome="max_selections" min="1" max="255" :valor="old('max_selections', $group->max_selections)" />
                </x-form-group>
            </div>

            <x-form-group nome="position" rotulo="posição">
                <x-input tipo="number" nome="position" min="0" :valor="old('position', $group->position)" />
            </x-form-group>

            <div class="flex justify-between">
                <form method="POST" action="{{ route('produtos.modificadores.destroy', [$product, $group]) }}" onsubmit="return confirm('Remover grupo {{ addslashes($group->name) }} e todas as opções?')">
                    @csrf @method('DELETE')
                    <x-button tipo="submit" variante="perigo">remover grupo</x-button>
                </form>

                <div class="flex gap-2">
                    <x-button variante="secundario" href="{{ route('produtos.modificadores.index', $product) }}">cancelar</x-button>
                    <x-button tipo="submit">salvar</x-button>
                </div>
            </div>
        </form>
    </x-card>
</x-layouts.app>
