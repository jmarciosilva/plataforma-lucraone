@props(['rotulo', 'rotas' => []])

@php
    // Grupo "ativo" quando a rota atual é de um dos filhos. Mesma lógica de
    // prefixo do x-nav-item: "pdv.catalog-loads.index" → casa "pdv.catalog-loads.*".
    // Serve para abrir o grupo automaticamente e destacar o cabeçalho.
    $grupoAtivo = collect($rotas)->contains(function ($r) {
        if (! \Illuminate\Support\Facades\Route::has($r)) {
            return false;
        }
        $prefixo = \Illuminate\Support\Str::beforeLast($r, '.');

        return request()->routeIs($r . '*') || request()->routeIs($prefixo . '.*');
    });
@endphp

<div x-data="{ aberto: @js($grupoAtivo) }" class="flex flex-col gap-1">
    <button
        type="button"
        @click="aberto = ! aberto"
        :aria-expanded="aberto ? 'true' : 'false'"
        class="flex min-h-11 w-full items-center gap-3 rounded-xl px-3 text-sm font-semibold lowercase transition-colors
            {{ $grupoAtivo ? 'text-grafite' : 'text-aco hover:bg-nevoa hover:text-grafite' }}"
    >
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg
            {{ $grupoAtivo ? 'gradiente-sol text-white' : 'bg-nevoa text-aco' }}">
            {{ $icone }}
        </span>
        {{ $rotulo }}
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
             class="ml-auto h-4 w-4 transition-transform" :class="aberto && 'rotate-180'" aria-hidden="true">
            <path d="M6 9l6 6 6-6" />
        </svg>
    </button>

    <div x-show="aberto" x-cloak class="ml-4 space-y-1 border-l border-linha pl-3">
        {{ $slot }}
    </div>
</div>
