<?php

namespace App\Modules\Authorization\Domain;

/**
 * Autorização padrão de um estabelecimento: o catálogo de permissões e os
 * quatro papéis que todo estabelecimento nasce tendo.
 *
 * Fonte única (ONB-01A). Antes desta classe a mesma intenção tinha duas
 * respostas: o AuthorizationSeeder criava 4 papéis e 28 permissões, e a criação
 * pelo painel criava 1 papel e 26 — um estabelecimento criado pela tela nascia
 * sem manager, user e viewer, e sem CRUD de papéis para criá-los depois.
 *
 * O admin não é redeclarado aqui: ele continua vindo de AdminPermissionMatrix,
 * que já é a definição do SEC-04 e é verificada pelos testes de segurança.
 *
 * `manage-assigned-branches` e `view-assigned-branches` estão no catálogo e em
 * nenhum papel, de propósito: a migration
 * `2026_09_11_000000_remove_obsolete_assigned_branch_permissions_from_standard_roles`
 * as removeu dos papéis padrão. Atribuí-las para "fechar" a conta de 28 seria
 * reverter aquela decisão.
 *
 * A contenção entre conjuntos exigida pelo SEC-04 · E2 vale aqui e é verificada
 * por StandardRoleMatrixSecurityTest: user ⊆ manager ⊆ admin e viewer ⊆ manager.
 */
final class StandardRoleMatrix
{
    /**
     * Catálogo completo de permissões de um estabelecimento.
     *
     * @var list<string>
     */
    public const CATALOGO = [
        'create-role',
        'update-role',
        'delete-role',
        'view-roles',
        'create-permission',
        'update-permission',
        'delete-permission',
        'view-permissions',
        'manage-companies',
        'view-companies',
        'manage-products',
        'view-products',
        'manage-inventory',
        'view-inventory',
        'manage-sales',
        'view-sales',
        'manage-customers',
        'view-customers',
        'view-reports',
        'manage-automations',
        'view-automations',
        'manage-users',
        'view-users',
        'manage-branches',
        'view-branches',
        'view-all-branches',
        'manage-assigned-branches',
        'view-assigned-branches',
    ];

    /**
     * @var list<string>
     */
    private const MANAGER = [
        'manage-companies',
        'view-companies',
        'manage-products',
        'view-products',
        'manage-inventory',
        'view-inventory',
        'manage-sales',
        'view-sales',
        'manage-customers',
        'view-customers',
        'view-reports',
        'view-automations',
        'manage-users',
        'view-users',
        'view-branches',
    ];

    /**
     * @var list<string>
     */
    private const USER = [
        'view-companies',
        'view-products',
        'view-inventory',
        'view-sales',
        'view-customers',
        'view-reports',
        'view-automations',
        'view-users',
    ];

    /**
     * @var list<string>
     */
    private const VIEWER = [
        'view-companies',
        'view-products',
        'view-inventory',
        'view-sales',
        'view-customers',
        'view-reports',
        'view-automations',
        'view-branches',
    ];

    /**
     * Papéis padrão, na ordem em que são provisionados.
     *
     * @return array<string, array{descricao: string, permissoes: list<string>}>
     */
    public static function papeis(): array
    {
        return [
            'admin' => [
                'descricao' => 'Administrator role with full access',
                'permissoes' => AdminPermissionMatrix::NAMES,
            ],
            'manager' => [
                'descricao' => 'Manager role with limited administrative access',
                'permissoes' => self::MANAGER,
            ],
            'user' => [
                'descricao' => 'Regular user role',
                'permissoes' => self::USER,
            ],
            'viewer' => [
                'descricao' => 'Viewer role with read-only access',
                'permissoes' => self::VIEWER,
            ],
        ];
    }
}
