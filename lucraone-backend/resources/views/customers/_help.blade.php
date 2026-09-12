<x-help-modal
    nome="help-customers"
    titulo="ajuda de clientes"
    objetivo="Nesta tela você consulta e administra os clientes utilizados nos pedidos e nas vendas do estabelecimento."
    visual="lista"
    :zonas="['filtros' => 1, 'novo' => 2, 'lista' => 3]"
    :colunas="['cliente' => null, 'dados' => 4, 'ações' => 5]"
    :itens="[
        'busca' => 'localize pelo nome, e-mail ou documento e filtre por empresa e situação.',
        'novo cliente' => 'cadastre nome, contato, documento e a empresa responsável.',
        'listagem' => 'mostra os clientes do estabelecimento atual.',
        'dados do cliente' => 'contato e empresa usados no momento de montar um pedido.',
        'ações' => 'abrir o detalhe com os últimos pedidos, editar e arquivar ou restaurar.',
    ]"
    dica="Confira os dados do cliente antes de utilizá-lo em um pedido. Durante a criação de um pedido também dá para cadastrar um cliente na hora."
/>
