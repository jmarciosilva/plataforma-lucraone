@php
    $accountStatusOptions = $accountStatusOptions ?? [];
    $selectedRoles = $selectedRoles ?? [];
    $modo = $modo ?? 'create';

    // Quem também existe em outro estabelecimento, ou é Platform Admin, tem os
    // dados da conta só para leitura. O backend recusa a alteração; a tela só
    // evita o erro.
    $contaSomenteLeitura = $modo === 'edit' && ($identidadeGlobalProtegida ?? false);
@endphp

@if ($contaSomenteLeitura)
    <x-alert tipo="atencao">
        os dados da conta desta pessoa não podem ser alterados por este estabelecimento. o vínculo e os papéis continuam editáveis.
    </x-alert>
@endif

<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <x-form-group nome="name" rotulo="nome" obrigatorio>
        <x-input nome="name" :valor="$user->name" autocomplete="name" :readonly="$contaSomenteLeitura" />
    </x-form-group>

    <x-form-group nome="email" rotulo="email" obrigatorio>
        <x-input tipo="email" nome="email" :valor="$user->email" autocomplete="email" :readonly="$contaSomenteLeitura" />
    </x-form-group>

    <x-form-group nome="status" rotulo="status no tenant" obrigatorio>
        <x-select nome="status" :opcoes="$statusOptions" :valor="$membershipStatus" />
    </x-form-group>

    @if ($modo === 'edit')
        <x-form-group nome="account_status" rotulo="status da conta" obrigatorio>
            @if ($contaSomenteLeitura)
                {{-- select desabilitado não é enviado; o valor atual segue pelo campo oculto --}}
                <input type="hidden" name="account_status" value="{{ $user->status }}">
            @endif
            <x-select nome="account_status" :opcoes="$accountStatusOptions" :valor="$user->status" :disabled="$contaSomenteLeitura" />
        </x-form-group>
    @endif

    @unless ($contaSomenteLeitura)
        <x-form-group nome="password" rotulo="{{ $modo === 'edit' ? 'nova senha' : 'senha' }}" :obrigatorio="$modo === 'create'" ajuda="{{ $modo === 'edit' ? 'deixe vazio para manter a senha atual' : null }}">
            <x-input tipo="password" nome="password" autocomplete="new-password" />
        </x-form-group>

        <x-form-group nome="password_confirmation" rotulo="confirmar senha" :obrigatorio="$modo === 'create'">
            <x-input tipo="password" nome="password_confirmation" autocomplete="new-password" />
        </x-form-group>
    @endunless
</div>

<div class="mt-6">
    <p class="text-sm font-semibold lowercase text-grafite">papéis</p>
    <p class="mt-1 text-xs text-aco">papéis aplicados somente ao estabelecimento atual.</p>

    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
        @forelse ($roles as $role)
            <label class="flex min-h-11 items-center gap-3 rounded-xl border border-linha bg-white px-3 text-sm text-grafite transition-colors hover:bg-nevoa">
                <input
                    type="checkbox"
                    name="roles[]"
                    value="{{ $role->id }}"
                    class="h-4 w-4 rounded border-linha text-sol focus:ring-sol/30"
                    @checked(in_array($role->id, old('roles', $selectedRoles), true))
                >
                <span>
                    <span class="block font-semibold lowercase">{{ $role->name }}</span>
                    <span class="block text-xs text-aco">{{ $role->description }}</span>
                </span>
            </label>
        @empty
            <p class="text-sm text-aco">nenhum papel cadastrado para este tenant.</p>
        @endforelse
    </div>
</div>
