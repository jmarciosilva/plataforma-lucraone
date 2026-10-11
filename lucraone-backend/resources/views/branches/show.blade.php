<x-layouts.app title="unidades" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full {{ $filial->isActive() ? 'bg-ok' : 'bg-brasa' }}"></span>
        detalhe da unidade
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('branches.index') }}">voltar</x-button>
        @if ($podeAdministrar)
            <x-button href="{{ route('branches.edit', $filial) }}">editar</x-button>
        @endif
    </x-slot:acoes>

    <x-section-label>resumo</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi rotulo="situação" :valor="strtolower($filial->status)" nota="operacional" />
        <x-kpi rotulo="tipo" :valor="$filial->is_headquarters ? 'matriz' : 'filial'" nota="classificação" />
        <x-kpi rotulo="endereços" :valor="$filial->addresses->count()" nota="vinculados" />
        <x-kpi rotulo="atualizada" :valor="$filial->updated_at->format('d/m')" nota="última alteração" />
    </div>

    <x-section-label class="mt-8">dados</x-section-label>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs lowercase text-aco">nome</dt>
                    <dd class="mt-1 text-sm font-semibold text-grafite">{{ $filial->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">código</dt>
                    <dd class="mt-1 font-comanda text-xs uppercase tracking-wider text-grafite">{{ $filial->code ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">empresa</dt>
                    <dd class="mt-1 text-sm text-grafite">
                        @if ($filial->company)
                            <a href="{{ route('companies.show', $filial->company) }}" class="font-semibold text-sol transition-opacity hover:opacity-70">
                                {{ $filial->company->legal_name }}
                            </a>
                        @else
                            <span class="text-aco">—</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">cnpj da filial</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $filial->document_override ?? '(usa o da empresa-mãe)' }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">e-mail</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $filial->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">telefone</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $filial->phone ?? '—' }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">ações</p>

            @if ($podeAdministrar)
                <x-button variante="secundario" href="{{ route('branches.edit', $filial) }}" class="mt-4 w-full">editar dados</x-button>

                @if ($filial->status === 'INACTIVE')
                    <form method="POST" action="{{ route('branches.toggle', $filial) }}" class="mt-2">
                        @csrf
                        <x-button tipo="submit" class="w-full">reativar unidade</x-button>
                    </form>
                @else
                    <button type="button" x-data @click="$dispatch('abrir-modal', 'desativar-branch')"
                        class="mt-2 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-brasa px-4 text-sm font-semibold lowercase text-white transition-opacity hover:opacity-90">
                        desativar unidade
                    </button>

                    <x-modal nome="desativar-branch" titulo="desativar unidade">
                        <p class="text-sm text-aco">
                            A unidade ficará inativa. Operadores vinculados a ela não poderão abrir o caixa enquanto estiver inativa.
                        </p>
                        <x-slot:acoes>
                            <form method="POST" action="{{ route('branches.toggle', $filial) }}">
                                @csrf
                                <x-button variante="perigo" tipo="submit">confirmar desativação</x-button>
                            </form>
                        </x-slot:acoes>
                    </x-modal>
                @endif
            @endif
        </x-card>
    </div>

    @if ($filial->addresses->isNotEmpty())
        <x-section-label class="mt-8">endereços</x-section-label>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($filial->addresses as $address)
                <x-card>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold lowercase text-grafite">{{ $address->formatted() }}</p>
                            <p class="mt-1 text-xs text-aco">{{ $address->country }} · {{ $address->created_at->format('d/m/Y') }}</p>
                        </div>
                        @if ($address->is_primary)
                            <x-badge tipo="destaque">principal</x-badge>
                        @endif
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    <x-section-label class="mt-8">terminais pdv</x-section-label>

    <x-card>
        @if ($terminais->isEmpty())
            <p class="text-sm text-aco">
                Nenhum terminal cadastrado nesta unidade.
                @if ($podeAdministrar)
                    <a href="{{ route('terminals.create') }}?branch_id={{ $filial->id }}" class="font-semibold text-sol hover:opacity-70">cadastrar o primeiro</a>
                @endif
            </p>
        @else
            <div class="space-y-2">
                @php
                    $coresTerminal = [
                        'PENDING' => 'bg-sol/10 text-sol',
                        'ACTIVE' => 'bg-ok/10 text-ok',
                        'BLOCKED' => 'bg-brasa/10 text-brasa',
                        'REVOKED' => 'bg-aco/10 text-aco',
                    ];
                    $labelTerminal = [
                        'PENDING' => 'aguardando',
                        'ACTIVE' => 'ativo',
                        'BLOCKED' => 'bloqueado',
                        'REVOKED' => 'revogado',
                    ];
                @endphp
                @foreach ($terminais as $t)
                    <div class="flex items-center justify-between gap-4 rounded-lg border border-linha px-3 py-2">
                        <div>
                            <p class="text-sm font-semibold lowercase text-grafite">{{ $t->name }}</p>
                            <p class="mt-0.5 font-comanda text-[0.65rem] uppercase tracking-wider text-aco">
                                {{ $t->last_seen_at ? 'visto em '.$t->last_seen_at->format('d/m H:i') : 'nunca conectado' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold lowercase {{ $coresTerminal[$t->status] ?? 'bg-aco/10 text-aco' }}">
                                {{ $labelTerminal[$t->status] ?? strtolower($t->status) }}
                            </span>
                            <a href="{{ route('terminals.show', $t) }}"
                               class="text-sm font-semibold text-sol transition-opacity hover:opacity-70 lowercase">
                                ver
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($podeAdministrar)
                <div class="mt-4">
                    <a href="{{ route('terminals.create') }}?branch_id={{ $filial->id }}"
                       class="text-sm font-semibold text-sol hover:opacity-70 lowercase">
                        + adicionar terminal
                    </a>
                    <span class="mx-2 text-aco">·</span>
                    <span class="text-xs text-aco">limite: {{ $filial->max_pdv_terminals }} terminal(is)</span>
                </div>
            @endif
        @endif
    </x-card>

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
