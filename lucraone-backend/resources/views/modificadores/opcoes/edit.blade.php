<x-layouts.app title="editar opção" :tenantNome="$tenantNome ?? null" :breadcrumbs="$breadcrumbs ?? []">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-atencao"></span>
        editar opção <strong>{{ $modifier->name }}</strong> em <strong>{{ $group->name }}</strong>
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('produtos.modificadores.index', $product) }}">voltar</x-button>
    </x-slot:acoes>

    <x-card>
        <form method="POST" action="{{ route('produtos.modificadores.opcoes.update', [$product, $group, $modifier]) }}" class="space-y-6">
            @csrf @method('PUT')

            <x-form-group nome="name" rotulo="nome da opção" obrigatorio>
                <x-input nome="name" :valor="old('name', $modifier->name)" autofocus />
            </x-form-group>

            @if ($group->type !== 'REMOVAL')
                <x-form-group nome="price_delta" rotulo="variação de preço" ajuda="valor adicionado ao preço base">
                    <x-input tipo="number" nome="price_delta" step="0.01" :valor="old('price_delta', $modifier->price_delta)" />
                </x-form-group>
            @else
                <p class="text-sm text-aco">grupo do tipo remoção: price_delta fixado em zero.</p>
                <input type="hidden" name="price_delta" value="0.00">
            @endif

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <x-form-group nome="position" rotulo="posição">
                    <x-input tipo="number" nome="position" min="0" :valor="old('position', $modifier->position)" />
                </x-form-group>

                <x-form-group nome="available" rotulo="disponibilidade">
                    <x-select nome="available" :opcoes="['1' => 'disponível', '0' => 'indisponível']" :valor="old('available', $modifier->available ? '1' : '0')" />
                </x-form-group>
            </div>

            <div class="flex justify-between">
                <form method="POST" action="{{ route('produtos.modificadores.opcoes.destroy', [$product, $group, $modifier]) }}" onsubmit="return confirm('Remover opção {{ addslashes($modifier->name) }}?')">
                    @csrf @method('DELETE')
                    <x-button tipo="submit" variante="perigo">remover opção</x-button>
                </form>

                <div class="flex gap-2">
                    <x-button variante="secundario" href="{{ route('produtos.modificadores.index', $product) }}">cancelar</x-button>
                    <x-button tipo="submit">salvar</x-button>
                </div>
            </div>
        </form>
    </x-card>
</x-layouts.app>
