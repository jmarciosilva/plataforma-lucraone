<x-help-modal
    nome="help-tenants"
    titulo="ajuda de tenants"
    objetivo="Nesta área são consultados e administrados os estabelecimentos disponíveis para o seu acesso."
    visual="lista"
    :zonas="['filtros' => 1]"
    :colunas="['estabelecimento' => 2, 'situação' => 3, 'ações' => 4]"
    :itens="[
        'busca e filtros' => 'localize um estabelecimento pelo nome ou pelo apelido curto usado no endereço.',
        'dados do estabelecimento' => 'nome, plano contratado, fuso horário, idioma e moeda usados nas telas; tenant é o espaço isolado de cada cliente, com seus próprios usuários, produtos e pedidos.',
        'situação' => 'indica se o estabelecimento está em teste, ativo, bloqueado ou encerrado.',
        'ações' => 'abrir o detalhe, editar os dados e arquivar ou restaurar o cadastro.',
    ]"
    dica="Algumas ações administrativas aparecem apenas para usuários autorizados. Arquivar não apaga: o cadastro sai da lista e pode voltar depois."
/>
