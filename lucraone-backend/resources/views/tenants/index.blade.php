<x-layouts.app title="tenants" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        estabelecimentos da plataforma
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button href="{{ route('tenants.create') }}">novo tenant</x-button>
    </x-slot:acoes>

    <x-section-label>filtros</x-section-label>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('tenants.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-5">
            <x-form-group nome="search" rotulo="busca" class="lg:col-span-2">
                <x-input nome="search" :valor="request('search')" placeholder="nome ou slug" />
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

            <div class="flex items-end gap-2 lg:col-span-5">
                <x-button tipo="submit">filtrar</x-button>
                <x-button variante="fantasma" href="{{ route('tenants.index') }}">limpar</x-button>
            </div>
        </form>
    </x-card>

    <x-section-label>lista</x-section-label>

    <x-table
        :cabecalhos="['nome', 'status', 'plano', 'usuários', 'empresas', 'criação', '']"
        :paginacao="$tenants"
    >
        @forelse ($tenants as $tenant)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4">
                    <a href="{{ route('tenants.show', $tenant) }}" class="font-semibold lowercase text-grafite transition-colors hover:text-sol">
                        {{ $tenant->name }}
                    </a>
                    <p class="font-comanda text-[0.65rem] uppercase tracking-wider text-aco">{{ $tenant->slug }}</p>
                </td>
                <td class="px-5 py-4">
                    <x-badge :tipo="$tenant->trashed() ? 'erro' : ($tenant->isActive() ? 'sucesso' : 'atencao')">
                        {{ $tenant->trashed() ? 'arquivado' : strtolower($tenant->status) }}
                    </x-badge>
                </td>
                <td class="px-5 py-4 text-sm text-aco">{{ $tenant->plan }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-grafite">{{ $tenant->active_users_count }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-grafite">{{ $tenant->companies_count }}</td>
                <td class="px-5 py-4 text-sm text-aco">{{ $tenant->created_at->format('d/m/Y') }}</td>
                <td class="px-5 py-4 text-right">
                    <x-button variante="fantasma" href="{{ route('tenants.show', $tenant) }}">abrir</x-button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-5 py-10 text-center text-sm text-aco">
                    nenhum tenant encontrado.
                </td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
