@php
    $productOptions = $products->mapWithKeys(function ($product) {
        $preco = $product->prices->firstWhere('type', 'sale');

        return [
            $product->id => "{$product->sku} · {$product->name}" . ($preco ? ' · ' . number_format((float) $preco->amount, 2, ',', '.') : ' · sem preço'),
        ];
    })->all();

    $badgePorStatus = [
        'draft' => 'neutro',
        'pending' => 'atencao',
        'confirmed' => 'destaque',
        'shipped' => 'destaque',
        'completed' => 'sucesso',
        'cancelled' => 'erro',
    ];
@endphp

<x-layouts.app :title="$order->order_number" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        detalhe do pedido
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-sales')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('sales.orders.index') }}">voltar</x-button>
        @if ($order->customer)
            <x-button variante="secundario" href="{{ route('sales.customers.show', $order->customer) }}">cliente</x-button>
        @endif
    </x-slot:acoes>

    @include('orders._help')

    <x-section-label>resumo</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi rotulo="subtotal" :valor="number_format((float) $order->subtotal, 2, ',', '.')" nota="soma dos itens" />
        <x-kpi rotulo="desconto" :valor="number_format((float) $order->discount, 2, ',', '.')" nota="abatido do subtotal" />
        <x-kpi rotulo="total" :valor="number_format((float) $order->total, 2, ',', '.')" :nota="$order->currency" />
        <x-kpi rotulo="itens" :valor="$order->items->count()" nota="produtos no pedido" />
    </div>

    <x-section-label class="mt-8">dados</x-section-label>

    <x-card>
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-xs lowercase text-aco">situação</dt>
                <dd class="mt-1">
                    <x-badge :tipo="$badgePorStatus[$order->status] ?? 'neutro'">
                        {{ $statusRotulos[$order->status] ?? $order->status }}
                    </x-badge>
                </dd>
            </div>
            <div>
                <dt class="text-xs lowercase text-aco">cliente</dt>
                <dd class="mt-1 text-sm font-semibold text-grafite">{{ $order->customer?->name ?: 'sem cliente' }}</dd>
            </div>
            <div>
                <dt class="text-xs lowercase text-aco">empresa</dt>
                <dd class="mt-1 text-sm text-grafite">{{ $order->company?->trade_name ?: $order->company?->legal_name }}</dd>
            </div>
            <div>
                <dt class="text-xs lowercase text-aco">registrado por</dt>
                <dd class="mt-1 text-sm text-grafite">{{ $order->user?->name ?: 'sistema' }}</dd>
            </div>
            <div>
                <dt class="text-xs lowercase text-aco">criado em</dt>
                <dd class="mt-1 text-sm text-aco">{{ $order->created_at->format('d/m/Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-xs lowercase text-aco">confirmado em</dt>
                <dd class="mt-1 text-sm text-aco">{{ $order->confirmed_at?->format('d/m/Y H:i') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs lowercase text-aco">enviado em</dt>
                <dd class="mt-1 text-sm text-aco">{{ $order->shipped_at?->format('d/m/Y H:i') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs lowercase text-aco">observações</dt>
                <dd class="mt-1 text-sm text-aco">{{ $order->notes ?: 'sem observações' }}</dd>
            </div>
        </dl>
    </x-card>

    <div class="mt-8 grid grid-cols-1 gap-4 xl:grid-cols-3">
        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">mudar situação</p>

            @if ($proximosStatus)
                <form method="POST" action="{{ route('sales.orders.status', $order) }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PUT')
                    <x-form-group nome="status" rotulo="próximo passo" obrigatorio>
                        <x-select nome="status" :opcoes="$proximosStatus" :valor="old('status')" />
                    </x-form-group>
                    <x-button tipo="submit" class="w-full">atualizar situação</x-button>
                </form>

                <p class="mt-4 text-xs text-aco">
                    confirmar reserva o estoque dos itens. enviar transforma a reserva em baixa definitiva.
                </p>
            @else
                <p class="mt-4 text-sm text-aco">este pedido chegou ao fim do fluxo e não aceita mais mudanças.</p>
            @endif
        </x-card>

        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">adicionar item</p>

            @if ($order->isEditable())
                <form method="POST" action="{{ route('sales.orders.items.store', $order) }}" class="mt-4 space-y-4">
                    @csrf
                    <x-form-group nome="product_id" rotulo="produto" obrigatorio>
                        <x-select nome="product_id" :opcoes="$productOptions" :valor="old('product_id')" vazio="selecione" />
                    </x-form-group>
                    <x-form-group nome="quantity" rotulo="quantidade" obrigatorio>
                        <x-input tipo="number" nome="quantity" step="0.001" min="0.001" :valor="old('quantity')" />
                    </x-form-group>
                    <x-form-group nome="unit_price" rotulo="valor unitário" ajuda="em branco usa o preço de venda do produto">
                        <x-input tipo="number" nome="unit_price" step="0.01" min="0" :valor="old('unit_price')" />
                    </x-form-group>
                    <x-button tipo="submit" class="w-full">adicionar item</x-button>
                </form>
            @else
                <p class="mt-4 text-sm text-aco">
                    itens só podem ser alterados em rascunho ou aguardando. este pedido está como
                    <strong class="text-grafite">{{ $statusRotulos[$order->status] ?? $order->status }}</strong>.
                </p>
            @endif
        </x-card>

        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">cancelar pedido</p>

            @if ($order->canTransitionTo('cancelled'))
                <p class="mt-4 text-sm text-aco">
                    cancelar encerra o pedido. se houver estoque reservado, ele volta para o disponível.
                </p>
                <form method="POST" action="{{ route('sales.orders.destroy', $order) }}" class="mt-4">
                    @csrf
                    @method('DELETE')
                    <x-button variante="perigo" tipo="submit" class="w-full">cancelar pedido</x-button>
                </form>
            @else
                <p class="mt-4 text-sm text-aco">este pedido não pode mais ser cancelado.</p>
            @endif
        </x-card>
    </div>

    <x-section-label class="mt-8">itens</x-section-label>

    <x-table :cabecalhos="['produto', 'sku', 'quantidade', 'valor unitário', 'total', '']">
        @forelse ($order->items as $item)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4 text-sm font-semibold lowercase text-grafite">{{ $item->name }}</td>
                <td class="px-5 py-4 font-comanda text-[0.7rem] uppercase tracking-wider text-aco">{{ $item->sku }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-grafite">{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-aco">{{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-grafite">{{ number_format((float) $item->total, 2, ',', '.') }}</td>
                <td class="px-5 py-4 text-right">
                    @if ($order->isEditable())
                        <form method="POST" action="{{ route('sales.orders.items.destroy', [$order, $item]) }}">
                            @csrf
                            @method('DELETE')
                            <x-button variante="fantasma" tipo="submit">remover</x-button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-5 py-10 text-center text-sm text-aco">nenhum item no pedido ainda.</td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
