<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->string('name');
            $table->string('slug')->unique('categories_tenant_id_slug_unique');
            $table->text('description')->nullable();
            $table->ulid('parent_id')->nullable(); // For hierarchy
            $table->timestamps();
            $table->softDeletes();

            // Indices
            $table->index(['tenant_id', 'parent_id']);
            $table->index(['tenant_id', 'slug']);

            // Foreign keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->foreign('parent_id')
                ->references('id')
                ->on('categories')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
