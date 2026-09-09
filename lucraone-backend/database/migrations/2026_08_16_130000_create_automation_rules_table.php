<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->string('trigger', 60);

            /*
            | Condições e configuração da ação são JSON, mas nunca query livre:
            | os campos e operadores aceitos vêm do catálogo em código, e o
            | conteúdo é validado contra ele na escrita e na leitura.
            */
            $table->json('conditions')->nullable();
            $table->string('action', 60);
            $table->json('action_config')->nullable();

            $table->boolean('active')->default(true);
            $table->ulid('created_by')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'trigger', 'active']);
            $table->index(['tenant_id', 'active']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
