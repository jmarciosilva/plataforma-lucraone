@php
    $moeda = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $pct = fn ($v) => number_format((float) $v, 1, ',', '.') . '%';
    $maxHora = collect($porHora)->max('total') ?: 0;
    $filtroQuery = array_filter([
        'date' => $dia->format('Y-m-d'),
        'periodo' => $periodo,
        'branch_id' => $branchId,
        'terminal_id' => $terminalId,
    ]);
    $pingUrl = route('pdv.sales.ping', $filtroQuery);
    $simularUrl = route('pdv.sales.simular', $filtroQuery);
    $monitor = ['url' => $pingUrl, 'base' => $eventosNoDia, 'hora' => $verificadoEm];
    $rotuloPeriodo = $periodo === 'hoje'
        ? $dia->translatedFormat('d/m/Y')
        : 'últimos ' . $periodos[$periodo] . ' até ' . $dia->translatedFormat('d/m/Y');
@endphp

<x-layouts.app title="vendas de pdv" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        painel do mercado · {{ $rotuloPeriodo }}
    </x-slot:subtitulo>

    <x-slot:acoes>
        <div x-data="vendasPdvMonitor(@js($monitor))" class="flex items-center gap-3">
            <span class="hidden items-center gap-1.5 text-xs text-aco sm:flex" aria-live="polite">
                <span class="h-2 w-2 rounded-full" :class="verificando ? 'animate-pulse bg-sol' : 'bg-ok'"></span>
                <span x-text="verificando ? 'verificando...' : ('verificado às ' + ultima)"></span>
            </span>

            <button
                type="button"
                @click="recarregar()"
                :class="novas > 0 && 'animate-pulse ring-2 ring-sol/40'"
                class="gradiente-sol inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 text-sm font-semibold lowercase text-white shadow-cartao transition-all hover:opacity-90"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                    <path d="M3 12a9 9 0 0 1 15-6.7L21 8" />
                    <path d="M21 3v5h-5" />
                    <path d="M21 12a9 9 0 0 1-15 6.7L3 16" />
                    <path d="M3 21v-5h5" />
                </svg>
                <span x-text="novas > 0
                    ? (novas + (novas > 1 ? ' novas vendas — atualizar' : ' nova venda — atualizar'))
                    : 'buscar vendas'"></span>
            </button>
        </div>
    </x-slot:acoes>

    <x-section-label>filtros</x-section-label>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('pdv.sales.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-5">
            <x-form-group nome="periodo" rotulo="período">
                <x-select nome="periodo" :opcoes="$periodos" :valor="$periodo" />
            </x-form-group>

            <x-form-group nome="date" rotulo="data de referência">
                <x-input tipo="date" nome="date" :valor="$dia->format('Y-m-d')" />
            </x-form-group>

            <x-form-group nome="branch_id" rotulo="filial">
                <x-select nome="branch_id" :opcoes="$branches->pluck('name', 'id')->toArray()" :valor="$branchId" vazio="todas" />
            </x-form-group>

            <x-form-group nome="terminal_id" rotulo="terminal">
                <x-select nome="terminal_id" :opcoes="$terminals->pluck('name', 'id')->toArray()" :valor="$terminalId" vazio="todos" />
            </x-form-group>

            <div class="flex items-end gap-2">
                <x-button tipo="submit">filtrar</x-button>
                <x-button variante="fantasma" href="{{ route('pdv.sales.index') }}">limpar</x-button>
            </div>
        </form>
    </x-card>

    <x-section-label>resumo do período</x-section-label>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-card>
            <p class="font-comanda text-[0.65rem] uppercase tracking-wider text-aco">faturamento</p>
            <p class="mt-1 text-2xl font-semibold text-grafite">{{ $moeda($cards['total_dia']) }}</p>
        </x-card>
        <x-card>
            <p class="font-comanda text-[0.65rem] uppercase tracking-wider text-aco">vendas</p>
            <p class="mt-1 text-2xl font-semibold text-grafite">{{ $cards['quantidade'] }}</p>
        </x-card>
        <x-card>
            <p class="flex items-center gap-1.5 font-comanda text-[0.65rem] uppercase tracking-wider text-aco">
                ticket médio
                <x-dica texto="Valor médio por venda no período: faturamento ÷ número de vendas confirmadas. Mostra quanto, em média, cada compra rendeu. Vendas canceladas não entram." />
            </p>
            <p class="mt-1 text-2xl font-semibold text-grafite">{{ $moeda($cards['ticket_medio']) }}</p>
        </x-card>
        <x-card>
            <p class="font-comanda text-[0.65rem] uppercase tracking-wider text-aco">cancelado</p>
            <p class="mt-1 text-2xl font-semibold text-brasa">{{ $moeda($cards['total_cancelado']) }}</p>
        </x-card>
    </div>

    @php
        $lucro = (float) $lucratividade['lucro'];
        $margem = $lucratividade['margem'];
        $corLucro = $lucro < 0 ? 'text-brasa' : 'text-ok';
    @endphp
    <x-card class="gradiente-sol-suave mb-6 border border-sol/20">
        <div class="flex flex-wrap items-center justify-between gap-6">
            <div>
                <p class="font-comanda text-[0.65rem] uppercase tracking-wider text-aco">lucratividade do período</p>
                <p class="mt-1 text-3xl font-semibold {{ $corLucro }}">{{ $moeda($lucro) }}</p>
                <p class="mt-0.5 text-xs text-aco">lucro = preço de venda − custo dos produtos vendidos</p>
            </div>

            <div class="text-right">
                <p class="font-comanda text-[0.65rem] uppercase tracking-wider text-aco">margem de lucro</p>
                @if ($margem === null)
                    <p class="mt-1 text-3xl font-semibold text-aco">—</p>
                    <p class="mt-0.5 text-xs text-aco">cadastre o custo dos produtos para ver a margem</p>
                @else
                    <p class="mt-1 text-3xl font-semibold {{ $corLucro }}">{{ number_format((float) $margem, 1, ',', '.') }}%</p>
                    <p class="mt-0.5 text-xs text-aco">sobre {{ $moeda($lucratividade['receita_avaliada']) }} avaliados</p>
                @endif
            </div>
        </div>

        @if ($lucratividade['itens_sem_custo'] > 0)
            <p class="mt-3 border-t border-sol/20 pt-2 text-xs text-aco">
                {{ $lucratividade['itens_sem_custo'] }} {{ $lucratividade['itens_sem_custo'] === 1 ? 'item ficou' : 'itens ficaram' }} de fora da margem por não ter custo cadastrado no produto.
            </p>
        @endif
    </x-card>

    <x-section-label>vendas por forma de pagamento</x-section-label>

    @php $maxPg = collect($pagamentos)->max('total') ?: 0; @endphp
    <x-card class="mb-6">
        <div class="space-y-3">
            @foreach ($pagamentos as $pg)
                <div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold lowercase text-grafite">{{ $pg['rotulo'] }}</span>
                        <span class="text-aco">{{ $moeda($pg['total']) }} · {{ $pct($pg['percentual']) }}</span>
                    </div>
                    <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-nevoa">
                        <div class="gradiente-sol h-full rounded-full" style="width: {{ $maxPg > 0 ? round($pg['total'] / $maxPg * 100) : 0 }}%;"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-card>

    <x-section-label>estoque</x-section-label>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-card>
            <p class="flex items-center gap-1.5 font-comanda text-[0.65rem] uppercase tracking-wider text-aco">
                itens em estoque
                <x-dica texto="Soma das unidades em estoque de todos os produtos do estabelecimento (fotografia do momento, não do período)." />
            </p>
            <p class="mt-1 text-2xl font-semibold text-grafite">{{ number_format((float) $estoque['itens'], 0, ',', '.') }}</p>
            <p class="mt-0.5 text-xs text-aco">{{ $estoque['produtos'] }} produto(s) com saldo</p>
        </x-card>
        <x-card>
            <p class="flex items-center gap-1.5 font-comanda text-[0.65rem] uppercase tracking-wider text-aco">
                valor a custo
                <x-dica texto="Quanto o estoque atual vale pelo preço de custo. É o capital parado em mercadoria." />
            </p>
            <p class="mt-1 text-2xl font-semibold text-grafite">{{ $moeda($estoque['valor_custo']) }}</p>
            <p class="mt-0.5 text-xs text-aco">capital parado</p>
        </x-card>
        <x-card>
            <p class="flex items-center gap-1.5 font-comanda text-[0.65rem] uppercase tracking-wider text-aco">
                valor a venda
                <x-dica texto="Quanto o estoque atual renderia se tudo fosse vendido pelo preço de venda. É a receita potencial." />
            </p>
            <p class="mt-1 text-2xl font-semibold text-grafite">{{ $moeda($estoque['valor_venda']) }}</p>
            <p class="mt-0.5 text-xs text-aco">receita potencial</p>
        </x-card>
        <x-card>
            <p class="font-comanda text-[0.65rem] uppercase tracking-wider text-aco">margem potencial</p>
            <p class="mt-1 text-2xl font-semibold text-ok">{{ $estoque['margem_potencial'] === null ? '—' : $pct($estoque['margem_potencial']) }}</p>
            <p class="mt-0.5 text-xs text-aco">lucro embutido no estoque</p>
        </x-card>
    </div>

    @if ($estoque['produtos_sem_custo'] > 0)
        <p class="-mt-3 mb-6 text-xs text-aco">
            {{ $estoque['produtos_sem_custo'] }} produto(s) sem custo cadastrado ficaram de fora do valor a custo e da margem.
        </p>
    @endif

    <x-section-label>simulador de promoção</x-section-label>

    <x-card class="mb-6" x-data="vendasPdvSimulador(@js(['url' => $simularUrl]))">
        <p class="text-sm text-aco">
            Escolha um produto e um percentual de desconto. A estimativa usa as vendas do <strong>período selecionado</strong> como base — fica mais precisa conforme o histórico cresce.
        </p>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-form-group nome="sim_produto" rotulo="produto">
                <x-select nome="sim_produto" :opcoes="$produtosSimulaveis->pluck('name', 'id')->toArray()" vazio="selecione" x-model="produto" />
            </x-form-group>
            <x-form-group nome="sim_desconto" rotulo="desconto (%)" ajuda="Entre 1 e 99.">
                <x-input nome="sim_desconto" tipo="number" x-model="desconto" min="1" max="99" step="0.5" placeholder="10" />
            </x-form-group>
            <div class="flex items-end">
                <x-button tipo="button" @click="simular()" ::disabled="carregando">simular promoção</x-button>
            </div>
        </div>

        <p x-show="carregando" x-cloak class="mt-4 text-sm text-aco">calculando...</p>
        <p x-show="erro" x-cloak class="mt-4 text-sm font-semibold text-brasa" x-text="erro"></p>

        <div x-show="r && r.ok" x-cloak class="mt-5 space-y-4 border-t border-linha pt-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="font-comanda text-[0.6rem] uppercase tracking-wider text-aco">preço</p>
                    <p class="mt-1 text-sm text-grafite">
                        <span class="text-aco line-through" x-text="moeda(r && r.preco_venda_atual)"></span>
                        <span class="font-semibold" x-text="' ' + moeda(r && r.preco_promocional)"></span>
                    </p>
                </div>
                <div>
                    <p class="font-comanda text-[0.6rem] uppercase tracking-wider text-aco">margem por unidade</p>
                    <p class="mt-1 text-sm">
                        <span class="text-aco" x-text="pct(r && r.margem_atual_pct)"></span>
                        <span class="font-semibold text-grafite" x-text="' → ' + pct(r && r.margem_promo_pct)"></span>
                    </p>
                </div>
                <div>
                    <p class="font-comanda text-[0.6rem] uppercase tracking-wider text-aco">vendidas no período</p>
                    <p class="mt-1 text-sm font-semibold text-grafite" x-text="num(r && r.volume_base) + ' un'"></p>
                </div>
                <div>
                    <p class="font-comanda text-[0.6rem] uppercase tracking-wider text-aco">impacto no lucro (mesmo volume)</p>
                    <p class="mt-1 text-sm font-semibold" :class="(r && r.impacto_lucro) < 0 ? 'text-brasa' : 'text-ok'"
                       x-text="moeda(r && r.impacto_lucro)"></p>
                </div>
            </div>

            <div class="rounded-xl bg-nevoa p-4 text-sm">
                <template x-if="r && r.vende_abaixo_do_custo">
                    <p class="font-semibold text-brasa">Com este desconto o produto é vendido abaixo do custo — não há ponto de equilíbrio: cada unidade dá prejuízo.</p>
                </template>
                <template x-if="r && !r.vende_abaixo_do_custo && r.volume_base > 0">
                    <p class="text-grafite">
                        Para manter o mesmo lucro do período
                        (<span class="font-semibold" x-text="moeda(r && r.lucro_base)"></span>),
                        você precisaria vender
                        <span class="font-semibold text-sol" x-text="num(r && r.unidades_adicionais) + ' unidade(s) a mais'"></span>
                        <span x-show="r && r.aumento_necessario_pct !== null" x-text="' (+' + pct(r && r.aumento_necessario_pct) + ' de volume)'"></span>.
                        Esse é o ponto de equilíbrio da promoção.
                    </p>
                </template>
                <template x-if="r && !r.vende_abaixo_do_custo && r.volume_base <= 0">
                    <p class="text-aco">Ainda não há vendas deste produto no período, então só dá para avaliar o efeito na margem por unidade. Escolha um período maior se já houver histórico.</p>
                </template>
            </div>
        </div>
    </x-card>

    <x-section-label>vendas por hora</x-section-label>

    <x-card class="mb-6">
        @if ($maxHora <= 0)
            <p class="py-8 text-center text-sm text-aco">nenhuma venda confirmada neste período.</p>
        @else
            <div class="flex items-end gap-1 pt-2" style="height: 180px;" role="img"
                 aria-label="gráfico de vendas por hora do dia">
                @foreach ($porHora as $balde)
                    @php $pct = $maxHora > 0 ? max(2, round($balde['total'] / $maxHora * 100)) : 0; @endphp
                    <div class="flex flex-1 flex-col items-center justify-end gap-1" style="height: 100%;">
                        <div class="w-full rounded-t {{ $balde['total'] > 0 ? 'gradiente-sol' : 'bg-nevoa' }}"
                             style="height: {{ $balde['total'] > 0 ? $pct : 1 }}%;"
                             title="{{ sprintf('%02dh', $balde['hora']) }} — {{ $moeda($balde['total']) }}"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex gap-1 text-center font-comanda text-[0.55rem] text-aco">
                @foreach ($porHora as $balde)
                    <div class="flex-1">{{ $balde['hora'] % 3 === 0 ? sprintf('%02d', $balde['hora']) : '' }}</div>
                @endforeach
            </div>
        @endif
    </x-card>

    <x-section-label>vendas recentes</x-section-label>

    <x-table :cabecalhos="['hora', 'terminal', 'operador', 'total', 'pagamento', 'itens', 'situação']" altura="max-h-[28rem]">
        @forelse ($recentes as $venda)
            <tr class="border-b border-linha/60 hover:bg-nevoa/40">
                <td class="px-5 py-3 text-sm text-grafite">{{ $venda['hora'] }}</td>
                <td class="px-5 py-3 text-sm text-aco">{{ $venda['terminal'] }}</td>
                <td class="px-5 py-3 text-sm text-aco">{{ $venda['operador'] }}</td>
                <td class="px-5 py-3 text-sm font-semibold {{ $venda['cancelada'] ? 'text-aco line-through' : 'text-grafite' }}">
                    {{ $moeda($venda['total']) }}
                </td>
                <td class="px-5 py-3 text-sm text-aco">{{ $venda['pagamento'] }}</td>
                <td class="px-5 py-3 text-sm text-aco">{{ $venda['itens'] }}</td>
                <td class="px-5 py-3 text-sm">
                    @if ($venda['cancelada'])
                        <span class="inline-flex items-center rounded-full bg-brasa/10 px-2 py-0.5 text-xs font-semibold lowercase text-brasa">cancelada</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-ok/10 px-2 py-0.5 text-xs font-semibold lowercase text-ok">confirmada</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-5 py-10 text-center text-sm text-aco">nenhuma venda recebida neste período.</td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
