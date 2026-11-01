<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REST-02 — grupos de modificadores (adicionais, remoções, escolhas).
 *
 * branch_id nullable (R2-A — decisão aprovada):
 *   NULL  = template global: vale para todas as filiais sem override.
 *   valor = override da filial: substitui integralmente o template global
 *           para essa filial específica.
 *
 * Resolução por precedência: grupos com branch_id = X >> grupos com branch_id IS NULL.
 * Os dois conjuntos nunca se mesclam — é substituição completa.
 *
 * CASCADE em branch_id: filial removida limpa seus overrides.
 * RESTRICT em product_id: produto com grupos não pode ser removido.
 * MySQL permite múltiplos NULLs em índice UNIQUE — comportamento desejado
 * para templates globais de produtos diferentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('product_id');
            $table->string('branch_id')->nullable();
            $table->string('tenant_id');
            $table->string('name', 100);
            $table->string('type', 20);
            $table->boolean('required')->default(false);
            $table->tinyInteger('min_selections')->unsigned()->default(0);
            $table->tinyInteger('max_selections')->unsigned()->nullable();
            $table->smallInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->unique(['product_id', 'branch_id', 'name']);
            $table->index(['tenant_id', 'product_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifier_groups');
    }
};
