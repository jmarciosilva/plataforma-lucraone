<x-help-modal
    nome="help-dashboard"
    titulo="ajuda do dashboard"
    objetivo="O dashboard reúne os principais indicadores do estabelecimento atual e ajuda a acompanhar a operação rapidamente."
    visual="painel"
    :zonas="['indicadores' => 1, 'resumo' => 2, 'alertas' => 3, 'atalhos' => 4]"
    :itens="[
        'indicadores' => 'faturamento, pedidos, ticket médio e estoque dos últimos 30 dias, com a variação em relação ao período anterior.',
        'resumo de vendas' => 'evolução do que foi vendido no período; o link abaixo dos números abre os relatórios completos.',
        'informações da operação' => 'último acesso, contagens do estabelecimento e aviso de itens com estoque baixo.',
        'atalhos' => 'acesso rápido às áreas mais usadas do painel.',
    ]"
    dica="Os dados exibidos consideram o estabelecimento selecionado no momento. Para ver outro, troque o estabelecimento pelo menu."
/>
