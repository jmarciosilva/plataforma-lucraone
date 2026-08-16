@php
    $companyOptions = $companies->mapWithKeys(fn ($company) => [
        $company->id => $company->trade_name ?: $company->legal_name,
    ])->all();

    $productOptions = $products->mapWithKeys(fn ($product) => [
        $product->id => "{$product->sku} · {$product->name}",
    ])->all();

    $categoryOptions = $categories->mapWithKeys(fn ($category) => [
        $category->id => $category->name,
    ])->all();
@endphp

<x-layouts.app title="estoque" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        posição e movimentações
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-inventory')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('catalog.products.index') }}">produtos</x-button>
    </x-slot:acoes>

    @include('inventory._help')

    <x-section-label>resumo</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-kpi rotulo="itens" :valor="$summary['items']" nota="produtos com estoque" />
        <x-kpi rotulo="em mãos" :valor="$summary['on_hand']" nota="saldo físico" />
        <x-kpi rotulo="disponível" :valor="$summary['available']" nota="em mãos menos reservas" />
        <x-kpi rotulo="baixo estoque" :valor="$summary['low']" nota="no ponto de reposição" />
        <x-kpi rotulo="excesso" :valor="$summary['over']" nota="acima do máximo" />
    </div>

    <x-section-label class="mt-8">filtros</x-section-label>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('inventory.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-6">
            <x-form-group nome="search" rotulo="busca" class="lg:col-span-2">
                <x-input nome="search" :valor="request('search')" placeholder="sku ou produto" />
            </x-form-group>

            <x-form-group nome="filter_company_id" rotulo="empresa">
                <x-select nome="filter_company_id" :opcoes="$companyOptions" :valor="request('filter_company_id')" vazio="todas" />
            </x-form-group>

            <x-form-group nome="filter_category_id" rotulo="categoria">
                <x-select nome="filter_category_id" :opcoes="$categoryOptions" :valor="request('filter_category_id')" vazio="todas" />
            </x-form-group>

            <x-form-group nome="filter_status" rotulo="situação">
                <x-select nome="filter_status" :opcoes="$statusOptions" :valor="request('filter_status', request('status'))" />
            </x-form-group>

            <div class="flex items-end gap-2">
                <x-button tipo="submit">filtrar</x-button>
                <x-button variante="fantasma" href="{{ route('inventory.index') }}">limpar</x-button>
            </div>
        </form>
    </x-card>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">movimentar estoque</p>
            <form method="POST" action="{{ route('inventory.adjust') }}" class="mt-4 space-y-4">
                @csrf
                <x-form-group nome="product_id" rotulo="produto" obrigatorio>
                    <x-select nome="product_id" :opcoes="$productOptions" :valor="old('product_id')" vazio="selecione" />
                </x-form-group>
                <x-form-group nome="company_id" rotulo="empresa" obrigatorio>
                    <x-select nome="company_id" :opcoes="$companyOptions" :valor="old('company_id')" vazio="selecione" />
                </x-form-group>
                <x-form-group nome="type" rotulo="tipo" obrigatorio>
                    <x-select nome="type" :opcoes="$movementTypes" :valor="old('type', 'in')" />
                </x-form-group>
                <x-form-group nome="quantity" rotulo="quantidade" obrigatorio>
                    <x-input tipo="number" nome="quantity" step="0.001" min="0.001" />
                </x-form-group>
                <x-form-group nome="reason" rotulo="motivo">
                    <x-input nome="reason" placeholder="compra, perda, inventário..." />
                </x-form-group>
                <x-button tipo="submit" class="w-full">registrar movimento</x-button>
            </form>
        </x-card>

        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">níveis de reposição</p>
            <form method="POST" action="{{ route('inventory.stock-levels.store') }}" class="mt-4 space-y-4">
                @csrf
                <x-form-group nome="product_id" rotulo="produto" obrigatorio>
                    <x-select nome="product_id" :opcoes="$productOptions" :valor="old('product_id')" vazio="selecione" />
                </x-form-group>
                <x-form-group nome="company_id" rotulo="empresa" obrigatorio>
                    <x-select nome="company_id" :opcoes="$companyOptions" :valor="old('company_id')" vazio="selecione" />
                </x-form-group>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-form-group nome="min_qty" rotulo="mínimo" obrigatorio>
                        <x-input tipo="number" nome="min_qty" step="0.001" min="0" />
                    </x-form-group>
                    <x-form-group nome="reorder_point" rotulo="repor em" obrigatorio>
                        <x-input tipo="number" nome="reorder_point" step="0.001" min="0" />
                    </x-form-group>
                    <x-form-group nome="max_qty" rotulo="máximo">
                        <x-input tipo="number" nome="max_qty" step="0.001" min="0" />
                    </x-form-group>
                </div>
                <x-button tipo="submit" class="w-full">salvar níveis</x-button>
            </form>
        </x-card>

        <div class="xl:col-span-3">
            <x-section-label>posição</x-section-label>

            <x-table :cabecalhos="['produto', 'empresa', 'em mãos', 'reservado', 'disponível', 'situação', '']" :paginacao="$inventory">
                @forelse ($inventory as $item)
                    @php
                        $low = $item->stockLevel && (float) $item->quantity_on_hand <= (float) $item->stockLevel->reorder_point;
                        $over = $item->stockLevel?->max_qty !== null && (float) $item->quantity_on_hand > (float) $item->stockLevel->max_qty;
                    @endphp
                    <tr class="border-b border-linha last:border-0">
                        <td class="px-5 py-4">
                            <a href="{{ route('inventory.show', $item) }}" class="font-semibold lowercase text-grafite transition-colors hover:text-sol">
                                {{ $item->product->name }}
                            </a>
                            <p class="font-comanda text-[0.65rem] uppercase tracking-wider text-aco">{{ $item->product->sku }}</p>
                        </td>
                        <td class="px-5 py-4 text-sm text-aco">{{ $item->company?->trade_name ?: $item->company?->legal_name }}</td>
                        <td class="px-5 py-4 font-comanda text-sm text-grafite">{{ number_format((float) $item->quantity_on_hand, 3, ',', '.') }}</td>
                        <td class="px-5 py-4 font-comanda text-sm text-aco">{{ number_format((float) $item->reserved, 3, ',', '.') }}</td>
                        <td class="px-5 py-4 font-comanda text-sm text-grafite">{{ number_format((float) $item->available, 3, ',', '.') }}</td>
                        <td class="px-5 py-4">
                            <x-badge :tipo="$low ? 'atencao' : ($over ? 'erro' : 'sucesso')">
                                {{ $low ? 'baixo' : ($over ? 'excesso' : 'ok') }}
                            </x-badge>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <x-button variante="fantasma" href="{{ route('inventory.show', $item) }}">abrir</x-button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-10 text-center text-sm text-aco">nenhum estoque movimentado ainda.</td>
                    </tr>
                @endforelse
            </x-table>
        </div>
    </div>
</x-layouts.app>
