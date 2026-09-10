<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SEC-04 · complementa o papel oficial "admin" de cada tenant com as permissões
 * explícitas que antes eram alcançadas pelo coringa create-role.
 *
 * A lista abaixo é um retrato imutável da matriz administrativa desta data. Não
 * trocar pela classe da aplicação: a matriz pode evoluir, e esta migration
 * precisa continuar executando exatamente o que foi auditado aqui.
 */
return new class extends Migration
{
    private const ADMIN_PERMISSION_NAMES = [
        'create-role', 'update-role', 'delete-role', 'view-roles',
        'create-permission', 'update-permission', 'delete-permission', 'view-permissions',
        'manage-companies', 'view-companies',
        'manage-products', 'view-products',
        'manage-inventory', 'view-inventory',
        'manage-sales', 'view-sales',
        'manage-customers', 'view-customers',
        'view-reports',
        'manage-automations', 'view-automations',
        'manage-users', 'view-users',
        'manage-branches', 'view-branches', 'view-all-branches',
    ];

    public function up(): void
    {
        $now = now();

        DB::table('roles')
            ->where('name', 'admin')
            ->orderBy('id')
            ->each(function (object $admin) use ($now): void {
                $permissions = DB::table('permissions')
                    ->where('tenant_id', $admin->tenant_id)
                    ->whereIn('name', self::ADMIN_PERMISSION_NAMES)
                    ->pluck('id', 'name');

                foreach (self::ADMIN_PERMISSION_NAMES as $name) {
                    $permissionId = $permissions[$name] ?? (string) Str::ulid();

                    if (! isset($permissions[$name])) {
                        DB::table('permissions')->insert([
                            'id' => $permissionId,
                            'tenant_id' => $admin->tenant_id,
                            'name' => $name,
                            'description' => $name,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    DB::table('role_permission')->insertOrIgnore([
                        'role_id' => $admin->id,
                        'permission_id' => $permissionId,
                        'tenant_id' => $admin->tenant_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Data fix aditivo: o rollback não revoga nada, porque não há como
        // distinguir o que foi concedido aqui do que o admin já tinha legitimamente.
    }
};
