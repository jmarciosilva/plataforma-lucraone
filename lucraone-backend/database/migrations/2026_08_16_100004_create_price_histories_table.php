<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('price_id');
            $table->ulid('product_id');
            $table->decimal('old_amount', 12, 2);
            $table->decimal('new_amount', 12, 2);
            $table->string('currency');
            $table->ulid('changed_by')->nullable(); // User who changed
            $table->string('reason')->nullable();
            $table->dateTime('changed_at');
            $table->timestamps();

            // Indices
            $table->index(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'price_id']);
            $table->index(['changed_at']);

            // Foreign keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->foreign('price_id')
                ->references('id')
                ->on('prices')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');

            $table->foreign('changed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_histories');
    }
};
