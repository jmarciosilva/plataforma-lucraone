@props([
    'nome',
    'opcoes' => [],
    'valor' => null,
    'vazio' => null,
])

@php
    $temErro = $errors->has($nome);
    $selecionado = old($nome, $valor);
@endphp

<select
    name="{{ $nome }}"
    id="{{ $attributes->get('id', $nome) }}"
    {{ $attributes->merge([
        'class' => 'block w-full min-h-11 rounded-xl border bg-white px-3 text-sm text-grafite
            transition-colors focus:outline-none focus:ring-2 focus:ring-sol/30 '
            . ($temErro ? 'border-brasa focus:border-brasa' : 'border-linha focus:border-sol'),
    ]) }}
>
    @isset($vazio)
        <option value="">{{ $vazio }}</option>
    @endisset

    @foreach ($opcoes as $chave => $rotulo)
        <option value="{{ $chave }}" @selected((string) $selecionado === (string) $chave)>
            {{ $rotulo }}
        </option>
    @endforeach
</select>
