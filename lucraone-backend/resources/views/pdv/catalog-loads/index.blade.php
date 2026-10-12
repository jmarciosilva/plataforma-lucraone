<x-layouts.app title="central de cargas pdv" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-sol"></span>
        publicação versionada para consulta offline no caixa
    </x-slot:subtitulo>

    @if (session('sucesso'))
        <x-alert tipo="sucesso" class="mb-6">{{ session('sucesso') }}</x-alert>
    @endif
    @if ($errors->any())
        <x-alert tipo="erro" class="mb-6">{{ $errors->first() }}</x-alert>
    @endif

    <header class="mb-6">
        <h1 class="text-xl font-bold text-grafite sm:text-2xl">Central de Cargas PDV</h1>
        <p class="mt-1 max-w-3xl text-sm text-aco">Acompanhe a conectividade dos caixas e as versões de catálogo destinadas a cada terminal.</p>
    </header>

    @if ($previewMode)
        <div role="status" class="mb-6 rounded-xl border border-sol bg-sol p-4 text-sm text-grafite">
            Modo de pré-visualização: a publicação de cargas está temporariamente desabilitada.
        </div>
    @endif

    @if (! $catalogHistoryAvailable)
        <div role="status" class="mb-6 rounded-xl border border-linha bg-nevoa p-4 text-sm text-aco">
            Os indicadores e o histórico de distribuição serão habilitados futuramente. Os dados cadastrais e a conectividade dos terminais continuam disponíveis.
        </div>
    @endif

    <section aria-label="resumo dos terminais" class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-card><p class="text-xs text-aco">caixas online</p><p class="mt-1 text-2xl font-bold text-grafite">{{ $connectivityCounts->get('online', 0) }}</p></x-card>
        <x-card><p class="text-xs text-aco">comunicação atrasada</p><p class="mt-1 text-2xl font-bold text-grafite">{{ $connectivityCounts->get('delayed', 0) }}</p></x-card>
        <x-card><p class="text-xs text-aco">caixas offline</p><p class="mt-1 text-2xl font-bold text-grafite">{{ $connectivityCounts->get('offline', 0) }}</p></x-card>
        <x-card><p class="text-xs text-aco">cargas pendentes</p><p class="mt-1 text-2xl font-bold text-grafite">{{ $catalogHistoryAvailable ? $pendingDeliveryCount : '—' }}</p></x-card>
    </section>

    <x-section-label>estabelecimento e empresa</x-section-label>
    <x-card class="mb-6">
        <form method="GET" action="{{ route('pdv.catalog-loads.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <label class="mb-1 block text-xs lowercase text-aco">estabelecimento</label>
                <div class="rounded-xl border border-linha bg-nevoa px-3 py-2.5 text-sm font-semibold text-grafite">{{ $tenantNome }}</div>
            </div>
            <div class="min-w-0 flex-1">
                <label for="company_id" class="mb-1 block text-xs lowercase text-aco">empresa</label>
                <select id="company_id" name="company_id" onchange="this.form.submit()" class="min-h-11 w-full rounded-xl border border-linha bg-white px-3 text-sm text-grafite">
                    @foreach ($companies as $option)
                        <option value="{{ $option->id }}" @selected($company->id === $option->id)>{{ $option->trade_name ?: $option->legal_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-0 flex-1">
                <label class="mb-1 block text-xs lowercase text-aco">filiais operacionais</label>
                <div class="rounded-xl border border-linha bg-nevoa px-3 py-2.5 text-sm text-grafite">{{ $branches->pluck('name')->join(', ') ?: 'nenhuma filial ativa' }}</div>
            </div>
        </form>
    </x-card>

    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <x-section-label>terminais da empresa</x-section-label>
        <p class="text-xs text-aco">Online: heartbeat nos últimos {{ $onlineSeconds }} s · comunicação atrasada: até 5 min · depois: offline</p>
    </div>
    <x-card class="mb-8 overflow-hidden p-0">
        <form method="POST" action="{{ route('pdv.catalog-loads.store') }}" class="p-4 sm:p-6">
            @csrf
            <input type="hidden" name="company_id" value="{{ $company->id }}">
            @if ($selectedTerminalId)
                <input type="hidden" name="return_terminal_id" value="{{ $selectedTerminalId }}">
            @endif
            @if ($previewMode)
                <div class="mb-4 flex flex-col gap-2 rounded-xl bg-nevoa p-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-aco">A seleção está disponível para avaliação. Nenhuma carga será criada ou enviada durante a pré-visualização.</p>
                    <button type="submit" disabled class="min-h-11 cursor-not-allowed rounded-xl bg-nevoa px-5 text-sm font-semibold lowercase text-aco opacity-70">
                        publicação indisponível na pré-visualização
                    </button>
                </div>
            @elseif ($canPublish)
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <label class="flex items-start gap-2 text-sm text-aco">
                        <input type="checkbox" name="schedule_offline" value="1" class="mt-1 rounded border-linha text-sol focus:ring-sol">
                        <span>Permitir agendamento para terminais sem heartbeat recente. Eles receberão a carga quando voltarem a consultar o servidor.</span>
                    </label>
                    <button type="submit" class="min-h-11 rounded-xl bg-sol px-5 text-sm font-semibold lowercase text-grafite transition-opacity hover:opacity-90">
                        enviar carga de produtos e preços
                    </button>
                </div>
            @else
                <p class="mb-4 rounded-xl bg-nevoa p-3 text-sm text-aco">Você pode consultar as cargas, mas precisa da permissão de gerenciamento de produtos para publicar.</p>
            @endif
            <div class="divide-y divide-linha">
                @forelse ($terminals as $terminal)
                    @php
                        $eligible = $terminal->status === 'ACTIVE' && $terminal->installation_id !== null && $terminal->branch?->status === 'ACTIVE';
                        $state = $terminal->catalog_state;
                        $delivery = $terminal->last_delivery;
                    @endphp
                    <label class="grid grid-cols-1 gap-3 py-4 sm:grid-cols-[auto_1fr_1fr_1fr] sm:items-center {{ $eligible ? 'cursor-pointer' : 'opacity-60' }}">
                        <input type="checkbox" name="terminal_ids[]" value="{{ $terminal->id }}" @checked($selectedTerminalId === $terminal->id) @disabled($previewMode || !$canPublish || !$eligible) class="rounded border-linha text-sol focus:ring-sol">
                        <span>
                            <span class="block text-sm font-semibold text-grafite">{{ $terminal->name }}</span>
                            <span class="mt-0.5 block font-comanda text-[0.65rem] uppercase text-aco">{{ $terminal->branch?->name ?? 'sem filial' }} · cadastro {{ strtolower($terminal->status) }}</span>
                        </span>
                        <span class="text-sm text-aco">
                            <span class="font-semibold text-grafite">{{ $terminal->connectivity_label }}</span><br>
                            heartbeat: {{ $terminal->last_seen_at?->format('d/m/Y H:i:s') ?? 'nunca' }}
                        </span>
                        <span class="text-sm text-aco">
                            ativa: {{ $state?->version ? 'v'.$state->version : 'nenhuma' }}
                            @if ($state?->applied_at)<br>atualizada: {{ \Illuminate\Support\Carbon::parse($state->applied_at)->format('d/m/Y H:i') }}@endif
                            @if ($state)<br>produtos ativos: {{ $state->product_count }}@endif
                            <br>última entrega: {{ $delivery?->status ?? '—' }}@if ($delivery?->failure_code) · {{ $delivery->failure_code }}@endif
                        </span>
                    </label>
                @empty
                    <p class="py-8 text-center text-sm text-aco">Nenhum terminal vinculado a uma filial visível desta empresa.</p>
                @endforelse
            </div>
        </form>
    </x-card>

    <x-section-label>histórico de cargas</x-section-label>
    <x-card class="overflow-hidden p-0">
        @if (! $catalogHistoryAvailable)
            <p class="p-8 text-center text-sm text-aco">Histórico de cargas indisponível neste ambiente.</p>
        @else
        <div class="divide-y divide-linha">
            @forelse ($publications as $publication)
                <details class="group p-4 sm:p-5">
                    <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3">
                        <span>
                            <strong class="text-sm text-grafite">Versão {{ $publication->version }}</strong>
                            <span class="ml-2 rounded-full bg-nevoa px-2 py-1 text-xs text-aco">{{ strtolower($publication->status) }}</span>
                            <span class="mt-1 block text-xs text-aco">{{ $publication->created_at->format('d/m/Y H:i') }} · {{ $publication->product_count }} produtos</span>
                            <span class="mt-1 block break-all font-mono text-[0.65rem] text-aco">ID {{ $publication->id }}</span>
                        </span>
                        <span class="text-right text-xs text-aco">{{ $publication->deliveries_count }} caixas · {{ $publication->checksum ? substr($publication->checksum, 0, 16).'…' : 'checksum pendente' }}</span>
                    </summary>
                    <div class="mt-4 grid grid-cols-2 gap-2 text-xs text-aco sm:grid-cols-4">
                        <div>preços: <strong class="text-grafite">{{ $publication->price_count }}</strong></div>
                        <div>embalagens: <strong class="text-grafite">{{ $publication->package_count }}</strong></div>
                        <div>schema: <strong class="text-grafite">{{ $publication->schema_version }}</strong></div>
                        <div class="break-all">sha256: {{ $publication->checksum ?? '—' }}</div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($publication->deliveries as $delivery)
                            <span class="rounded-lg bg-nevoa px-2 py-1 text-xs text-aco">
                                {{ $delivery->terminal?->name ?? $delivery->terminal_id }} · {{ strtolower($delivery->status) }}
                                @if ($delivery->failure_code) · {{ $delivery->failure_code }}@endif
                                · {{ ($delivery->applied_at ?? $delivery->received_at ?? $delivery->updated_at)->format('d/m/Y H:i') }}
                            </span>
                        @endforeach
                    </div>
                </details>
            @empty
                <p class="p-8 text-center text-sm text-aco">Nenhuma carga publicada para esta empresa.</p>
            @endforelse
        </div>
        <div class="p-4">{{ $publications->links() }}</div>
        @endif
    </x-card>
</x-layouts.app>
