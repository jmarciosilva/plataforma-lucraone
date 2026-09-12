<x-help-modal
    nome="help-sales"
    titulo="ajuda de vendas"
    objetivo="Nesta tela você acompanha os pedidos do estabelecimento e consulta os itens, os valores e a situação de cada um."
    visual="lista"
    :zonas="['filtros' => 1, 'lista' => 2]"
    :colunas="['pedido e cliente' => 3, 'situação' => 4, 'ações' => 5]"
    :itens="[
        'filtros' => 'localize pelo número do pedido ou pelo cliente e filtre por situação e empresa.',
        'lista de pedidos' => 'mostra os pedidos do estabelecimento atual, do mais recente para o mais antigo.',
        'cliente' => 'quem comprou; ao criar um pedido dá para escolher um cliente já cadastrado ou cadastrar na hora.',
        'situação' => 'rascunho, aguardando, confirmado, enviado, concluído ou cancelado.',
        'ações' => 'abrir o detalhe para ver itens e valores e para mudar a situação do pedido.',
    ]"
    dica="Algumas mudanças de situação refletem no estoque: confirmar reserva as quantidades e enviar dá baixa definitiva. Os itens só podem ser alterados enquanto o pedido está em rascunho ou aguardando."
/>
