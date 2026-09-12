@props([
    'nome',
    'titulo',
    'objetivo',
    'itens' => [],
    'dica' => null,
    'visual' => 'lista',
    'zonas' => [],
    'colunas' => [],
])

{{--
    Ajuda contextual da tela. O texto responde o que é a tela, o que dá para
    fazer nela, onde ficam as ações e com o que tomar cuidado.

    itens: rótulo => explicação, na ordem em que os números aparecem.
    zonas e colunas: número do marcador em cada área do desenho da tela.

    Desenhos disponíveis em $visual: lista, painel e papeis.
--}}
@php
    $marcador = 'inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-grafite text-[11px] font-bold leading-none text-white';
    $marcadorMini = 'inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-grafite text-[9px] font-bold leading-none text-white';
    $posicao = 'absolute -left-2 top-1/2 z-10 -translate-y-1/2';
@endphp

<x-modal :nome="$nome" :titulo="$titulo" largura="max-w-3xl">
    <p class="text-sm leading-6 text-aco">{{ $objetivo }}</p>

    <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
        {{-- A folga à esquerda abre a calha onde ficam os marcadores numerados. --}}
        <div class="self-start rounded-modulo border border-linha bg-nevoa py-3 pl-7 pr-3" aria-hidden="true">
            @if ($visual === 'painel')
                <div class="space-y-2.5">
                    <div class="relative">
                        @isset($zonas['indicadores'])
                            <span class="{{ $marcador }} {{ $posicao }}">{{ $zonas['indicadores'] }}</span>
                        @endisset
                        <div class="grid grid-cols-4 gap-1.5">
                            @foreach (range(1, 4) as $indicador)
                                <div class="rounded-lg border border-linha bg-white p-1.5">
                                    <div class="h-1 w-6 rounded bg-linha"></div>
                                    <div class="mt-1.5 h-2.5 w-8 rounded bg-linha"></div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="relative">
                        @isset($zonas['resumo'])
                            <span class="{{ $marcador }} {{ $posicao }}">{{ $zonas['resumo'] }}</span>
                        @endisset
                        <div class="rounded-lg border border-linha bg-white p-2">
                            <div class="flex h-12 items-end gap-1.5">
                                @foreach ([40, 65, 50, 85, 70, 95] as $altura)
                                    <div class="gradiente-sol flex-1 rounded-t" style="height: {{ $altura }}%"></div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="relative">
                        @isset($zonas['alertas'])
                            <span class="{{ $marcador }} {{ $posicao }}">{{ $zonas['alertas'] }}</span>
                        @endisset
                        <div class="flex items-center gap-2 rounded-lg border border-linha bg-white p-2">
                            <span class="h-2 w-2 shrink-0 rounded-full bg-alerta"></span>
                            <div class="h-1.5 flex-1 rounded bg-linha"></div>
                            <div class="h-1.5 w-8 rounded bg-linha"></div>
                        </div>
                    </div>

                    <div class="relative">
                        @isset($zonas['atalhos'])
                            <span class="{{ $marcador }} {{ $posicao }}">{{ $zonas['atalhos'] }}</span>
                        @endisset
                        <div class="grid grid-cols-4 gap-1.5">
                            @foreach (range(1, 4) as $atalho)
                                <div class="rounded-lg border border-linha bg-white p-1.5">
                                    <div class="h-1 w-full rounded bg-linha"></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @elseif ($visual === 'papeis')
                <div class="space-y-2.5">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="relative">
                            @isset($zonas['lista'])
                                <span class="{{ $marcador }} {{ $posicao }}">{{ $zonas['lista'] }}</span>
                            @endisset
                            <div class="space-y-2 rounded-lg border border-linha bg-white p-2">
                                @foreach (['w-14', 'w-10', 'w-16'] as $larguraRotulo)
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="h-1.5 rounded bg-linha {{ $larguraRotulo }}"></div>
                                        <div class="h-3 w-6 rounded-full bg-nevoa"></div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="relative">
                            @isset($zonas['permissoes'])
                                <span class="{{ $marcador }} {{ $posicao }}">{{ $zonas['permissoes'] }}</span>
                            @endisset
                            <div class="space-y-2 rounded-lg border border-linha bg-white p-2">
                                @foreach ([true, true, false, true] as $marcada)
                                    <div class="flex items-center gap-1.5">
                                        <span class="h-2.5 w-2.5 shrink-0 rounded-sm border border-linha {{ $marcada ? 'gradiente-sol' : 'bg-white' }}"></span>
                                        <div class="h-1.5 flex-1 rounded bg-linha"></div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="relative">
                            @isset($zonas['detalhe'])
                                <span class="{{ $marcador }} {{ $posicao }}">{{ $zonas['detalhe'] }}</span>
                            @endisset
                            <div class="flex h-7 items-center justify-center rounded-lg border border-linha bg-white">
                                <div class="h-1.5 w-10 rounded bg-linha"></div>
                            </div>
                        </div>

                        <div class="relative">
                            @isset($zonas['editar'])
                                <span class="{{ $marcador }} {{ $posicao }}">{{ $zonas['editar'] }}</span>
                            @endisset
                            <div class="gradiente-sol flex h-7 items-center justify-center rounded-lg">
                                <div class="h-1.5 w-10 rounded bg-white/70"></div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="flex items-center gap-2">
                    @isset($zonas['filtros'])
                        <span class="{{ $marcadorMini }}">{{ $zonas['filtros'] }}</span>
                    @endisset
                    <div class="h-7 flex-1 rounded-lg border border-linha bg-white"></div>
                    <div class="h-7 w-10 rounded-lg border border-linha bg-white"></div>
                    @isset($zonas['novo'])
                        <span class="{{ $marcadorMini }}">{{ $zonas['novo'] }}</span>
                    @endisset
                    <div class="gradiente-sol h-7 w-14 rounded-lg"></div>
                </div>

                <div class="relative mt-3">
                    @isset($zonas['lista'])
                        <span class="{{ $marcador }} {{ $posicao }}">{{ $zonas['lista'] }}</span>
                    @endisset

                    <div class="overflow-hidden rounded-lg border border-linha bg-white">
                        <div class="flex items-center gap-2 border-b border-linha bg-nevoa px-2 py-1.5">
                            @foreach ($colunas as $rotulo => $numero)
                                <span class="flex items-center gap-1 text-[10px] font-semibold lowercase text-aco {{ $loop->first ? 'flex-1' : ($loop->last ? 'w-12 justify-end' : 'w-20') }}">
                                    @if ($numero)
                                        <span class="{{ $marcadorMini }}">{{ $numero }}</span>
                                    @endif
                                    {{ $rotulo }}
                                </span>
                            @endforeach
                        </div>

                        @foreach (range(1, 3) as $linha)
                            <div class="flex items-center gap-2 px-2 py-2 {{ $linha < 3 ? 'border-b border-linha' : '' }}">
                                @foreach ($colunas as $rotulo => $numero)
                                    <span class="{{ $loop->first ? 'flex-1' : ($loop->last ? 'w-12 justify-end' : 'w-20') }} flex">
                                        <span class="h-1.5 rounded bg-linha {{ $loop->first ? 'w-2/3' : 'w-full' }}"></span>
                                    </span>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <p class="mt-3 text-center text-[11px] lowercase text-aco">desenho simplificado desta tela</p>
        </div>

        <ol class="space-y-3">
            @foreach ($itens as $rotulo => $texto)
                <li class="flex gap-3">
                    <span class="{{ $marcador }} mt-0.5">{{ $loop->iteration }}</span>
                    <span class="text-sm leading-6 text-aco">
                        <strong class="font-semibold lowercase text-grafite">{{ $rotulo }}</strong> — {{ $texto }}
                    </span>
                </li>
            @endforeach
        </ol>
    </div>

    @if ($dica)
        <div class="gradiente-sol-suave mt-5 rounded-modulo border border-linha p-3">
            <p class="text-sm leading-6 text-grafite">
                <strong class="font-semibold lowercase">atenção</strong> — {{ $dica }}
            </p>
        </div>
    @endif

    <x-slot:acoes>
        <x-button @click="aberto = false">entendi</x-button>
    </x-slot:acoes>
</x-modal>
