<x-layouts.app title="nova opção" :tenantNome="$tenantNome ?? null" :breadcrumbs="$breadcrumbs ?? []">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-atencao"></span>
        nova opção em <strong>{{ $group->name }}</strong>
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('produtos.modificadores.index', $product) }}">voltar</x-button>
    </x-slot:acoes>

    <x-card>
        <form method="POST" action="{{ route('produtos.modificadores.opcoes.store', [$product, $group]) }}" class="space-y-6">
            @csrf

            <x-form-group nome="name" rotulo="nome da opção" obrigatorio>
                <x-input nome="name" :valor="old('name')" placeholder="ex: ao ponto, sem cebola, extra queijo" autofocus />
            </x-form-group>

            @if ($group->type !== 'REMOVAL')
                <x-form-group nome="price_delta" rotulo="variação de preço" ajuda="valor adicionado ao preço base (0 = sem acréscimo)">
                    <x-input tipo="number" nome="price_delta" step="0.01" :valor="old('price_delta', '0.00')" />
                </x-form-group>
            @else
                <p class="text-sm text-aco">grupo do tipo remoção: price_delta fixado em zero.</p>
                <input type="hidden" name="price_delta" value="0.00">
            @endif

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <x-form-group nome="position" rotulo="posição" ajuda="ordem de exibição (0 = primeiro)">
                    <x-input tipo="number" nome="position" min="0" :valor="old('position', '0')" />
                </x-form-group>

                <x-form-group nome="available" rotulo="disponibilidade">
                    <x-select nome="available" :opcoes="['1' => 'disponível', '0' => 'indisponível']" :valor="old('available', '1')" />
                </x-form-group>
            </div>

            <div class="flex justify-end gap-2">
                <x-button variante="secundario" href="{{ route('produtos.modificadores.index', $product) }}">cancelar</x-button>
                <x-button tipo="submit">adicionar opção</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
