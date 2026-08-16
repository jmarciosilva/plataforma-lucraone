<x-layouts.app title="permissões" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        catálogo técnico de permissões
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-roles')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('roles.index') }}">papéis</x-button>
    </x-slot:acoes>

    @include('roles._help')

    <x-section-label>filtros</x-section-label>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('permissions.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <x-form-group nome="search" rotulo="busca" class="lg:col-span-2">
                <x-input nome="search" :valor="request('search')" placeholder="nome ou descrição" />
            </x-form-group>

            <div class="flex items-end gap-2">
                <x-button tipo="submit">filtrar</x-button>
                <x-button variante="fantasma" href="{{ route('permissions.index') }}">limpar</x-button>
            </div>
        </form>
    </x-card>

    <x-section-label>catálogo</x-section-label>

    <x-table :cabecalhos="['permissão', 'descrição', 'papéis']" :paginacao="$permissions">
        @forelse ($permissions as $permission)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4">
                    <p class="font-semibold lowercase text-grafite">{{ $permission->name }}</p>
                    <p class="font-comanda text-[0.65rem] uppercase tracking-wider text-aco">{{ $permission->id }}</p>
                </td>
                <td class="px-5 py-4 text-sm text-aco">{{ $permission->description ?: 'sem descrição' }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-aco">{{ $permission->roles_count }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="3" class="px-5 py-10 text-center text-sm text-aco">nenhuma permissão encontrada.</td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
