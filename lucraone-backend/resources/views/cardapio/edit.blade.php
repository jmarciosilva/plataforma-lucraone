<x-layouts.app title="editar seção" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-atencao"></span>
        editar dados da seção
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('cardapio.show', $section) }}">voltar</x-button>
    </x-slot:acoes>

    <x-card>
        <form method="POST" action="{{ route('cardapio.update', $section) }}" class="space-y-6">
            @csrf @method('PUT')

            <x-form-group nome="name" rotulo="nome da seção" obrigatorio>
                <x-input nome="name" :valor="old('name', $section->name)" autofocus />
            </x-form-group>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <x-form-group nome="position" rotulo="posição" ajuda="ordem de exibição (0 = primeiro)">
                    <x-input tipo="number" nome="position" min="0" :valor="old('position', $section->position)" />
                </x-form-group>

                <x-form-group nome="available" rotulo="disponibilidade">
                    <x-select nome="available" :opcoes="['1' => 'disponível', '0' => 'indisponível']" :valor="old('available', $section->available ? '1' : '0')" />
                </x-form-group>
            </div>

            <div class="flex justify-between">
                <form method="POST" action="{{ route('cardapio.destroy', $section) }}" onsubmit="return confirm('Remover seção {{ addslashes($section->name) }} e todos os seus itens?')">
                    @csrf @method('DELETE')
                    <x-button tipo="submit" variante="perigo">remover seção</x-button>
                </form>

                <div class="flex gap-2">
                    <x-button variante="secundario" href="{{ route('cardapio.show', $section) }}">cancelar</x-button>
                    <x-button tipo="submit">salvar</x-button>
                </div>
            </div>
        </form>
    </x-card>
</x-layouts.app>
