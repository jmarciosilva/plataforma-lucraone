<?php

namespace App\Modules\Pdv\Domain;

/**
 * Permissões do RBAC do estabelecimento consumidas pelo PDV.
 *
 * Fonte única dos literais para não espalhá-los entre policy, middleware,
 * serviço de revalidação e testes. A permissão em si vive no catálogo do
 * `StandardRoleMatrix`, com as mesmas linhas e o mesmo provisionamento das
 * demais — o PDV não cria um sistema de permissões paralelo.
 *
 * `ACESSO` autoriza o aplicativo de caixa e NÃO autoriza por si só: operar num
 * caixa exige, além dela, uma atribuição ativa do operador àquela filial. Uma
 * permissão de estabelecimento vale no estabelecimento inteiro; a atribuição é
 * o que restringe à filial.
 *
 * A administração das atribuições reutiliza `manage-branches`, a mesma
 * autoridade que o TerminalPolicy já exige para administrar Terminal. Criar uma
 * permissão nova para isso a deixaria sem nenhum papel padrão — e portanto
 * inacessível a quem administra o estabelecimento hoje.
 */
final class OperatorPermission
{
    /** Usar o aplicativo de caixa. Não concede nenhuma capacidade comercial. */
    public const ACESSO = 'access-pdv';

    /** Conceder e revogar atribuição de operador a filial. */
    public const ADMINISTRAR_ATRIBUICOES = 'manage-branches';

    /**
     * Consultar atribuições de operador.
     *
     * @var list<string>
     */
    public const CONSULTAR_ATRIBUICOES = ['view-branches', 'view-all-branches', 'manage-branches'];
}
