@props(['tipo' => 'neutro'])

@php
    $estilos = [
        'sucesso' => 'bg-ok/10 text-ok',
        'erro' => 'bg-brasa/10 text-brasa',
        'atencao' => 'bg-alerta/10 text-alerta',
        'destaque' => 'gradiente-sol-suave text-grafite',
        'neutro' => 'bg-nevoa text-aco',
    ];
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold lowercase '
        . ($estilos[$tipo] ?? $estilos['neutro']),
]) }}>
    {{ $slot }}
</span>
