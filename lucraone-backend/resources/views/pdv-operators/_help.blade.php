<x-help-modal
    nome="help-pdv-operators"
    titulo="ajuda de operadores de pdv"
    objetivo="Nesta tela você cadastra quem opera o caixa. Cada operador entra no PDV com um nome de usuário curto e a senha dele — sem precisar digitar e-mail."
    visual="lista"
    :zonas="['filtros' => 1, 'novo' => 2, 'lista' => 3]"
    :colunas="['operador' => null, 'filiais' => 4, 'ações' => 5]"
    :itens="[
        'filtros' => 'localize pelo nome ou pelo usuário do pdv e filtre por situação.',
        'novo operador' => 'cadastre uma pessoa nova ou habilite no caixa alguém que já existe no LUCRAONE.',
        'listagem' => 'mostra os operadores deste estabelecimento, o usuário do pdv de cada um e se há caixa aberto agora.',
        'filiais' => 'o operador só entra nos caixas das filiais em que tiver acesso liberado.',
        'ações' => 'abrir o detalhe, editar, ativar ou desativar e redefinir a senha.',
    ]"
    dica="Desativar um operador encerra o caixa aberto dele na hora. O nome de usuário vale só neste estabelecimento: outra loja pode ter um usuário com o mesmo nome."
/>
