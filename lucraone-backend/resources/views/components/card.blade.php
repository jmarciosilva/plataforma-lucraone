@props(['padding' => 'p-5'])

<div {{ $attributes->merge(['class' => 'cartao ' . $padding]) }}>
    {{ $slot }}
</div>
