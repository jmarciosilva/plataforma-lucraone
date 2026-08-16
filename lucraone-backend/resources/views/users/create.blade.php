<x-layouts.app title="usuários" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-sol"></span>
        novo acesso para o estabelecimento atual
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-users')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('users.index') }}">voltar</x-button>
    </x-slot:acoes>

    @include('users._help')

    <x-section-label>cadastro</x-section-label>

    <x-card>
        <form method="POST" action="{{ route('users.store') }}" class="space-y-6">
            @csrf

            @include('users._form', [
                'user' => $user,
                'membershipStatus' => $membershipStatus,
                'statusOptions' => $statusOptions,
                'roles' => $roles,
                'selectedRoles' => $selectedRoles,
            ])

            <div class="flex justify-end gap-2">
                <x-button variante="fantasma" href="{{ route('users.index') }}">cancelar</x-button>
                <x-button tipo="submit">salvar usuário</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
