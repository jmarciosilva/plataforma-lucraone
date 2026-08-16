<x-layouts.app title="estoque" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        detalhe do saldo
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-inventory')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('inventory.index') }}">voltar</x-button>
        <x-button variante="secundario" href="{{ route('catalog.products.show', $inventory->product) }}">produto</x-button>
    </x-slot:acoes>

    @include('inventory._help')

    <x-section-label>resumo</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi rotulo="em mãos" :valor="number_format((float) $inventory->quantity_on_hand, 3, ',', '.')" nota="saldo físico" />
        <x-kpi rotulo="reservado" :valor="number_format((float) $inventory->reserved, 3, ',', '.')" nota="separado para pedidos" />
        <x-kpi rotulo="disponível" :valor="number_format((float) $inventory->available, 3, ',', '.')" nota="saldo livre" />
        <x-kpi rotulo="repor em" :valor="$inventory->stockLevel ? number_format((float) $inventory->stockLevel->reorder_point, 3, ',', '.') : 'n/a'" nota="ponto de reposição" />
    </div>

    <x-section-label class="mt-8">dados</x-section-label>

    <x-card>
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-xs lowercase text-aco">produto</dt>
                <dd class="mt-1 text-sm font-semibold text-grafite">{{ $inventory->product->name }}</dd>
            </div>
            <div>
                <dt class="text-xs lowercase text-aco">sku</dt>
                <dd class="mt-1 font-comanda text-sm text-grafite">{{ $inventory->product->sku }}</dd>
            </div>
            <div>
                <dt class="text-xs lowercase text-aco">empresa</dt>
                <dd class="mt-1 text-sm text-grafite">{{ $inventory->company?->trade_name ?: $inventory->company?->legal_name }}</dd>
            </div>
            <div>
                <dt class="text-xs lowercase text-aco">categorias</dt>
                <dd class="mt-1 text-sm text-grafite">{{ $inventory->product->categories->pluck('name')->implode(', ') ?: 'sem categoria' }}</dd>
            </div>
        </dl>
    </x-card>

    <x-section-label class="mt-8">histórico</x-section-label>

    <x-table :cabecalhos="['tipo', 'quantidade', 'antes', 'depois', 'motivo', 'usuário', 'quando']" :paginacao="$movements">
        @forelse ($movements as $movement)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4 text-sm font-semibold lowercase text-grafite">{{ $movementTypes[$movement->type] ?? $movement->type }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-grafite">{{ number_format((float) $movement->quantity, 3, ',', '.') }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-aco">{{ number_format((float) $movement->quantity_before, 3, ',', '.') }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-grafite">{{ number_format((float) $movement->quantity_after, 3, ',', '.') }}</td>
                <td class="px-5 py-4 text-sm text-aco">{{ $movement->reason ?: 'sem motivo' }}</td>
                <td class="px-5 py-4 text-sm text-aco">{{ $movement->user?->name ?: 'sistema' }}</td>
                <td class="px-5 py-4 text-sm text-aco">{{ $movement->moved_at->format('d/m/Y H:i') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-5 py-10 text-center text-sm text-aco">nenhuma movimentação registrada.</td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
