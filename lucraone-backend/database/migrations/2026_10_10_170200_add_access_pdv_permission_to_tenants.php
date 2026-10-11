<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cria a linha da permissão `access-pdv` nos estabelecimentos existentes.
 *
 * O catálogo de permissões é provisionado por estabelecimento, no momento em
 * que ele é criado. Acrescentar um nome ao `StandardRoleMatrix` só alcança
 * estabelecimentos NOVOS; sem esta migration, nenhum estabelecimento já
 * existente teria a linha, e o administrador não teria o que atribuir a um
 * papel.
 *
 * Cria a permissão e NADA MAIS: nenhuma linha em `role_permission`, nenhuma
 * atribuição a papel, nenhum usuário autorizado. Depois desta migration o
 * número de pessoas com acesso ao caixa continua sendo zero — conceder é ato
 * administrativo explícito, pessoa por pessoa, e exige também a atribuição à
 * filial.
 */
return new class extends Migration
{
    private const PERMISSION = 'access-pdv';

    public function up(): void
    {
        $existentes = DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->pluck('tenant_id')
            ->all();

        $faltantes = DB::table('tenants')
            // Estabelecimento removido não volta a operar por esta migration.
            ->whereNull('deleted_at')
            ->whereNotIn('id', $existentes === [] ? [''] : $existentes)
            ->pluck('id');

        foreach ($faltantes as $tenantId) {
            DB::table('permissions')->insert([
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenantId,
                'name' => self::PERMISSION,
                'description' => self::PERMISSION,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Remove a permissão e as concessões dela, sempre no mesmo
        // estabelecimento das duas pontas. Sem isso o rollback deixaria
        // linhas de role_permission apontando para uma permissão inexistente.
        $ids = DB::table('permissions')->where('name', self::PERMISSION)->pluck('id');

        DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
