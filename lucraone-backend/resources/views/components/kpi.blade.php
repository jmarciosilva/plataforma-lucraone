@props([
    'rotulo',
    'valor',
    'variacao' => null,
    'comparativo' => null,
    'nota' => null,
])

@php
    // Variação positiva sobe em verde; negativa desce em brasa
    $positiva = $variacao !== null && (float) $variacao >= 0;
    $cor = $positiva ? 'text-ok' : 'text-brasa';
@endphp

<div {{ $attributes->merge(['class' => 'cartao p-5']) }}>
    <p class="text-xs lowercase text-aco">{{ $rotulo }}</p>

    <p class="mt-1.5 font-comanda text-2xl font-medium text-grafite lg:text-3xl">
        {{ $valor }}
    </p>

    @if ($variacao !== null)
        <p class="mt-2 flex items-center gap-1 text-xs font-semibold {{ $cor }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                 class="h-3.5 w-3.5 {{ $positiva ? '' : 'rotate-180' }}" aria-hidden="true">
                <path d="M12 19V5" />
                <path d="M6 11l6-6 6 6" />
            </svg>
            <span>
                {{ $positiva ? '+' : '' }}{{ $variacao }}%
                @isset($comparativo)
                    <span class="font-normal text-aco">{{ $comparativo }}</span>
                @endisset
            </span>
        </p>
    @elseif ($nota)
        <p class="mt-2 text-xs text-aco">{{ $nota }}</p>
    @endif
</div>
