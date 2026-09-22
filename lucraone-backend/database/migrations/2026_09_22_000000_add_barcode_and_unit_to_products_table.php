<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Código da apresentação comercial (GTIN-8 a GTIN-14). Opcional:
            // produto próprio sem EAN segue identificado só pelo SKU.
            $table->string('barcode', 14)->nullable()->after('sku');

            // O que significa quantity = 1 no estoque e na venda (UN, KG).
            // Não é o conteúdo da embalagem: garrafa de 2 L continua UN.
            $table->string('unit', 6)->default('UN')->after('barcode');

            // Único dentro do tenant; vários NULL continuam permitidos.
            $table->unique(['tenant_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'barcode']);
            $table->dropColumn(['barcode', 'unit']);
        });
    }
};
