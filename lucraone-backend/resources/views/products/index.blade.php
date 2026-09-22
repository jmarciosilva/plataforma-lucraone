<x-layouts.app title="produtos" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        catálogo do estabelecimento atual
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-products')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('catalog.categories.index') }}">categorias</x-button>
        <x-button href="{{ route('catalog.products.create') }}">novo produto</x-button>
    </x-slot:acoes>

    @include('products._help')

    <x-section-label>filtros</x-section-label>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('catalog.products.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-6">
            <x-form-group nome="search" rotulo="busca" class="lg:col-span-2">
                <x-input nome="search" :valor="request('search')" placeholder="sku, código de barras ou nome" />
            </x-form-group>

            <x-form-group nome="company_id" rotulo="empresa">
                <x-select nome="company_id" :opcoes="$companies->mapWithKeys(fn ($company) => [$company->id => $company->trade_name ?: $company->legal_name])->all()" :valor="request('company_id')" vazio="todas" />
            </x-form-group>

            <x-form-group nome="status" rotulo="status">
                <x-select nome="status" :opcoes="$statusOptions" :valor="request('status')" vazio="todos" />
            </x-form-group>

            <x-form-group nome="sort" rotulo="ordem">
                <x-select nome="sort" :opcoes="$sortOptions" :valor="request('sort', 'created_desc')" />
            </x-form-group>

            <x-form-group nome="trashed" rotulo="arquivo">
                <x-select nome="trashed" :opcoes="$trashedOptions" :valor="request('trashed')" />
            </x-form-group>

            <div class="flex items-end gap-2 lg:col-span-6">
                <x-button tipo="submit">filtrar</x-button>
                <x-button variante="fantasma" href="{{ route('catalog.products.index') }}">limpar</x-button>
            </div>
        </form>
    </x-card>

    <x-section-label>lista</x-section-label>

    <x-table :cabecalhos="['produto', 'empresa', 'unidade', 'status', 'categorias', 'preços', '']" :paginacao="$products">
        @forelse ($products as $product)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4">
                    <a href="{{ route('catalog.products.show', $product) }}" class="font-semibold lowercase text-grafite transition-colors hover:text-sol">
                        {{ $product->name }}
                    </a>
                    <p class="font-comanda text-[0.65rem] uppercase tracking-wider text-aco">{{ $product->sku }}</p>
                </td>
                <td class="px-5 py-4 text-sm text-aco">{{ $product->company?->trade_name ?: $product->company?->legal_name ?: 'sem empresa' }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-aco">{{ $product->unit }}</td>
                <td class="px-5 py-4">
                    <x-badge :tipo="$product->trashed() ? 'erro' : ($product->status === 'active' ? 'sucesso' : ($product->status === 'inactive' ? 'atencao' : 'neutro'))">
                        {{ $product->trashed() ? 'arquivado' : $product->status }}
                    </x-badge>
                </td>
                <td class="px-5 py-4 font-comanda text-sm text-aco">{{ $product->categories_count }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-aco">{{ $product->prices->count() }}</td>
                <td class="px-5 py-4 text-right">
                    <x-button variante="fantasma" href="{{ route('catalog.products.show', $product) }}">abrir</x-button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-5 py-10 text-center text-sm text-aco">nenhum produto encontrado.</td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
