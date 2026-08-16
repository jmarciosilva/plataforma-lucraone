@php
    $badgePorStatus = [
        'draft' => 'neutro',
        'pending' => 'atencao',
        'confirmed' => 'destaque',
        'shipped' => 'destaque',
        'completed' => 'sucesso',
        'cancelled' => 'erro',
    ];
@endphp

<x-layouts.app :title="$customer->name" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        detalhe do cliente
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-customers')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('sales.customers.index') }}">voltar</x-button>
        @unless ($customer->trashed())
            <x-button variante="secundario" href="{{ route('sales.customers.edit', $customer) }}">editar</x-button>
        @endunless
    </x-slot:acoes>

    @include('customers._help')

    <x-section-label>dados</x-section-label>

    <x-card>
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-xs lowercase text-aco">situação</dt>
                <dd class="mt-1">
                    @if ($customer->trashed())
                        <x-badge tipo="neutro">arquivado</x-badge>
                    @else
                        <x-badge :tipo="$customer->status === 'active' ? 'sucesso' : 'atencao'">
                            {{ $customer->status === 'active' ? 'ativo' : 'inativo' }}
                        </x-badge>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs lowercase text-aco">e-mail</dt>
                <dd class="mt-1 text-sm text-grafite">{{ $customer->email ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs lowercase text-aco">telefone</dt>
                <dd class="mt-1 text-sm text-grafite">{{ $customer->phone ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs lowercase text-aco">documento</dt>
                <dd class="mt-1 font-comanda text-sm text-grafite">{{ $customer->document ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs lowercase text-aco">empresa</dt>
                <dd class="mt-1 text-sm text-grafite">{{ $customer->company?->trade_name ?: $customer->company?->legal_name }}</dd>
            </div>
            <div>
                <dt class="text-xs lowercase text-aco">cadastrado em</dt>
                <dd class="mt-1 text-sm text-aco">{{ $customer->created_at->format('d/m/Y H:i') }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs lowercase text-aco">observações</dt>
                <dd class="mt-1 text-sm text-aco">{{ $customer->notes ?: 'sem observações' }}</dd>
            </div>
        </dl>

        <div class="mt-6 flex flex-wrap gap-2 border-t border-linha pt-4">
            @if ($customer->trashed())
                <form method="POST" action="{{ route('sales.customers.restore', $customer) }}">
                    @csrf
                    <x-button tipo="submit">restaurar cliente</x-button>
                </form>
            @else
                <form method="POST" action="{{ route('sales.customers.destroy', $customer) }}">
                    @csrf
                    @method('DELETE')
                    <x-button variante="perigo" tipo="submit">arquivar cliente</x-button>
                </form>
            @endif
        </div>
    </x-card>

    <x-section-label class="mt-8">últimos pedidos</x-section-label>

    <x-table :cabecalhos="['pedido', 'situação', 'total', 'quando', '']">
        @forelse ($customer->orders as $order)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4 font-comanda text-sm font-semibold text-grafite">{{ $order->order_number }}</td>
                <td class="px-5 py-4">
                    <x-badge :tipo="$badgePorStatus[$order->status] ?? 'neutro'">
                        {{ $statusRotulos[$order->status] ?? $order->status }}
                    </x-badge>
                </td>
                <td class="px-5 py-4 font-comanda text-sm text-grafite">{{ number_format((float) $order->total, 2, ',', '.') }}</td>
                <td class="px-5 py-4 text-sm text-aco">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                <td class="px-5 py-4 text-right">
                    <x-button variante="fantasma" href="{{ route('sales.orders.show', $order) }}">abrir</x-button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="px-5 py-10 text-center text-sm text-aco">este cliente ainda não tem pedidos.</td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
