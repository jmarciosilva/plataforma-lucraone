@props([
    'nome',
    'titulo' => null,
])

{{--
    Abre com: <button x-data @click="$dispatch('abrir-modal', 'NOME')">
    Fecha com Esc, clique no fundo ou no botão de fechar.
--}}
<div
    x-data="{ aberto: false }"
    x-on:abrir-modal.window="if ($event.detail === '{{ $nome }}') aberto = true"
    x-on:fechar-modal.window="if ($event.detail === '{{ $nome }}') aberto = false"
    x-on:keydown.escape.window="aberto = false"
    x-show="aberto"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    @if ($titulo) aria-label="{{ $titulo }}" @endif
>
    <div
        class="absolute inset-0 bg-grafite/40"
        @click="aberto = false"
        x-transition:enter="transition-opacity ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:leave="transition-opacity ease-in duration-150"
        x-transition:leave-end="opacity-0"
    ></div>

    <div
        class="cartao relative w-full max-w-lg p-6"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-end="opacity-0 scale-95"
    >
        @if ($titulo)
            <div class="mb-4 flex items-start justify-between gap-4">
                <h2 class="text-lg font-bold lowercase text-grafite">{{ $titulo }}</h2>
                <button
                    type="button"
                    @click="aberto = false"
                    class="shrink-0 text-aco transition-colors hover:text-grafite"
                    aria-label="fechar"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="h-5 w-5">
                        <path d="M18 6L6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @endif

        {{ $slot }}

        @isset($acoes)
            <div class="mt-6 flex justify-end gap-2">
                {{ $acoes }}
            </div>
        @endisset
    </div>
</div>
