<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_packages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('product_id');
            $table->string('name');
            $table->string('barcode', 14)->nullable();
            // Quantas unidades base do produto a embalagem contém (caixa 24 → 24).
            $table->unsignedInteger('factor');
            $table->timestamps();

            // Único dentro do tenant; vários NULL continuam permitidos. A colisão
            // com products.barcode é garantida pela validação (BarcodeAvailable).
            $table->unique(['tenant_id', 'barcode']);
            $table->index(['tenant_id', 'product_id']);

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_packages');
    }
};
