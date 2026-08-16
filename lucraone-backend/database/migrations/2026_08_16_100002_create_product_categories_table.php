<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot entre produtos e categorias.
 *
 * Sem coluna tenant_id: os dois lados já são escopados por tenant nas suas
 * próprias tabelas, então um vínculo entre tenants diferentes é impossível —
 * o TenantScope impede carregar o registro do outro tenant para associar.
 * Uma coluna denormalizada aqui só criaria mais um ponto para sair de sincronia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->ulid('product_id');
            $table->ulid('category_id');
            $table->timestamps();

            $table->primary(['product_id', 'category_id']);
            $table->index('category_id');

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
