<x-layouts.app title="usuários" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        pessoas vinculadas ao estabelecimento atual
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button href="{{ route('users.create') }}">novo usuário</x-button>
    </x-slot:acoes>

    <x-section-label>filtros</x-section-label>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('users.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-5">
            <x-form-group nome="search" rotulo="busca" class="lg:col-span-2">
                <x-input nome="search" :valor="request('search')" placeholder="nome ou email" />
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
                <x-button variante="fantasma" href="{{ route('users.index') }}">limpar</x-button>
            </div>
        </form>
    </x-card>

    <x-section-label>lista</x-section-label>

    <x-table
        :cabecalhos="['nome', 'email', 'status', 'papéis', 'último acesso', '']"
        :paginacao="$users"
    >
        @forelse ($users as $user)
            @php
                $membership = $user->membershipFor($tenantId);
                $roles = $user->rolesForTenant($tenantId)->orderBy('name')->pluck('name');
            @endphp

            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4">
                    <a href="{{ route('users.show', $user) }}" class="font-semibold lowercase text-grafite transition-colors hover:text-sol">
                        {{ $user->name }}
                    </a>
                    <p class="font-comanda text-[0.65rem] uppercase tracking-wider text-aco">{{ $user->id }}</p>
                </td>
                <td class="px-5 py-4 text-sm text-aco">{{ $user->email }}</td>
                <td class="px-5 py-4">
                    <x-badge :tipo="$user->trashed() ? 'erro' : (($membership?->status === 'ACTIVE' && $user->status === 'ACTIVE') ? 'sucesso' : 'atencao')">
                        {{ $user->trashed() ? 'arquivado' : strtolower($membership?->status ?? 'sem vínculo') }}
                    </x-badge>
                </td>
                <td class="px-5 py-4 text-sm text-aco">
                    {{ $roles->isNotEmpty() ? $roles->implode(', ') : 'sem papel' }}
                </td>
                <td class="px-5 py-4 text-sm text-aco">
                    {{ $user->last_login_at?->format('d/m/Y H:i') ?? 'nunca' }}
                </td>
                <td class="px-5 py-4 text-right">
                    <x-button variante="fantasma" href="{{ route('users.show', $user) }}">abrir</x-button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-5 py-10 text-center text-sm text-aco">
                    nenhum usuário encontrado.
                </td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
