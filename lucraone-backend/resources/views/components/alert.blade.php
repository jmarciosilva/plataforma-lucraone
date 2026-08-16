@props(['tipo' => 'info'])

@php
    $estilos = [
        'sucesso' => ['borda' => 'border-ok/30', 'fundo' => 'bg-ok/5', 'texto' => 'text-ok'],
        'erro' => ['borda' => 'border-brasa/30', 'fundo' => 'bg-brasa/5', 'texto' => 'text-brasa'],
        'atencao' => ['borda' => 'border-alerta/30', 'fundo' => 'bg-alerta/5', 'texto' => 'text-alerta'],
        'info' => ['borda' => 'border-linha', 'fundo' => 'bg-nevoa', 'texto' => 'text-aco'],
    ];

    $e = $estilos[$tipo] ?? $estilos['info'];

    $icones = [
        'sucesso' => '<path d="M20 6L9 17l-5-5" />',
        'erro' => '<circle cx="12" cy="12" r="10" /><path d="M15 9l-6 6M9 9l6 6" />',
        'atencao' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" /><path d="M12 9v4M12 17h.01" />',
        'info' => '<circle cx="12" cy="12" r="10" /><path d="M12 16v-4M12 8h.01" />',
    ];
@endphp

<div
    role="alert"
    x-data="{ visivel: true }"
    x-show="visivel"
    {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-modulo border {$e['borda']} {$e['fundo']} px-4 py-3"]) }}
>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
         class="h-5 w-5 shrink-0 {{ $e['texto'] }}" aria-hidden="true">
        {!! $icones[$tipo] ?? $icones['info'] !!}
    </svg>

    <div class="min-w-0 flex-1 text-sm {{ $e['texto'] }}">
        {{ $slot }}
    </div>

    <button
        type="button"
        @click="visivel = false"
        class="shrink-0 text-aco transition-colors hover:text-grafite"
        aria-label="fechar aviso"
    >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="h-4 w-4">
            <path d="M18 6L6 18M6 6l12 12" />
        </svg>
    </button>
</div>
