<x-help-modal
    nome="help-users"
    titulo="ajuda de usuários"
    objetivo="Nesta tela você administra as pessoas que podem acessar o estabelecimento, com os dados básicos, a situação do acesso e os papéis de cada uma."
    visual="lista"
    :zonas="['filtros' => 1, 'novo' => 2, 'lista' => 3]"
    :colunas="['usuário' => null, 'papel de acesso' => 4, 'ações' => 5]"
    :itens="[
        'filtros' => 'localize uma pessoa pelo nome ou e-mail e filtre por situação do acesso.',
        'novo usuário' => 'cadastre nome, e-mail, senha inicial e os papéis de acesso.',
        'listagem' => 'mostra apenas as pessoas vinculadas ao estabelecimento atual; usuário é uma pessoa com conta global no LUCRAONE e pode ter acesso a mais de um estabelecimento.',
        'papel de acesso' => 'define quais áreas e ações a pessoa pode utilizar aqui.',
        'ações' => 'abrir o detalhe, editar, redefinir a senha e arquivar ou restaurar o acesso.',
    ]"
    dica="Os papéis definem quais áreas e ações cada pessoa pode utilizar. Arquivar retira o acesso a este estabelecimento sem apagar a pessoa."
/>
