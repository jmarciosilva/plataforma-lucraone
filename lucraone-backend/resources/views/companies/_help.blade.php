<x-help-modal
    nome="help-companies"
    titulo="ajuda de empresas"
    objetivo="Nesta tela você consulta e administra as empresas do estabelecimento atual, com os dados cadastrais e os endereços de cada uma."
    visual="lista"
    :zonas="['filtros' => 1, 'novo' => 2, 'lista' => 3]"
    :colunas="['empresa' => null, 'situação' => 4, 'ações' => 5]"
    :itens="[
        'busca e filtros' => 'localize uma empresa pelo nome ou pelo documento.',
        'nova empresa' => 'cadastre razão social, nome fantasia, documento e contato.',
        'listagem' => 'mostra as empresas cadastradas no estabelecimento em uso; empresa é o CNPJ ou a razão social que aparece nas vendas.',
        'situação' => 'ativa, inativa ou bloqueada temporariamente.',
        'ações' => 'abrir o detalhe, editar os dados e cadastrar os endereços da empresa.',
    ]"
    dica="Desativar uma empresa preserva o histórico: ela sai da operação, mas o cadastro e os registros ligados a ela continuam disponíveis."
/>
