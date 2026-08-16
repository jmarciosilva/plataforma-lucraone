<x-layouts.app title="categorias" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full {{ $category->trashed() ? 'bg-brasa' : 'bg-ok' }}"></span>
        detalhe da categoria
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-products')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('catalog.categories.index') }}">voltar</x-button>
        @if (! $category->trashed())
            <x-button href="{{ route('catalog.categories.edit', $category) }}">editar</x-button>
        @endif
    </x-slot:acoes>

    @include('products._help')

    <x-section-label>resumo</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-kpi rotulo="status" :valor="$category->trashed() ? 'arquivada' : 'ativa'" nota="catálogo" />
        <x-kpi rotulo="categorias filhas" :valor="$category->children->count()" nota="subníveis diretos" />
        <x-kpi rotulo="produtos" :valor="$category->products->count()" nota="associados" />
    </div>

    <x-section-label class="mt-8">dados</x-section-label>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs lowercase text-aco">nome</dt>
                    <dd class="mt-1 text-sm font-semibold text-grafite">{{ $category->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">slug</dt>
                    <dd class="mt-1 font-comanda text-sm text-grafite">{{ $category->slug }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">categoria pai</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $category->parent?->name ?: 'raiz' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs lowercase text-aco">descrição</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $category->description ?: 'não informada' }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">ações</p>

            @if ($category->trashed())
                <form method="POST" action="{{ route('catalog.categories.restore', $category->id) }}" class="mt-4">
                    @csrf
                    <x-button tipo="submit" class="w-full">restaurar categoria</x-button>
                </form>
            @else
                <x-button variante="secundario" href="{{ route('catalog.categories.edit', $category) }}" class="mt-4 w-full">editar dados</x-button>

                <button type="button" x-data @click="$dispatch('abrir-modal', 'arquivar-category')" class="mt-2 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-brasa px-4 text-sm font-semibold lowercase text-white transition-opacity hover:opacity-90">
                    arquivar categoria
                </button>

                <x-modal nome="arquivar-category" titulo="arquivar categoria">
                    <p class="text-sm text-aco">A categoria sairá das listagens ativas. Categorias filhas ficarão sem pai e poderão ser reorganizadas depois.</p>

                    <x-slot:acoes>
                        <form method="POST" action="{{ route('catalog.categories.destroy', $category) }}">
                            @csrf
                            @method('DELETE')
                            <x-button variante="perigo" tipo="submit">confirmar arquivo</x-button>
                        </form>
                    </x-slot:acoes>
                </x-modal>
            @endif
        </x-card>
    </div>

    <x-section-label class="mt-8">produtos vinculados</x-section-label>

    <x-table :cabecalhos="['produto', 'sku', 'status', '']">
        @forelse ($category->products as $product)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4 font-semibold lowercase text-grafite">{{ $product->name }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-aco">{{ $product->sku }}</td>
                <td class="px-5 py-4 text-sm text-aco">{{ $product->status }}</td>
                <td class="px-5 py-4 text-right">
                    <x-button variante="fantasma" href="{{ route('catalog.products.show', $product) }}">abrir</x-button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="px-5 py-10 text-center text-sm text-aco">nenhum produto vinculado.</td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
