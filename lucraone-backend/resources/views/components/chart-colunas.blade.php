@props([
    'serie' => [],
    'titulo' => null,
    'legenda' => null,
    'formato' => 'moeda',
    'projecao' => null,
    'rotuloSerie' => 'realizado',
])

@php
    /*
    | Colunas de série temporal.
    |
    | Uma série só, então sem caixa de legenda — o título já diz o que está
    | plotado. Quando entra a projeção viram duas séries e a legenda aparece.
    | Marca: 24px no máximo, topo arredondado em 4px, base quadrada, 2px de
    | respiro entre vizinhas. Rótulo direto só no pico; o resto fica no eixo,
    | no tooltip e na tabela.
    */
    $pontos = collect($serie)->values();

    if ($projecao) {
        $pontos->push([
            'rotulo' => $projecao['rotulo'],
            'valor' => $projecao['valor'],
            'projetado' => true,
        ]);
    }

    $valores = $pontos->map(fn ($p) => (float) ($p['valor'] ?? 0));
    $maximo = (float) $valores->max();

    // Teto "redondo" para o eixo: 1, 2 ou 5 vezes uma potência de dez.
    $teto = 0.0;
    if ($maximo > 0) {
        $magnitude = 10 ** floor(log10($maximo));
        foreach ([1, 2, 2.5, 5, 10] as $passo) {
            if ($maximo <= $passo * $magnitude) {
                $teto = $passo * $magnitude;
                break;
            }
        }
    }

    $indiceDoPico = $maximo > 0 ? $valores->search($maximo) : null;

    // Com muitos baldes os rótulos do eixo colidem: mostra um a cada N.
    $passoRotulo = max(1, (int) ceil($pontos->count() / 8));

    $formatar = function ($valor) use ($formato) {
        $valor = (float) $valor;

        return $formato === 'moeda'
            ? number_format($valor, 2, ',', '.')
            : number_format($valor, $valor == (int) $valor ? 0 : 3, ',', '.');
    };
@endphp

<div {{ $attributes->merge(['class' => 'cartao p-5']) }} x-data="{ ativo: null }">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            @if ($titulo)
                <p class="text-sm font-semibold lowercase text-grafite">{{ $titulo }}</p>
            @endif
            @if ($legenda)
                <p class="mt-1 text-xs text-aco">{{ $legenda }}</p>
            @endif
        </div>

        @if ($projecao)
            {{-- Duas séries: a legenda é obrigatória, identidade nunca só pela cor --}}
            <div class="flex items-center gap-4 text-xs text-aco">
                <span class="flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-sm bg-grafico" aria-hidden="true"></span>
                    {{ $rotuloSerie }}
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-sm bg-aco" aria-hidden="true"></span>
                    projeção
                </span>
            </div>
        @endif
    </div>

    @if ($pontos->isEmpty() || $teto <= 0)
        <p class="py-12 text-center text-sm text-aco">sem movimento no período selecionado.</p>
    @else
        <div class="relative mt-6 pl-16">
            {{-- Grade recessiva: hairline sólida, um passo fora da superfície --}}
            <div class="pointer-events-none absolute inset-0 left-16">
                @foreach ([1, 0.75, 0.5, 0.25, 0] as $fracao)
                    <div class="absolute left-0 right-0 border-t border-linha" style="top: {{ (1 - $fracao) * 100 }}%"></div>
                    <span
                        class="absolute -translate-y-1/2 pr-2 text-right text-[0.65rem] tabular-nums text-aco"
                        style="top: {{ (1 - $fracao) * 100 }}%; left: -4rem; width: 3.75rem"
                    >{{ $formatar($teto * $fracao) }}</span>
                @endforeach
            </div>

            <div class="relative flex h-56 items-end gap-[2px]">
                @foreach ($pontos as $indice => $ponto)
                    @php
                        $valor = (float) ($ponto['valor'] ?? 0);
                        $altura = $teto > 0 ? ($valor / $teto) * 100 : 0;
                        $projetado = ! empty($ponto['projetado']);
                    @endphp

                    {{-- O alvo de hover é a coluna inteira, não só a parte pintada --}}
                    <div
                        class="group relative flex h-full flex-1 flex-col justify-end"
                        tabindex="0"
                        role="img"
                        aria-label="{{ $ponto['rotulo'] }}: {{ $formatar($valor) }}{{ $projetado ? ' (projeção)' : '' }}"
                        @mouseenter="ativo = {{ $indice }}"
                        @mouseleave="ativo = null"
                        @focus="ativo = {{ $indice }}"
                        @blur="ativo = null"
                    >
                        @if ($indice === $indiceDoPico && ! $projetado)
                            <span class="mb-1 text-center text-[0.65rem] font-semibold tabular-nums text-grafite">
                                {{ $formatar($valor) }}
                            </span>
                        @endif

                        <div
                            class="mx-auto w-full max-w-[24px] rounded-t transition-opacity {{ $projetado ? 'bg-aco' : 'bg-grafico' }} group-hover:opacity-75 group-focus:opacity-75"
                            style="height: {{ $altura }}%"
                        ></div>

                        {{--
                            Ancorado na ponta da coluna, não no topo do quadro:
                            o contêiner tem a altura toda do gráfico, então
                            `bottom-full` jogaria o tooltip para longe da marca.
                        --}}
                        <div
                            x-show="ativo === {{ $indice }}"
                            x-cloak
                            class="pointer-events-none absolute left-1/2 z-20 mb-2 -translate-x-1/2 whitespace-nowrap rounded-lg border border-linha bg-white px-2.5 py-1.5 shadow-cartao"
                            style="bottom: {{ $altura }}%"
                        >
                            {{-- O valor lidera, o rótulo segue --}}
                            <span class="block font-comanda text-xs font-semibold text-grafite">{{ $formatar($valor) }}</span>
                            <span class="block text-[0.65rem] text-aco">
                                {{ $ponto['rotulo'] }}{{ $projetado ? ' · projeção' : '' }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-2 flex gap-[2px]">
                @foreach ($pontos as $indice => $ponto)
                    <span class="flex-1 text-center text-[0.6rem] text-aco">
                        @if ($indice % $passoRotulo === 0 || $indice === $pontos->count() - 1)
                            {{ $ponto['rotulo'] }}
                        @endif
                    </span>
                @endforeach
            </div>
        </div>

        {{-- Toda leitura do gráfico existe sem depender de hover --}}
        <details class="mt-6 border-t border-linha pt-3">
            <summary class="cursor-pointer text-xs font-semibold lowercase text-aco transition-colors hover:text-grafite">
                ver como tabela
            </summary>

            <div class="mt-3 max-h-64 overflow-y-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-linha">
                            <th scope="col" class="py-2 text-xs font-semibold uppercase tracking-wide text-aco">período</th>
                            <th scope="col" class="py-2 text-right text-xs font-semibold uppercase tracking-wide text-aco">valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pontos as $ponto)
                            <tr class="border-b border-linha last:border-0">
                                <td class="py-2 text-sm text-aco">
                                    {{ $ponto['rotulo'] }}{{ ! empty($ponto['projetado']) ? ' (projeção)' : '' }}
                                </td>
                                <td class="py-2 text-right font-comanda text-sm tabular-nums text-grafite">
                                    {{ $formatar($ponto['valor'] ?? 0) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
</div>
