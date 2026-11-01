<x-layouts.app title="editar item" :tenantNome="$tenantNome ?? null" :breadcrumbs="$breadcrumbs ?? []">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-atencao"></span>
        editar <strong>{{ $item->product->name }}</strong> em <strong>{{ $section->name }}</strong>
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('cardapio.show', $section) }}">voltar</x-button>
    </x-slot:acoes>

    <x-card>
        <form method="POST" action="{{ route('cardapio.itens.update', [$section, $item]) }}" class="space-y-6">
            @csrf @method('PUT')

            <x-form-group nome="description" rotulo="descrição no cardápio" ajuda="opcional — substitui a descrição padrão do produto">
                <textarea name="description" id="description" rows="2"
                    class="w-full rounded-lg border border-linha bg-fundo px-3 py-2 text-sm text-grafite placeholder-aco/40 focus:outline-none focus:ring-2 focus:ring-primario"
                    placeholder="descrição personalizada para o cardápio">{{ old('description', $item->description) }}</textarea>
                @error('description') <p class="mt-1 text-xs text-erro">{{ $message }}</p> @enderror
            </x-form-group>

            <x-section-label>preço no cardápio</x-section-label>
            <p class="text-sm text-aco">deixe em branco para usar o preço de venda cadastrado no produto.</p>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <x-form-group nome="price_override" rotulo="preço override">
                    <x-input tipo="number" nome="price_override" step="0.01" min="0" :valor="old('price_override', $item->price_override)" placeholder="0,00" />
                </x-form-group>

                <x-form-group nome="price_currency" rotulo="moeda">
                    <x-select nome="price_currency" :opcoes="['BRL' => 'BRL — Real', 'USD' => 'USD — Dólar', 'EUR' => 'EUR — Euro']" :valor="old('price_currency', $item->price_currency ?? 'BRL')" vazio="selecione" />
                </x-form-group>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <x-form-group nome="position" rotulo="posição">
                    <x-input tipo="number" nome="position" min="0" :valor="old('position', $item->position)" />
                </x-form-group>

                <x-form-group nome="available" rotulo="disponibilidade">
                    <x-select nome="available" :opcoes="['1' => 'disponível', '0' => 'indisponível']" :valor="old('available', $item->available ? '1' : '0')" />
                </x-form-group>
            </div>

            <div class="flex justify-end gap-2">
                <x-button variante="secundario" href="{{ route('cardapio.show', $section) }}">cancelar</x-button>
                <x-button tipo="submit">salvar</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
