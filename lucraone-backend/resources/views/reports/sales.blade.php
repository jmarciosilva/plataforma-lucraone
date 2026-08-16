@php
    $serieGrafico = collect($serie)->map(fn ($balde) => [
        'rotulo' => $balde['rotulo'],
        'valor' => $balde['faturamento'],
    ])->all();

    $badgePorStatus = [
        'draft' => 'neutro',
        'pending' => 'atencao',
        'confirmed' => 'destaque',
        'shipped' => 'destaque',
        'completed' => 'sucesso',
        'cancelled' => 'erro',
    ];
@endphp

<x-layouts.app title="relatório de vendas" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        {{ $periodo->rotulo() }}
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-reports')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('reports.index', request()->query()) }}">voltar</x-button>
        <x-button variante="secundario" href="{{ route('reports.export', array_merge(request()->query(), ['tipo' => 'sales'])) }}">exportar csv</x-button>
    </x-slot:acoes>

    @include('reports._help')

    @include('reports._filtros', ['rotaAtual' => 'reports.sales'])

    <x-section-label>totais do período</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-kpi rotulo="faturamento" :valor="number_format($totais['faturamento'], 2, ',', '.')" nota="enviados e concluídos" />
        <x-kpi rotulo="pedidos faturados" :valor="$totais['pedidos_faturados']" nota="de {{ $totais['pedidos_totais'] }} criados" />
        <x-kpi rotulo="ticket médio" :valor="number_format($totais['ticket_medio'], 2, ',', '.')" nota="faturamento por pedido" />
        <x-kpi rotulo="itens vendidos" :valor="number_format($totais['itens_vendidos'], 0, ',', '.')" nota="unidades faturadas" />
        <x-kpi rotulo="cancelados" :valor="$totais['pedidos_cancelados']" nota="pedidos no período" />
    </div>

    <x-section-label class="mt-8">faturamento no tempo</x-section-label>

    <x-chart-colunas
        :serie="$serieGrafico"
        titulo="faturamento por período"
        :legenda="'agrupado ' . ($granularidades[$periodo->granularidade] ?? '')"
        formato="moeda"
    />

    <div class="mt-8 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
            <x-section-label>onde estão os pedidos</x-section-label>

            <x-table :cabecalhos="['situação', 'pedidos', 'valor']">
                @foreach ($porSituacao as $linha)
                    <tr class="border-b border-linha last:border-0">
                        <td class="px-5 py-3">
                            <x-badge :tipo="$badgePorStatus[$linha['status']] ?? 'neutro'">
                                {{ $statusRotulos[$linha['status']] ?? $linha['status'] }}
                            </x-badge>
                        </td>
                        <td class="px-5 py-3 font-comanda text-sm tabular-nums text-grafite">{{ $linha['pedidos'] }}</td>
                        <td class="px-5 py-3 font-comanda text-sm tabular-nums text-aco">
                            {{ number_format($linha['valor'], 2, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </div>

        <div>
            <x-section-label>produtos mais vendidos</x-section-label>

            <x-chart-barras
                :itens="collect($topProdutos)->map(fn ($p) => [
                    'rotulo' => $p['nome'],
                    'valor' => $p['receita'],
                    'nota' => number_format($p['quantidade'], 0, ',', '.') . ' un',
                ])->all()"
                legenda="por receita no período"
                formato="moeda"
                vazio="nenhum produto faturado no período."
            />
        </div>
    </div>

    <x-section-label class="mt-8">detalhe dos produtos</x-section-label>

    <x-table :cabecalhos="['produto', 'sku', 'quantidade', 'receita']">
        @forelse ($topProdutos as $produto)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-3 text-sm lowercase text-grafite">{{ $produto['nome'] }}</td>
                <td class="px-5 py-3 font-comanda text-[0.7rem] uppercase tracking-wider text-aco">{{ $produto['sku'] }}</td>
                <td class="px-5 py-3 font-comanda text-sm tabular-nums text-grafite">
                    {{ number_format($produto['quantidade'], 3, ',', '.') }}
                </td>
                <td class="px-5 py-3 font-comanda text-sm tabular-nums text-grafite">
                    {{ number_format($produto['receita'], 2, ',', '.') }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="px-5 py-10 text-center text-sm text-aco">
                    nenhum produto faturado no período selecionado.
                </td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
