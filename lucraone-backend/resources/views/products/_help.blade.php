<x-help-modal
    nome="help-products"
    titulo="ajuda de produtos"
    objetivo="Nesta tela você consulta e administra os produtos cadastrados no estabelecimento, com suas categorias, preços e situação."
    visual="lista"
    :zonas="['filtros' => 1, 'novo' => 2, 'lista' => 3]"
    :colunas="['produto' => null, 'categoria' => 4, 'ações' => 5]"
    :itens="[
        'busca e filtros' => 'localize pelo código ou nome e filtre por empresa, situação e arquivados.',
        'novo produto' => 'cadastre código, nome, descrição, empresa e categorias.',
        'dados fiscais' => 'NCM é a classificação fiscal do produto; CEST é opcional quando aplicável. Origem padrão é uma referência para avaliação futura. Esses dados não são exigidos para usar estoque e pedidos e o formato não comprova validade fiscal.',
        'listagem' => 'mostra o catálogo do estabelecimento atual.',
        'categoria e situação' => 'organizam o catálogo e indicam se o produto está em uso.',
        'ações' => 'abrir o detalhe, editar, cadastrar preços e arquivar ou restaurar.',
    ]"
    dica="Use os filtros para localizar um produto antes de criar um novo cadastro e evitar itens repetidos no catálogo."
/>
