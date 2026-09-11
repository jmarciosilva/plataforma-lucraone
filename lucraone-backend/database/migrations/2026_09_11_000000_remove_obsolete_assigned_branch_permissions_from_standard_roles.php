<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SEC-04 · pré-requisito do E2 — retira dos papéis padrão manager e user as
 * permissões de filiais atribuídas.
 *
 * Só a BranchPolicy, que não está registrada, consultava essas permissões. Com
 * elas, as permissões do manager não cabiam nas do admin, as do user não cabiam
 * nas do manager nem nas do admin, e a contenção de atribuição de papéis do E2
 * bloquearia fluxos legítimos. A matriz do admin não muda, e as permissões
 * continuam no catálogo.
 *
 * Remove a linha só quando o papel padrão, a permissão e a linha de
 * role_permission são do mesmo tenant. Admin, viewer, papéis personalizados e
 * linhas com tenant divergente ficam como estão. Os pares são literais de
 * propósito: a migration é histórica e não acompanha o seeder.
 */
return new class extends Migration
{
    private const OBSOLETE_ASSIGNMENTS = [
        'manager' => 'manage-assigned-branches',
        'user' => 'view-assigned-branches',
    ];

    public function up(): void
    {
        foreach (self::OBSOLETE_ASSIGNMENTS as $roleName => $permissionName) {
            $rowIds = DB::table('role_permission')
                ->join('roles', 'roles.id', '=', 'role_permission.role_id')
                ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
                ->where('roles.name', $roleName)
                ->where('permissions.name', $permissionName)
                ->whereColumn('permissions.tenant_id', 'roles.tenant_id')
                ->whereColumn('role_permission.tenant_id', 'roles.tenant_id')
                ->pluck('role_permission.id');

            DB::table('role_permission')->whereIn('id', $rowIds)->delete();
        }
    }

    public function down(): void
    {
        // Data fix de segurança: o rollback não devolve as permissões. Não há
        // registro de quais linhas existiam antes, e recriá-las em todo manager e
        // user reintroduziria a matriz que impede a contenção do E2.
    }
};
