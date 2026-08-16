@php
    $rotuloSegmento = [
        'vip' => 'vip · 3+ pedidos',
        'recorrente' => 'recorrente · 2 pedidos',
        'novo' => 'novo · 1 pedido',
        'sem_compra' => 'sem compra no período',
    ];
@endphp

<x-layouts.app title="relatório de clientes" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        {{ $periodo->rotulo() }}
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-reports')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('reports.index', request()->query()) }}">voltar</x-button>
        <x-button variante="secundario" href="{{ route('reports.export', array_merge(request()->query(), ['tipo' => 'customers'])) }}">exportar csv</x-button>
    </x-slot:acoes>

    @include('reports._help')

    @include('reports._filtros', ['rotaAtual' => 'reports.customers', 'comGranularidade' => false])

    <x-section-label>quem comprou</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi rotulo="compraram" :valor="$totaisClientes['compraram_no_periodo']" nota="clientes com pedido faturado" />
        <x-kpi rotulo="cadastrados" :valor="$totaisClientes['cadastrados']" nota="base total de clientes" />
        <x-kpi rotulo="novos no período" :valor="$totaisClientes['novos_no_periodo']" nota="cadastrados no intervalo" />
        <x-kpi
            rotulo="gasto médio"
            :valor="number_format($totaisClientes['gasto_medio_por_cliente'], 2, ',', '.')"
            nota="por cliente que comprou"
        />
    </div>

    <x-section-label class="mt-8">segmentação</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($segmentacao as $chave => $quantidade)
            <x-card>
                <p class="text-xs lowercase text-aco">{{ $rotuloSegmento[$chave] ?? $chave }}</p>
                <p class="mt-1.5 font-comanda text-2xl text-grafite">{{ $quantidade }}</p>
            </x-card>
        @endforeach
    </div>

    <x-section-label class="mt-8">quem mais gastou</x-section-label>

    <x-chart-barras
        :itens="collect($topClientes)->take(8)->map(fn ($c) => [
            'rotulo' => $c['nome'],
            'valor' => $c['total_gasto'],
            'nota' => $c['pedidos'] . ' ped',
        ])->all()"
        legenda="valor faturado por cliente no período"
        formato="moeda"
        vazio="nenhum cliente faturado no período."
    />

    <x-section-label class="mt-8">detalhe</x-section-label>

    <x-table :cabecalhos="['cliente', 'contato', 'pedidos', 'total gasto', 'ticket médio', 'última compra']">
        @forelse ($topClientes as $cliente)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-3">
                    <a
                        href="{{ route('sales.customers.show', $cliente['customer_id']) }}"
                        class="text-sm lowercase text-grafite transition-colors hover:text-sol"
                    >{{ $cliente['nome'] }}</a>
                </td>
                <td class="px-5 py-3 text-sm text-aco">{{ $cliente['email'] ?: '—' }}</td>
                <td class="px-5 py-3 font-comanda text-sm tabular-nums text-grafite">{{ $cliente['pedidos'] }}</td>
                <td class="px-5 py-3 font-comanda text-sm tabular-nums text-grafite">
                    {{ number_format($cliente['total_gasto'], 2, ',', '.') }}
                </td>
                <td class="px-5 py-3 font-comanda text-sm tabular-nums text-aco">
                    {{ number_format($cliente['ticket_medio'], 2, ',', '.') }}
                </td>
                <td class="px-5 py-3 text-sm text-aco">
                    {{ $cliente['ultima_compra'] ? \Illuminate\Support\Carbon::parse($cliente['ultima_compra'])->format('d/m/Y') : '—' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-5 py-10 text-center text-sm text-aco">
                    nenhum cliente com pedido faturado no período selecionado.
                </td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
