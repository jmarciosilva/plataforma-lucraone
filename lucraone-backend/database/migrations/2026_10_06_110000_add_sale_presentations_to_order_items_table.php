<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('sale_presentation_type', 16)->nullable();
            $table->ulid('product_package_id')->nullable();
            $table->string('package_name')->nullable();
            $table->unsignedInteger('package_factor')->nullable();
            $table->string('presentation_barcode', 14)->nullable();
            $table->foreign('product_package_id')->references('id')->on('product_packages')->nullOnDelete();
            // Mantém suporte à FK order_id antes de remover o índice único no MySQL.
            $table->index(['order_id', 'product_id'], 'order_items_order_product_index');
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'product_id']);
        });
    }

    public function down(): void
    {
        // DDL MySQL não é transacional: verificar antes de qualquer alteração.
        // Executar rollback com as escritas da aplicação suspensas.
        if (DB::table('order_items')->select('order_id', 'product_id')
            ->groupBy('order_id', 'product_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Rollback indisponível: existem múltiplas linhas do mesmo produto no pedido. Nenhuma venda foi alterada.');
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->unique(['order_id', 'product_id']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_package_id']);
            $table->dropIndex('order_items_order_product_index');
            $table->dropColumn(['sale_presentation_type', 'product_package_id', 'package_name', 'package_factor', 'presentation_barcode']);
        });
    }
};
