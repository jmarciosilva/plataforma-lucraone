@props([
    'variante' => 'primario',
    'tipo' => 'button',
    'href' => null,
])

@php
    $base = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 text-sm font-semibold lowercase transition-all disabled:cursor-not-allowed disabled:opacity-50';

    $variantes = [
        'primario' => 'gradiente-sol text-white shadow-cartao hover:opacity-90',
        'secundario' => 'border border-linha bg-white text-grafite hover:bg-nevoa',
        'perigo' => 'bg-brasa text-white hover:opacity-90',
        'fantasma' => 'text-aco hover:bg-nevoa hover:text-grafite',
    ];

    $classes = $base . ' ' . ($variantes[$variante] ?? $variantes['primario']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $tipo }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
