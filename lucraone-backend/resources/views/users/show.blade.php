<x-layouts.app title="usuários" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full {{ $user->trashed() ? 'bg-brasa' : (($membership?->status === 'ACTIVE' && $user->status === 'ACTIVE') ? 'bg-ok' : 'bg-alerta') }}"></span>
        {{ $user->trashed() ? 'usuário arquivado' : 'detalhe do usuário' }}
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-users')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('users.index') }}">voltar</x-button>
        @if (! $user->trashed())
            <x-button href="{{ route('users.edit', $user) }}">editar</x-button>
        @endif
    </x-slot:acoes>

    @include('users._help')

    @if (session('senha_temporaria'))
        <x-alert tipo="atencao" titulo="senha temporária">
            {{ session('senha_temporaria') }}
        </x-alert>
    @endif

    <x-section-label>resumo</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi rotulo="status no tenant" :valor="strtolower($membership?->status ?? 'sem vínculo')" nota="acesso local" />
        <x-kpi rotulo="status da conta" :valor="strtolower($user->status)" nota="identidade global" />
        <x-kpi rotulo="papéis" :valor="$roles->count()" nota="neste tenant" />
        <x-kpi rotulo="último acesso" :valor="$user->last_login_at?->format('d/m') ?? 'nunca'" nota="login web/api" />
    </div>

    <x-section-label class="mt-8">dados</x-section-label>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs lowercase text-aco">nome</dt>
                    <dd class="mt-1 text-sm font-semibold text-grafite">{{ $user->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">email</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">papéis</dt>
                    <dd class="mt-1 text-sm text-grafite">
                        {{ $roles->pluck('name')->implode(', ') ?: 'sem papel' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">vinculado em</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $membership?->joined_at?->format('d/m/Y H:i') ?? 'sem data' }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">criado em</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $user->created_at->format('d/m/Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">atualizado em</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $user->updated_at->format('d/m/Y H:i') }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">ações</p>

            @if ($user->trashed())
                <form method="POST" action="{{ route('users.restore', $user->id) }}" class="mt-4">
                    @csrf
                    <x-button tipo="submit" class="w-full">restaurar usuário</x-button>
                </form>
            @else
                <x-button variante="secundario" href="{{ route('users.edit', $user) }}" class="mt-4 w-full">editar dados</x-button>

                @can('manageGlobalIdentity', [$user, $tenantId])
                    <form method="POST" action="{{ route('users.reset-password', $user) }}" class="mt-2">
                        @csrf
                        <x-button variante="secundario" tipo="submit" class="w-full">resetar senha</x-button>
                    </form>
                @endcan

                <button
                    type="button"
                    x-data
                    @click="$dispatch('abrir-modal', 'arquivar-user')"
                    class="mt-2 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-brasa px-4 text-sm font-semibold lowercase text-white transition-opacity hover:opacity-90"
                >
                    arquivar usuário
                </button>

                <x-modal nome="arquivar-user" titulo="arquivar usuário">
                    <p class="text-sm text-aco">
                        Se a conta for administrada só por este tenant, a identidade será arquivada. Caso contrário, apenas o vínculo atual será desativado.
                    </p>

                    <x-slot:acoes>
                        <form method="POST" action="{{ route('users.destroy', $user) }}">
                            @csrf
                            @method('DELETE')
                            <x-button variante="perigo" tipo="submit">confirmar arquivo</x-button>
                        </form>
                    </x-slot:acoes>
                </x-modal>
            @endif
        </x-card>
    </div>

    <x-section-label class="mt-8">últimas ações</x-section-label>

    <x-table :cabecalhos="['ação', 'entidade', 'quando', 'descrição']">
        @forelse ($latestActions as $action)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4 font-semibold lowercase text-grafite">{{ $action->action }}</td>
                <td class="px-5 py-4 text-sm text-aco">{{ $action->entity_type }}</td>
                <td class="px-5 py-4 text-sm text-aco">{{ $action->created_at->format('d/m/Y H:i') }}</td>
                <td class="px-5 py-4 text-sm text-aco">{{ $action->description ?? 'sem descrição' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="px-5 py-10 text-center text-sm text-aco">
                    nenhuma ação registrada para este usuário.
                </td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
