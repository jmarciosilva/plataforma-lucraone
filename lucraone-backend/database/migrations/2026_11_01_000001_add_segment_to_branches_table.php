<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MS-02 — adiciona segmento operacional às filiais.
 *
 * Retrocompatível: todas as filiais existentes recebem 'SUPERMARKET' pelo
 * DEFAULT, preservando o comportamento atual do supermercado sem nenhuma
 * alteração nos dados.
 *
 * `operational_profile` (JSON) não é adicionado aqui — aguarda aprovação da
 * Decisão F antes da implementação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('segment', 30)->notNull()->default('SUPERMARKET')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('segment');
        });
    }
};
