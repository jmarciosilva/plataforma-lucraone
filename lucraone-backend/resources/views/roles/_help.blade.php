<x-help-modal
    nome="help-roles"
    titulo="ajuda de permissões"
    objetivo="Papéis agrupam permissões e facilitam a definição do que cada pessoa pode fazer no estabelecimento."
    visual="papeis"
    :zonas="['lista' => 1, 'permissoes' => 2, 'detalhe' => 3, 'editar' => 4]"
    :itens="[
        'lista de papéis' => 'mostra os papéis do estabelecimento e quantas pessoas usam cada um.',
        'permissões do papel' => 'o que aquele papel libera no painel, marcado item a item.',
        'visualização' => 'abra um papel para ver as permissões e as pessoas vinculadas a ele.',
        'edição' => 'marque ou desmarque permissões pela tela de atribuição do papel.',
    ]"
    dica="Alterações em um papel valem imediatamente para todas as pessoas que o utilizam neste estabelecimento."
/>
