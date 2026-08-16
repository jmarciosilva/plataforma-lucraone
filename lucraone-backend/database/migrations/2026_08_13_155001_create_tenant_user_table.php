<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo entre uma pessoa (users) e um estabelecimento (tenants).
 *
 * É aqui que mora o status operacional: o administrador de um estabelecimento
 * ativa, convida ou suspende alguém *no seu estabelecimento*, sem alcance
 * sobre os vínculos daquela pessoa com outros.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('user_id', 26);
            $table->enum('status', ['ACTIVE', 'INVITED', 'INACTIVE', 'SUSPENDED'])
                ->default('INVITED');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            // Uma pessoa tem no máximo um vínculo por estabelecimento
            $table->unique(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'status']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user');
    }
};
