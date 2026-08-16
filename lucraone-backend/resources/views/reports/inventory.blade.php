@php
    // Situação usa a paleta reservada de estado, sempre com rótulo junto —
    // nunca só a cor.
    $badgePorSituacao = [
        'ok' => 'sucesso',
        'baixo' => 'atencao',
        'excesso' => 'erro',
    ];

    $rotuloSituacao = [
        'ok' => 'ok',
        'baixo' => 'baixo',
        'excesso' => 'excesso',
    ];
@endphp

<x-layouts.app title="relatório de estoque" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        posição de agora, sem recorte de período
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-reports')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('reports.index', request()->query()) }}">voltar</x-button>
        <x-button variante="secundario" href="{{ route('reports.export', array_merge(request()->query(), ['tipo' => 'inventory'])) }}">exportar csv</x-button>
    </x-slot:acoes>

    @include('reports._help')

    @include('reports._filtros', [
        'rotaAtual' => 'reports.inventory',
        'comGranularidade' => false,
        'comSituacao' => true,
    ])

    <x-section-label>valor parado na prateleira</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi rotulo="valor a custo" :valor="number_format($resumoEstoque['valor_custo'], 2, ',', '.')" nota="dinheiro imobilizado" />
        <x-kpi rotulo="valor a venda" :valor="number_format($resumoEstoque['valor_venda'], 2, ',', '.')" nota="se vender tudo" />
        <x-kpi
            rotulo="margem potencial"
            :valor="number_format($resumoEstoque['margem_potencial'], 2, ',', '.')"
            :nota="$resumoEstoque['margem_percentual'] !== null ? number_format($resumoEstoque['margem_percentual'], 1, ',', '.') . '% sobre o custo' : 'sem custo cadastrado'"
        />
        <x-kpi rotulo="unidades" :valor="number_format($resumoEstoque['unidades'], 0, ',', '.')" nota="em {{ $resumoEstoque['itens'] }} itens" />
    </div>

    @if ($resumoEstoque['sem_preco_custo'] > 0)
        <x-alert tipo="atencao" class="mt-4">
            {{ $resumoEstoque['sem_preco_custo'] }} item(ns) sem preço de custo cadastrado entram como zero no valor a custo.
            Cadastre o custo em produtos para o número fechar.
        </x-alert>
    @endif

    <x-section-label class="mt-8">situação</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs lowercase text-aco">equilibrado</p>
                    <p class="mt-1 font-comanda text-2xl text-grafite">{{ $porSituacao['ok'] }}</p>
                </div>
                <x-badge tipo="sucesso">ok</x-badge>
            </div>
        </x-card>

        <x-card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs lowercase text-aco">no ponto de reposição</p>
                    <p class="mt-1 font-comanda text-2xl text-grafite">{{ $porSituacao['baixo'] }}</p>
                </div>
                <x-badge tipo="atencao">baixo</x-badge>
            </div>
        </x-card>

        <x-card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs lowercase text-aco">acima do máximo</p>
                    <p class="mt-1 font-comanda text-2xl text-grafite">{{ $porSituacao['excesso'] }}</p>
                </div>
                <x-badge tipo="erro">excesso</x-badge>
            </div>
        </x-card>
    </div>

    <x-section-label class="mt-8">maior valor imobilizado</x-section-label>

    <x-chart-barras
        :itens="collect($linhas)->take(8)->map(fn ($l) => [
            'rotulo' => $l['produto'],
            'valor' => $l['valor_custo'],
            'nota' => number_format($l['quantidade'], 0, ',', '.') . ' un',
        ])->all()"
        legenda="valor a custo por produto"
        formato="moeda"
        vazio="nenhum produto com estoque e custo cadastrado."
    />

    <x-section-label class="mt-8">posição completa</x-section-label>

    <x-table :cabecalhos="['produto', 'empresa', 'quantidade', 'reservado', 'valor a custo', 'valor a venda', 'situação']">
        @forelse ($linhas as $linha)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-3">
                    <span class="text-sm lowercase text-grafite">{{ $linha['produto'] }}</span>
                    <span class="block font-comanda text-[0.65rem] uppercase tracking-wider text-aco">{{ $linha['sku'] }}</span>
                </td>
                <td class="px-5 py-3 text-sm text-aco">{{ $linha['empresa'] }}</td>
                <td class="px-5 py-3 font-comanda text-sm tabular-nums text-grafite">
                    {{ number_format($linha['quantidade'], 3, ',', '.') }}
                </td>
                <td class="px-5 py-3 font-comanda text-sm tabular-nums text-aco">
                    {{ number_format($linha['reservado'], 3, ',', '.') }}
                </td>
                <td class="px-5 py-3 font-comanda text-sm tabular-nums text-grafite">
                    {{ number_format($linha['valor_custo'], 2, ',', '.') }}
                </td>
                <td class="px-5 py-3 font-comanda text-sm tabular-nums text-aco">
                    {{ number_format($linha['valor_venda'], 2, ',', '.') }}
                </td>
                <td class="px-5 py-3">
                    <x-badge :tipo="$badgePorSituacao[$linha['situacao']] ?? 'neutro'">
                        {{ $rotuloSituacao[$linha['situacao']] ?? $linha['situacao'] }}
                    </x-badge>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-5 py-10 text-center text-sm text-aco">
                    nenhum produto com estoque registrado.
                </td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
