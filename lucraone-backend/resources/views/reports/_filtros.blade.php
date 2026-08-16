@php
    /*
    | Filtros ficam numa linha só, acima do conteúdo, e valem para tudo que vem
    | abaixo — gráfico, KPI e tabela leem sempre a mesma fatia.
    |
    | Espera $rotaAtual e, opcionalmente, $comGranularidade e $comSituacao.
    */
    $comGranularidade = $comGranularidade ?? true;
    $comSituacao = $comSituacao ?? false;

    $companyOptions = $companies->mapWithKeys(fn ($company) => [
        $company->id => $company->trade_name ?: $company->legal_name,
    ])->all();

    $inicioAtual = request('inicio', $periodo->inicio->toDateString());
    $fimAtual = request('fim', $periodo->fim->toDateString());
@endphp

<x-card class="mb-6">
    <form method="GET" action="{{ route($rotaAtual) }}" class="space-y-4">
        {{-- Intervalo primeiro: é o filtro que todo mundo procura --}}
        <div class="flex flex-wrap items-center gap-2">
            <span class="mr-1 text-xs font-semibold uppercase tracking-wide text-aco">intervalo</span>

            @foreach ($presets as $chave => $preset)
                @php
                    $ativo = $inicioAtual === $preset['inicio'] && $fimAtual === $preset['fim'];
                @endphp
                <a
                    href="{{ route($rotaAtual, array_merge(request()->query(), ['inicio' => $preset['inicio'], 'fim' => $preset['fim']])) }}"
                    class="inline-flex min-h-9 items-center rounded-xl border px-3 text-xs font-semibold lowercase transition-colors
                        {{ $ativo ? 'border-sol bg-nevoa text-grafite' : 'border-linha text-aco hover:bg-nevoa hover:text-grafite' }}"
                    @if ($ativo) aria-current="true" @endif
                >
                    @if ($ativo)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="mr-1 h-3.5 w-3.5" aria-hidden="true">
                            <path d="M20 6L9 17l-5-5" />
                        </svg>
                    @endif
                    {{ $preset['rotulo'] }}
                </a>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
            <x-form-group nome="inicio" rotulo="de">
                <x-input tipo="date" nome="inicio" :valor="$inicioAtual" />
            </x-form-group>

            <x-form-group nome="fim" rotulo="até">
                <x-input tipo="date" nome="fim" :valor="$fimAtual" />
            </x-form-group>

            <x-form-group nome="company_id" rotulo="empresa">
                <x-select nome="company_id" :opcoes="$companyOptions" :valor="request('company_id')" vazio="todas" />
            </x-form-group>

            @if ($comGranularidade)
                <x-form-group nome="granularidade" rotulo="agrupar">
                    <x-select nome="granularidade" :opcoes="$granularidades" :valor="request('granularidade', 'day')" />
                </x-form-group>
            @endif

            @if ($comSituacao)
                <x-form-group nome="situacao" rotulo="situação">
                    <x-select nome="situacao" :opcoes="$situacaoOptions" :valor="request('situacao')" />
                </x-form-group>
            @endif

            <div class="flex items-end gap-2">
                <x-button tipo="submit">aplicar</x-button>
                <x-button variante="fantasma" href="{{ route($rotaAtual) }}">limpar</x-button>
            </div>
        </div>
    </form>
</x-card>
