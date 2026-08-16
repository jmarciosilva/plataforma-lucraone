<x-layouts.app title="permissões" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-sol"></span>
        papel {{ $role->name }}
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-roles')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('roles.index') }}">voltar</x-button>
        <x-button x-data @click="$dispatch('abrir-modal', 'assign-permissions')">atribuir permissões</x-button>
    </x-slot:acoes>

    @include('roles._help')

    <x-modal nome="assign-permissions" titulo="atribuir permissões">
        <form method="POST" action="{{ route('roles.permissions.sync', $role) }}">
            @csrf
            <p class="mb-4 text-sm text-aco">
                As permissões marcadas serão aplicadas ao papel {{ $role->name }} neste tenant.
            </p>

            <div class="max-h-96 space-y-2 overflow-y-auto pr-1">
                @foreach ($permissions as $permission)
                    <label class="flex min-h-11 items-center gap-3 rounded-xl border border-linha bg-white px-3 text-sm text-grafite transition-colors hover:bg-nevoa">
                        <input
                            type="checkbox"
                            name="permissions[]"
                            value="{{ $permission->id }}"
                            class="h-4 w-4 rounded border-linha text-sol focus:ring-sol/30"
                            @checked(in_array($permission->id, old('permissions', $selectedPermissions), true))
                        >
                        <span>
                            <span class="block font-semibold lowercase">{{ $permission->name }}</span>
                            <span class="block text-xs text-aco">{{ $permission->description ?: 'sem descrição' }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-button variante="fantasma" tipo="button" x-data @click="$dispatch('fechar-modal', 'assign-permissions')">cancelar</x-button>
                <x-button tipo="submit">salvar permissões</x-button>
            </div>
        </form>
    </x-modal>

    <x-section-label>resumo</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-kpi rotulo="permissões" :valor="count($selectedPermissions)" nota="atribuídas" />
        <x-kpi rotulo="usuários" :valor="$users->count()" nota="com este papel" />
        <x-kpi rotulo="criado" :valor="$role->created_at->format('d/m')" nota="cadastro técnico" />
    </div>

    <x-section-label class="mt-8">permissões do papel</x-section-label>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($permissions->whereIn('id', $selectedPermissions) as $permission)
            <div class="rounded-xl border border-linha bg-white p-4">
                <p class="text-sm font-semibold lowercase text-grafite">{{ $permission->name }}</p>
                <p class="mt-1 text-xs text-aco">{{ $permission->description ?: 'sem descrição' }}</p>
            </div>
        @empty
            <x-card>
                <p class="text-sm text-aco">nenhuma permissão atribuída a este papel.</p>
            </x-card>
        @endforelse
    </div>

    <x-section-label class="mt-8">usuários com este papel</x-section-label>

    <x-table :cabecalhos="['nome', 'email', 'status']">
        @forelse ($users as $user)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4">
                    <a href="{{ route('users.show', $user) }}" class="font-semibold lowercase text-grafite transition-colors hover:text-sol">
                        {{ $user->name }}
                    </a>
                </td>
                <td class="px-5 py-4 text-sm text-aco">{{ $user->email }}</td>
                <td class="px-5 py-4">
                    <x-badge :tipo="$user->status === 'ACTIVE' ? 'sucesso' : 'erro'">{{ strtolower($user->status) }}</x-badge>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="3" class="px-5 py-10 text-center text-sm text-aco">nenhum usuário possui este papel.</td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
