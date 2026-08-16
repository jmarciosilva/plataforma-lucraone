<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sanctum cria tokenable_id como bigint, mas os usuários deste projeto usam ULID.
 * Sem isto, createToken() falha com "Data truncated for column 'tokenable_id'".
 *
 * O índice morph precisa cair antes da coluna — no SQLite, remover uma coluna
 * ainda referenciada por índice aborta a migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex('personal_access_tokens_tokenable_type_tokenable_id_index');
            $table->dropColumn('tokenable_id');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->ulid('tokenable_id')->after('tokenable_type');
            $table->index(['tokenable_type', 'tokenable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex('personal_access_tokens_tokenable_type_tokenable_id_index');
            $table->dropColumn('tokenable_id');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('tokenable_id')->after('tokenable_type');
            $table->index(['tokenable_type', 'tokenable_id']);
        });
    }
};
