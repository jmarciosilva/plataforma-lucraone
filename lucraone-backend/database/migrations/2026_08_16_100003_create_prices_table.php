<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('product_id');
            $table->string('currency')->default('BRL');
            $table->decimal('amount', 12, 2);
            $table->enum('type', ['cost', 'sale', 'suggested_retail'])->default('sale');
            $table->timestamps();

            // Unique constraint: one price per product, currency, type per tenant
            $table->unique(['tenant_id', 'product_id', 'currency', 'type']);

            // Indices
            $table->index(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'type']);

            // Foreign keys
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
        Schema::dropIfExists('prices');
    }
};
