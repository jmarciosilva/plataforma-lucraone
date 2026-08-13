<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Execute the migrations.
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->index();
            $table->char('company_id', 26);
            $table->string('name');
            $table->string('code')->nullable(); // Identificador interno
            $table->string('document_override')->nullable(); // CNPJ específico da filial
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('timezone')->default('America/Sao_Paulo');
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'SUSPENDED'])->default('ACTIVE')->index();
            $table->timestamps();

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

            // Unique constraint per tenant+company
            $table->unique(['tenant_id', 'company_id', 'code']);
            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
