@php
    $companyOptions = $companies->mapWithKeys(fn ($company) => [
        $company->id => $company->trade_name ?: $company->legal_name,
    ])->all();

    $customerOptions = $customers->mapWithKeys(fn ($customer) => [
        $customer->id => $customer->name,
    ])->all();

    $badgePorStatus = [
        'draft' => 'neutro',
        'pending' => 'atencao',
        'confirmed' => 'destaque',
        'shipped' => 'destaque',
        'completed' => 'sucesso',
        'cancelled' => 'erro',
    ];
@endphp

<x-layouts.app title="vendas" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        pedidos e faturamento
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-sales')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('sales.customers.index') }}">clientes</x-button>
        <x-button href="{{ route('sales.orders.create') }}">novo pedido</x-button>
    </x-slot:acoes>

    @include('orders._help')

    <x-section-label>resumo</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi rotulo="pedidos" :valor="$summary['total']" nota="no estabelecimento" />
        <x-kpi rotulo="em aberto" :valor="$summary['abertos']" nota="ainda não fechados" />
        <x-kpi rotulo="confirmados" :valor="$summary['confirmados']" nota="com estoque reservado" />
        <x-kpi rotulo="faturado" :valor="$summary['faturado']" nota="enviados e concluídos" />
    </div>

    <x-section-label class="mt-8">filtros</x-section-label>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('sales.orders.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-5">
            <x-form-group nome="search" rotulo="busca" class="lg:col-span-2">
                <x-input nome="search" :valor="request('search')" placeholder="número do pedido ou cliente" />
            </x-form-group>

            <x-form-group nome="status" rotulo="situação">
                <x-select nome="status" :opcoes="$statusRotulos" :valor="request('status')" vazio="todas" />
            </x-form-group>

            <x-form-group nome="company_id" rotulo="empresa">
                <x-select nome="company_id" :opcoes="$companyOptions" :valor="request('company_id')" vazio="todas" />
            </x-form-group>

            <div class="flex items-end gap-2">
                <x-button tipo="submit">filtrar</x-button>
                <x-button variante="fantasma" href="{{ route('sales.orders.index') }}">limpar</x-button>
            </div>
        </form>
    </x-card>

    <x-section-label>pedidos</x-section-label>

    <x-table :cabecalhos="['pedido', 'cliente', 'empresa', 'itens', 'total', 'situação', '']" :paginacao="$orders">
        @forelse ($orders as $order)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4">
                    <a href="{{ route('sales.orders.show', $order) }}" class="font-comanda text-sm font-semibold text-grafite transition-colors hover:text-sol">
                        {{ $order->order_number }}
                    </a>
                    <p class="text-[0.65rem] uppercase tracking-wider text-aco">{{ $order->created_at->format('d/m/Y H:i') }}</p>
                </td>
                <td class="px-5 py-4 text-sm text-grafite">{{ $order->customer?->name ?: 'sem cliente' }}</td>
                <td class="px-5 py-4 text-sm text-aco">{{ $order->company?->trade_name ?: $order->company?->legal_name }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-aco">{{ $order->items_count }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-grafite">{{ number_format((float) $order->total, 2, ',', '.') }}</td>
                <td class="px-5 py-4">
                    <x-badge :tipo="$badgePorStatus[$order->status] ?? 'neutro'">
                        {{ $statusRotulos[$order->status] ?? $order->status }}
                    </x-badge>
                </td>
                <td class="px-5 py-4 text-right">
                    <x-button variante="fantasma" href="{{ route('sales.orders.show', $order) }}">abrir</x-button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-5 py-10 text-center text-sm text-aco">nenhum pedido registrado ainda.</td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
