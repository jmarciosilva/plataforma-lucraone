@php
    $moeda = fn ($valor) => number_format((float) $valor, 2, ',', '.');
@endphp

<x-mail::message>
# Resumo de vendas

**{{ $estabelecimento }}** · {{ $periodo }}

| | |
|:---|---:|
| Faturamento | **{{ $moeda($totais['faturamento']) }}** |
| Pedidos faturados | {{ $totais['pedidos_faturados'] }} |
| Ticket médio | {{ $moeda($totais['ticket_medio']) }} |
| Carteira em aberto | {{ $moeda($totais['carteira_em_aberto']) }} |
| Pedidos cancelados | {{ $totais['pedidos_cancelados'] }} |

@if (count($topProdutos))
## Mais vendidos

@foreach ($topProdutos as $produto)
- **{{ $produto['nome'] }}** — {{ $moeda($produto['receita']) }} ({{ number_format($produto['quantidade'], 0, ',', '.') }} un)
@endforeach
@endif

@if ($estoque['baixo'] > 0 || $estoque['excesso'] > 0)
## Atenção no estoque

@if ($estoque['baixo'] > 0)
- {{ $estoque['baixo'] }} item(ns) no ponto de reposição
@endif
@if ($estoque['excesso'] > 0)
- {{ $estoque['excesso'] }} item(ns) acima do máximo
@endif
@endif

<x-slot:subcopy>
Faturamento conta apenas pedidos enviados e concluídos. Você recebe este resumo
porque tem permissão de ver relatórios neste estabelecimento.
</x-slot:subcopy>
</x-mail::message>
