<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('product_id');
            $table->ulid('company_id');
            $table->decimal('quantity_on_hand', 14, 3)->default(0);
            $table->decimal('reserved', 14, 3)->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id', 'company_id']);
            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'product_id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
