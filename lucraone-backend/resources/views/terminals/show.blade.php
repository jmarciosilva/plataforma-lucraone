<x-layouts.app title="terminais pdv" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        @php
            $cor = match($terminal->status) {
                'ACTIVE' => 'bg-ok',
                'BLOCKED' => 'bg-brasa',
                'REVOKED' => 'bg-aco',
                default => 'bg-sol',
            };
        @endphp
        <span class="h-2 w-2 rounded-full {{ $cor }}"></span>
        detalhe do terminal
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('terminals.index') }}">voltar</x-button>
        @if ($podeAdministrar && $terminal->status !== 'REVOKED')
            <x-button href="{{ route('terminals.edit', $terminal) }}">editar</x-button>
        @endif
    </x-slot:acoes>

    @if (session('sucesso'))
        <x-alert tipo="sucesso" class="mb-6">{{ session('sucesso') }}</x-alert>
    @endif
    @if ($errors->any())
        <x-alert tipo="erro" class="mb-6">{{ $errors->first() }}</x-alert>
    @endif

    @if (session('codigoPareamento'))
        @include('terminals._pairing_modal', [
            'codigo' => session('codigoPareamento'),
            'expira' => session('codigoExpira'),
        ])
    @endif

    <x-section-label>resumo</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi rotulo="situação" :valor="match($terminal->status) {
            'PENDING' => 'aguardando',
            'ACTIVE' => 'ativo',
            'BLOCKED' => 'bloqueado',
            'REVOKED' => 'revogado',
            default => strtolower($terminal->status),
        }" nota="do terminal" />
        <x-kpi rotulo="filial" :valor="$terminal->branch?->name ?? '—'" nota="vinculação" />
        <x-kpi rotulo="último acesso" :valor="$terminal->last_seen_at?->format('d/m H:i') ?? '—'" nota="heartbeat" />
        <x-kpi rotulo="cadastrado" :valor="$terminal->created_at->format('d/m/Y')" nota="criação" />
    </div>

    <x-section-label class="mt-8">dados</x-section-label>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs lowercase text-aco">nome</dt>
                    <dd class="mt-1 text-sm font-semibold text-grafite">{{ $terminal->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">id</dt>
                    <dd class="mt-1 font-comanda text-xs uppercase tracking-wider text-grafite">{{ $terminal->id }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">instalação</dt>
                    <dd class="mt-1 font-comanda text-xs uppercase tracking-wider text-grafite">{{ $terminal->installation_id ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">empresa</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $terminal->branch?->company?->legal_name ?? '—' }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">ações</p>

            @if ($podeAdministrar)
                @if ($terminal->status === 'PENDING')
                    <form method="POST" action="{{ route('terminals.pairing-code', $terminal) }}" class="mt-4">
                        @csrf
                        <x-button tipo="submit" class="w-full">gerar código de pareamento</x-button>
                    </form>

                    <form method="POST" action="{{ route('terminals.revoke', $terminal) }}" class="mt-2"
                          onsubmit="return confirm('Revogar este terminal? Esta ação é irreversível.')">
                        @csrf
                        <x-button variante="perigo" tipo="submit" class="w-full">revogar</x-button>
                    </form>
                @elseif ($terminal->status === 'ACTIVE')
                    <form method="POST" action="{{ route('terminals.pairing-code', $terminal) }}" class="mt-4">
                        @csrf
                        <x-button variante="secundario" tipo="submit" class="w-full">novo código de pareamento</x-button>
                    </form>

                    <button type="button" x-data @click="$dispatch('abrir-modal', 'bloquear-terminal')"
                        class="mt-2 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-brasa px-4 text-sm font-semibold lowercase text-white transition-opacity hover:opacity-90">
                        bloquear
                    </button>

                    <x-modal nome="bloquear-terminal" titulo="bloquear terminal">
                        <p class="text-sm text-aco">O terminal será bloqueado imediatamente. O aplicativo deixará de autenticar até o desbloqueio.</p>
                        <x-slot:acoes>
                            <form method="POST" action="{{ route('terminals.block', $terminal) }}">
                                @csrf
                                <x-button variante="perigo" tipo="submit">confirmar bloqueio</x-button>
                            </form>
                        </x-slot:acoes>
                    </x-modal>

                    <form method="POST" action="{{ route('terminals.revoke', $terminal) }}" class="mt-2"
                          onsubmit="return confirm('Revogar este terminal? Esta ação é irreversível.')">
                        @csrf
                        <x-button variante="perigo" tipo="submit" class="w-full">revogar</x-button>
                    </form>
                @elseif ($terminal->status === 'BLOCKED')
                    <form method="POST" action="{{ route('terminals.unblock', $terminal) }}" class="mt-4">
                        @csrf
                        <x-button tipo="submit" class="w-full">desbloquear</x-button>
                    </form>

                    <form method="POST" action="{{ route('terminals.revoke', $terminal) }}" class="mt-2"
                          onsubmit="return confirm('Revogar este terminal? Esta ação é irreversível.')">
                        @csrf
                        <x-button variante="perigo" tipo="submit" class="w-full">revogar</x-button>
                    </form>
                @else
                    <p class="mt-4 text-sm text-aco">Terminal revogado. Nenhuma ação disponível.</p>
                @endif
            @endif
        </x-card>
    </div>

    @if ($codigoPendente)
        <x-section-label class="mt-8">código de pareamento ativo</x-section-label>
        <x-card>
            <p class="text-sm text-aco">
                Existe um código de pareamento válido até
                <strong class="text-grafite">{{ $codigoPendente->expires_at->format('d/m/Y H:i') }}</strong>.
                Gere um novo para invalidar este.
            </p>
        </x-card>
    @endif

    @if ($historico->isNotEmpty())
        <x-section-label class="mt-8">histórico</x-section-label>

        <x-card>
            <ul class="divide-y divide-linha">
                @foreach ($historico as $evento)
                    <li class="py-3 first:pt-0 last:pb-0">
                        <p class="text-sm text-grafite">{{ $evento->description }}</p>
                        <p class="mt-0.5 font-comanda text-[0.65rem] uppercase tracking-wider text-aco">
                            {{ $evento->action }} · {{ $evento->created_at->format('d/m/Y H:i') }}
                        </p>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</x-layouts.app>
