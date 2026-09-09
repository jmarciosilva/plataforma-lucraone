<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');

            // Nulo = aviso do estabelecimento inteiro, visível a quem tem acesso
            $table->ulid('user_id')->nullable();

            $table->string('title');
            $table->string('message', 1000);

            // info · sucesso · atencao · erro — mesma paleta de estado do painel
            $table->string('level', 20)->default('info');

            $table->string('link')->nullable();
            $table->ulid('automation_rule_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'read_at']);
            $table->index(['tenant_id', 'created_at']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('automation_rule_id')->references('id')->on('automation_rules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
