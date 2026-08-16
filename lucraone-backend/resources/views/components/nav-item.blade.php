@props(['rota', 'rotulo'])

@php
    // Rotas ainda não criadas (sprints seguintes) caem em "#" sem quebrar o menu
    $existe = \Illuminate\Support\Facades\Route::has($rota);
    $href = $existe ? route($rota) : '#';
    $prefixo = \Illuminate\Support\Str::before($rota, '.');
    $ativo = $existe && (request()->routeIs($rota . '*') || request()->routeIs($prefixo . '.*'));
@endphp

<a
    href="{{ $href }}"
    @if ($ativo) aria-current="page" @endif
    @unless ($existe) aria-disabled="true" @endunless
    class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold lowercase transition-colors
        {{ $ativo
            ? 'gradiente-sol-suave text-grafite'
            : 'text-aco hover:bg-nevoa hover:text-grafite' }}
        {{ $existe ? '' : 'cursor-not-allowed opacity-50' }}"
>
    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg
        {{ $ativo ? 'gradiente-sol text-white' : 'bg-nevoa text-aco' }}">
        {{ $slot }}
    </span>
    {{ $rotulo }}
</a>
