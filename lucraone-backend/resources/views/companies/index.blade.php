<x-layouts.app title="empresas" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        CNPJs e entidades jurídicas do estabelecimento atual
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-companies')">ajuda</x-button>
        <x-button href="{{ route('companies.create') }}">nova empresa</x-button>
    </x-slot:acoes>

    @include('companies._help')

    <x-section-label>filtros</x-section-label>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('companies.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            <x-form-group nome="search" rotulo="busca" class="lg:col-span-2">
                <x-input nome="search" :valor="request('search')" placeholder="razão social, fantasia ou cnpj" />
            </x-form-group>

            <x-form-group nome="status" rotulo="status">
                <x-select nome="status" :opcoes="$statusOptions" :valor="request('status')" vazio="todos" />
            </x-form-group>

            <x-form-group nome="sort" rotulo="ordem">
                <x-select nome="sort" :opcoes="$sortOptions" :valor="request('sort', 'created_desc')" />
            </x-form-group>

            <div class="flex items-end gap-2 lg:col-span-4">
                <x-button tipo="submit">filtrar</x-button>
                <x-button variante="fantasma" href="{{ route('companies.index') }}">limpar</x-button>
            </div>
        </form>
    </x-card>

    <x-section-label>lista</x-section-label>

    <x-table :cabecalhos="['empresa', 'documento', 'status', 'endereços', 'filiais', '']" :paginacao="$companies">
        @forelse ($companies as $company)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4">
                    <a href="{{ route('companies.show', $company) }}" class="font-semibold lowercase text-grafite transition-colors hover:text-sol">
                        {{ $company->trade_name ?: $company->legal_name }}
                    </a>
                    <p class="text-xs text-aco">{{ $company->legal_name }}</p>
                </td>
                <td class="px-5 py-4 text-sm text-aco">{{ $company->document ?: 'sem documento' }}</td>
                <td class="px-5 py-4">
                    <x-badge :tipo="$company->status === 'ACTIVE' ? 'sucesso' : ($company->status === 'SUSPENDED' ? 'atencao' : 'erro')">
                        {{ strtolower($company->status) }}
                    </x-badge>
                </td>
                <td class="px-5 py-4 font-comanda text-sm text-aco">{{ $company->addresses_count }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-aco">{{ $company->branches_count }}</td>
                <td class="px-5 py-4 text-right">
                    <x-button variante="fantasma" href="{{ route('companies.show', $company) }}">abrir</x-button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-5 py-10 text-center text-sm text-aco">nenhuma empresa encontrada.</td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
