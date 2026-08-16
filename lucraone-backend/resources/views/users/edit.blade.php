<x-layouts.app title="usuários" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-alerta"></span>
        editar pessoa e acesso no tenant atual
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('users.show', $user) }}">voltar</x-button>
    </x-slot:acoes>

    <x-section-label>edição</x-section-label>

    <x-card>
        <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-6">
            @csrf
            @method('PUT')

            @include('users._form', [
                'user' => $user,
                'membershipStatus' => $membershipStatus,
                'statusOptions' => $statusOptions,
                'accountStatusOptions' => $accountStatusOptions,
                'roles' => $roles,
                'selectedRoles' => $selectedRoles,
                'modo' => 'edit',
            ])

            <div class="flex justify-end gap-2">
                <x-button variante="fantasma" href="{{ route('users.show', $user) }}">cancelar</x-button>
                <x-button tipo="submit">salvar alterações</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
