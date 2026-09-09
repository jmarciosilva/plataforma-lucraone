@php
    // Campos internos não interessam a quem lê o e-mail
    $ocultos = ['product_id', 'company_id', 'order_id', 'inventory_id', 'customer_id', 'link'];
    $visiveis = collect($dados)->except($ocultos)->filter(fn ($valor) => is_scalar($valor));
@endphp

<x-mail::message>
# {{ $assunto }}

@if ($mensagem)
{{ $mensagem }}
@endif

@if ($visiveis->isNotEmpty())
**Detalhes**

@foreach ($visiveis as $campo => $valor)
- **{{ str_replace('_', ' ', $campo) }}:** {{ is_float($valor) ? number_format($valor, 2, ',', '.') : $valor }}
@endforeach
@endif

<x-slot:subcopy>
Enviado automaticamente pela regra **{{ $regra }}**, no gatilho *{{ $gatilho }}*.
Para parar de receber, desative a regra no painel em automações.
</x-slot:subcopy>
</x-mail::message>
