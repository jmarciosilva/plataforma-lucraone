<x-layouts.app title="categorias" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        organização do catálogo
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-products')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('catalog.products.index') }}">produtos</x-button>
        <x-button href="{{ route('catalog.categories.create') }}">nova categoria</x-button>
    </x-slot:acoes>

    @include('products._help')

    <x-section-label>filtros</x-section-label>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('catalog.categories.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            <x-form-group nome="search" rotulo="busca" class="lg:col-span-2">
                <x-input nome="search" :valor="request('search')" placeholder="nome ou slug" />
            </x-form-group>

            <x-form-group nome="trashed" rotulo="arquivo">
                <x-select nome="trashed" :opcoes="$trashedOptions" :valor="request('trashed')" />
            </x-form-group>

            <div class="flex items-end gap-2">
                <x-button tipo="submit">filtrar</x-button>
                <x-button variante="fantasma" href="{{ route('catalog.categories.index') }}">limpar</x-button>
            </div>
        </form>
    </x-card>

    <x-section-label>lista</x-section-label>

    <x-table :cabecalhos="['categoria', 'pai', 'filhas', 'produtos', 'status', '']" :paginacao="$categories">
        @forelse ($categories as $category)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4">
                    <a href="{{ route('catalog.categories.show', $category) }}" class="font-semibold lowercase text-grafite transition-colors hover:text-sol">
                        {{ $category->name }}
                    </a>
                    <p class="font-comanda text-[0.65rem] lowercase text-aco">{{ $category->slug }}</p>
                </td>
                <td class="px-5 py-4 text-sm text-aco">{{ $category->parent?->name ?: 'raiz' }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-aco">{{ $category->children_count }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-aco">{{ $category->products_count }}</td>
                <td class="px-5 py-4">
                    <x-badge :tipo="$category->trashed() ? 'erro' : 'sucesso'">
                        {{ $category->trashed() ? 'arquivada' : 'ativa' }}
                    </x-badge>
                </td>
                <td class="px-5 py-4 text-right">
                    <x-button variante="fantasma" href="{{ route('catalog.categories.show', $category) }}">abrir</x-button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-5 py-10 text-center text-sm text-aco">nenhuma categoria encontrada.</td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
