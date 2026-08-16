@props([
    'tipo' => 'text',
    'nome',
    'valor' => null,
    'erro' => null,
])

@php
    $temErro = $erro || $errors->has($nome);

    $classes = 'block w-full min-h-11 rounded-xl border bg-white px-3 text-sm text-grafite
        placeholder:text-aco transition-colors
        focus:outline-none focus:ring-2 focus:ring-sol/30 '
        . ($temErro ? 'border-brasa focus:border-brasa' : 'border-linha focus:border-sol');
@endphp

@if ($tipo === 'textarea')
    <textarea
        name="{{ $nome }}"
        id="{{ $attributes->get('id', $nome) }}"
        {{ $attributes->merge(['class' => $classes . ' py-2', 'rows' => 4]) }}
    >{{ old($nome, $valor) }}</textarea>
@else
    <input
        type="{{ $tipo }}"
        name="{{ $nome }}"
        id="{{ $attributes->get('id', $nome) }}"
        value="{{ $tipo === 'password' ? '' : old($nome, $valor) }}"
        {{ $attributes->merge(['class' => $classes]) }}
    >
@endif
