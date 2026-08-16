<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('inventory_id');
            $table->ulid('product_id');
            $table->ulid('company_id');
            $table->ulid('user_id')->nullable();
            $table->enum('type', ['in', 'out', 'adjustment', 'reservation', 'release']);
            $table->decimal('quantity', 14, 3);
            $table->decimal('quantity_before', 14, 3);
            $table->decimal('quantity_after', 14, 3);
            $table->string('reason')->nullable();
            $table->timestamp('moved_at')->index();
            $table->timestamps();

            $table->index(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'type']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('inventory_id')->references('id')->on('inventories')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
