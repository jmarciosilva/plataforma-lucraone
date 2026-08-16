<x-layouts.app title="permissões" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        papéis e permissões do tenant atual
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-roles')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('permissions.index') }}">catálogo</x-button>
    </x-slot:acoes>

    @include('roles._help')

    <x-section-label>resumo</x-section-label>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-kpi rotulo="papéis" :valor="$roles->total()" nota="tenant atual" />
        <x-kpi rotulo="permissões" :valor="$permissionsCount" nota="catálogo técnico" />
        <x-kpi rotulo="página" :valor="$roles->currentPage()" nota="listagem" />
    </div>

    <x-section-label>filtros</x-section-label>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('roles.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <x-form-group nome="search" rotulo="busca" class="lg:col-span-2">
                <x-input nome="search" :valor="request('search')" placeholder="nome ou descrição" />
            </x-form-group>

            <div class="flex items-end gap-2">
                <x-button tipo="submit">filtrar</x-button>
                <x-button variante="fantasma" href="{{ route('roles.index') }}">limpar</x-button>
            </div>
        </form>
    </x-card>

    <x-section-label>papéis</x-section-label>

    <x-table :cabecalhos="['papel', 'descrição', 'permissões', 'usuários', '']" :paginacao="$roles">
        @forelse ($roles as $role)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4">
                    <a href="{{ route('roles.show', $role) }}" class="font-semibold lowercase text-grafite transition-colors hover:text-sol">
                        {{ $role->name }}
                    </a>
                    <p class="font-comanda text-[0.65rem] uppercase tracking-wider text-aco">{{ $role->id }}</p>
                </td>
                <td class="px-5 py-4 text-sm text-aco">{{ $role->description ?: 'sem descrição' }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-aco">{{ $role->permissions_count }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-aco">{{ $role->users_count }}</td>
                <td class="px-5 py-4 text-right">
                    <x-button variante="fantasma" href="{{ route('roles.show', $role) }}">abrir</x-button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="px-5 py-10 text-center text-sm text-aco">nenhum papel encontrado.</td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
