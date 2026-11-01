<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REST-02 — itens das seções do cardápio.
 *
 * Cada item associa um Product a uma MenuSection com atributos de exibição
 * específicos do cardápio: descrição, preço override e disponibilidade.
 *
 * price_override NULL → usa Price[type=sale] do produto.
 * price_currency NULL → assume BRL (padrão do tenant).
 * UNIQUE(menu_section_id, product_id): um produto aparece uma vez por seção.
 * CASCADE em menu_section_id: seção removida remove seus itens.
 * RESTRICT em product_id: produto não pode ser removido enquanto estiver no cardápio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_section_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('menu_section_id');
            $table->string('product_id');
            $table->string('tenant_id');
            $table->text('description')->nullable();
            $table->decimal('price_override', 10, 2)->nullable();
            $table->char('price_currency', 3)->nullable();
            $table->smallInteger('position')->default(0);
            $table->boolean('available')->default(true);
            $table->timestamps();

            $table->foreign('menu_section_id')->references('id')->on('menu_sections')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->unique(['menu_section_id', 'product_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_section_items');
    }
};
