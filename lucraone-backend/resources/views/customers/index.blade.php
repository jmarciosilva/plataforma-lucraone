@php
    $companyOptions = $companies->mapWithKeys(fn ($company) => [
        $company->id => $company->trade_name ?: $company->legal_name,
    ])->all();
@endphp

<x-layouts.app title="clientes" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        quem compra do estabelecimento
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-customers')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('sales.orders.index') }}">vendas</x-button>
        <x-button href="{{ route('sales.customers.create') }}">novo cliente</x-button>
    </x-slot:acoes>

    @include('customers._help')

    <x-section-label>filtros</x-section-label>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('sales.customers.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-5">
            <x-form-group nome="search" rotulo="busca" class="lg:col-span-2">
                <x-input nome="search" :valor="request('search')" placeholder="nome, e-mail ou documento" />
            </x-form-group>

            <x-form-group nome="company_id" rotulo="empresa">
                <x-select nome="company_id" :opcoes="$companyOptions" :valor="request('company_id')" vazio="todas" />
            </x-form-group>

            <x-form-group nome="status" rotulo="situação">
                <x-select nome="status" :opcoes="$statusOptions" :valor="request('status')" vazio="todas" />
            </x-form-group>

            <x-form-group nome="trashed" rotulo="arquivados">
                <x-select nome="trashed" :opcoes="$trashedOptions" :valor="request('trashed')" />
            </x-form-group>

            <div class="flex items-end gap-2 lg:col-span-5">
                <x-button tipo="submit">filtrar</x-button>
                <x-button variante="fantasma" href="{{ route('sales.customers.index') }}">limpar</x-button>
            </div>
        </form>
    </x-card>

    <x-section-label>clientes</x-section-label>

    <x-table :cabecalhos="['cliente', 'contato', 'empresa', 'pedidos', 'situação', '']" :paginacao="$customers">
        @forelse ($customers as $customer)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4">
                    <a href="{{ route('sales.customers.show', $customer) }}" class="font-semibold lowercase text-grafite transition-colors hover:text-sol">
                        {{ $customer->name }}
                    </a>
                    @if ($customer->document)
                        <p class="font-comanda text-[0.65rem] uppercase tracking-wider text-aco">{{ $customer->document }}</p>
                    @endif
                </td>
                <td class="px-5 py-4 text-sm text-aco">
                    {{ $customer->email ?: '—' }}
                    @if ($customer->phone)
                        <span class="block">{{ $customer->phone }}</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-sm text-aco">{{ $customer->company?->trade_name ?: $customer->company?->legal_name }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-grafite">{{ $customer->orders_count }}</td>
                <td class="px-5 py-4">
                    @if ($customer->trashed())
                        <x-badge tipo="neutro">arquivado</x-badge>
                    @else
                        <x-badge :tipo="$customer->status === 'active' ? 'sucesso' : 'atencao'">
                            {{ $statusOptions[$customer->status] ?? $customer->status }}
                        </x-badge>
                    @endif
                </td>
                <td class="px-5 py-4 text-right">
                    <x-button variante="fantasma" href="{{ route('sales.customers.show', $customer) }}">abrir</x-button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-5 py-10 text-center text-sm text-aco">nenhum cliente cadastrado ainda.</td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
