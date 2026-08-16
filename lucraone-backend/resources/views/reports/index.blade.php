@php
    $serieGrafico = collect($serie)->map(fn ($balde) => [
        'rotulo' => $balde['rotulo'],
        'valor' => $balde['faturamento'],
    ])->all();

    $projecao = $analise['confiavel'] && $analise['projecao_proximo_periodo'] > 0
        ? ['rotulo' => 'próximo', 'valor' => $analise['projecao_proximo_periodo']]
        : null;

    $tendenciaBadge = match ($analise['tendencia']) {
        'alta' => 'sucesso',
        'queda' => 'erro',
        default => 'neutro',
    };
@endphp

<x-layouts.app title="relatórios" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        {{ $periodo->rotulo() }}
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-reports')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('reports.sales', request()->query()) }}">vendas</x-button>
        <x-button variante="secundario" href="{{ route('reports.inventory', request()->query()) }}">estoque</x-button>
        <x-button variante="secundario" href="{{ route('reports.customers', request()->query()) }}">clientes</x-button>
    </x-slot:acoes>

    @include('reports._help')

    @include('reports._filtros', ['rotaAtual' => 'reports.index'])

    <x-section-label>o negócio no período</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi
            rotulo="faturamento"
            :valor="number_format($resumo['faturamento']['valor'], 2, ',', '.')"
            :variacao="$resumo['faturamento']['variacao']"
            comparativo="vs. período anterior"
            nota="enviados e concluídos"
        />
        <x-kpi
            rotulo="pedidos"
            :valor="$resumo['pedidos']['valor']"
            :variacao="$resumo['pedidos']['variacao']"
            comparativo="vs. período anterior"
            nota="pedidos faturados"
        />
        <x-kpi
            rotulo="ticket médio"
            :valor="number_format($resumo['ticket_medio']['valor'], 2, ',', '.')"
            :variacao="$resumo['ticket_medio']['variacao']"
            comparativo="vs. período anterior"
            nota="faturamento por pedido"
        />
        <x-kpi
            rotulo="carteira em aberto"
            :valor="number_format($resumo['carteira_em_aberto'], 2, ',', '.')"
            nota="ainda não faturado"
        />
    </div>

    <x-section-label class="mt-8">faturamento no tempo</x-section-label>

    <x-chart-colunas
        :serie="$serieGrafico"
        :projecao="$projecao"
        titulo="faturamento por período"
        :legenda="'agrupado ' . ($granularidades[$periodo->granularidade] ?? '') . ' · ' . $periodo->rotulo()"
        formato="moeda"
    />

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">tendência</p>
            <div class="mt-3 flex items-center gap-2">
                <x-badge :tipo="$tendenciaBadge">{{ $analise['tendencia'] }}</x-badge>
                @if ($analise['crescimento_percentual'] !== null)
                    <span class="font-comanda text-sm text-grafite">
                        {{ $analise['crescimento_percentual'] > 0 ? '+' : '' }}{{ number_format($analise['crescimento_percentual'], 1, ',', '.') }}%
                    </span>
                @endif
            </div>
            <p class="mt-3 text-xs text-aco">
                @if ($analise['confiavel'])
                    comparação da segunda metade do período contra a primeira.
                @else
                    período curto demais para afirmar tendência — precisa de pelo menos três agrupamentos.
                @endif
            </p>
        </x-card>

        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">estoque parado</p>
            <p class="mt-3 font-comanda text-2xl text-grafite">
                {{ number_format($resumo['estoque']['valor_custo'], 2, ',', '.') }}
            </p>
            <p class="mt-1 text-xs text-aco">
                valor a custo em {{ $resumo['estoque']['itens'] }} itens · margem potencial
                {{ number_format($resumo['estoque']['margem_potencial'], 2, ',', '.') }}
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                @if ($resumo['estoque']['baixo'] > 0)
                    <x-badge tipo="atencao">{{ $resumo['estoque']['baixo'] }} em baixo estoque</x-badge>
                @endif
                @if ($resumo['estoque']['excesso'] > 0)
                    <x-badge tipo="erro">{{ $resumo['estoque']['excesso'] }} em excesso</x-badge>
                @endif
                @if ($resumo['estoque']['baixo'] === 0 && $resumo['estoque']['excesso'] === 0)
                    <x-badge tipo="sucesso">estoque equilibrado</x-badge>
                @endif
            </div>
        </x-card>

        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">clientes</p>
            <p class="mt-3 font-comanda text-2xl text-grafite">{{ $resumo['clientes']['compraram_no_periodo'] }}</p>
            <p class="mt-1 text-xs text-aco">
                compraram no período, de {{ $resumo['clientes']['cadastrados'] }} cadastrados
            </p>
            <p class="mt-3 text-xs text-aco">
                gasto médio por cliente
                <span class="font-comanda text-grafite">{{ number_format($resumo['clientes']['gasto_medio_por_cliente'], 2, ',', '.') }}</span>
            </p>
        </x-card>
    </div>

    <x-section-label class="mt-8">o que puxa o faturamento</x-section-label>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-chart-barras
            :itens="collect($topProdutos)->map(fn ($p) => [
                'rotulo' => $p['nome'],
                'valor' => $p['receita'],
                'nota' => number_format($p['quantidade'], 0, ',', '.') . ' un',
            ])->all()"
            titulo="produtos mais vendidos"
            legenda="por receita no período"
            formato="moeda"
            vazio="nenhum produto faturado no período."
        />

        <x-chart-barras
            :itens="collect($topClientes)->map(fn ($c) => [
                'rotulo' => $c['nome'],
                'valor' => $c['total_gasto'],
                'nota' => $c['pedidos'] . ' ped',
            ])->all()"
            titulo="clientes que mais compraram"
            legenda="por valor gasto no período"
            formato="moeda"
            vazio="nenhum cliente faturado no período."
        />
    </div>
</x-layouts.app>
