@props([
    'itens' => [],
    'titulo' => null,
    'legenda' => null,
    'formato' => 'moeda',
    'vazio' => 'sem dados no período selecionado.',
])

@php
    /*
    | Barras horizontais para ranking.
    |
    | Nomes de produto e de cliente são longos: na horizontal cabem sem girar
    | texto. Uma série só — sem legenda. Cada barra leva o valor na ponta, então
    | nenhum número depende de hover.
    */
    $linhas = collect($itens)->values();
    $maximo = (float) $linhas->map(fn ($i) => (float) ($i['valor'] ?? 0))->max();

    $formatar = function ($valor) use ($formato) {
        $valor = (float) $valor;

        return $formato === 'moeda'
            ? number_format($valor, 2, ',', '.')
            : number_format($valor, $valor == (int) $valor ? 0 : 3, ',', '.');
    };
@endphp

<div {{ $attributes->merge(['class' => 'cartao p-5']) }}>
    @if ($titulo)
        <p class="text-sm font-semibold lowercase text-grafite">{{ $titulo }}</p>
    @endif
    @if ($legenda)
        <p class="mt-1 text-xs text-aco">{{ $legenda }}</p>
    @endif

    @if ($linhas->isEmpty() || $maximo <= 0)
        <p class="py-10 text-center text-sm text-aco">{{ $vazio }}</p>
    @else
        <ul class="mt-5 space-y-3">
            @foreach ($linhas as $item)
                @php
                    $valor = (float) ($item['valor'] ?? 0);
                    $largura = $maximo > 0 ? ($valor / $maximo) * 100 : 0;
                @endphp

                <li tabindex="0" class="group block rounded-lg outline-none">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="truncate text-sm lowercase text-grafite">{{ $item['rotulo'] }}</span>
                        <span class="shrink-0 font-comanda text-xs tabular-nums text-grafite">{{ $formatar($valor) }}</span>
                    </div>

                    <div class="mt-1.5 flex items-center gap-2">
                        <div class="h-2.5 flex-1 rounded-full bg-nevoa">
                            <div
                                class="h-full rounded-full bg-grafico transition-opacity group-hover:opacity-75 group-focus:opacity-75"
                                style="width: {{ max($largura, 1.5) }}%"
                            ></div>
                        </div>

                        @if (! empty($item['nota']))
                            <span class="shrink-0 text-[0.65rem] text-aco">{{ $item['nota'] }}</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
