<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_catalog_publications', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->index();
            $table->char('company_id', 26)->index();
            $table->char('created_by', 26)->nullable()->index();
            $table->unsignedBigInteger('version');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->string('status', 20)->index();
            $table->unsignedInteger('product_count')->default(0);
            $table->unsignedInteger('price_count')->default(0);
            $table->unsignedInteger('package_count')->default(0);
            $table->char('checksum', 64)->nullable();
            $table->text('failure_code')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'version']);
            $table->index(['tenant_id', 'company_id', 'created_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('pdv_catalog_deliveries', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('publication_id', 26)->index();
            $table->char('terminal_id', 26)->index();
            $table->string('status', 20)->index();
            $table->unsignedInteger('last_part')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->unsignedBigInteger('applied_version')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestamps();
            $table->unique(['publication_id', 'terminal_id']);
            $table->index(['terminal_id', 'status']);
            $table->foreign('publication_id')->references('id')->on('pdv_catalog_publications')->cascadeOnDelete();
            $table->foreign('terminal_id')->references('id')->on('terminals')->cascadeOnDelete();
        });

        Schema::create('pdv_catalog_snapshot_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('publication_id', 26);
            $table->unsignedInteger('position');
            $table->longText('payload');
            $table->char('checksum', 64);
            $table->unique(['publication_id', 'position']);
            $table->foreign('publication_id')->references('id')->on('pdv_catalog_publications')->cascadeOnDelete();
        });

        Schema::create('pdv_terminal_catalog_states', function (Blueprint $table) {
            $table->char('terminal_id', 26)->primary();
            $table->char('publication_id', 26)->index();
            $table->unsignedBigInteger('version');
            $table->timestamp('applied_at');
            $table->timestamps();
            $table->foreign('terminal_id')->references('id')->on('terminals')->cascadeOnDelete();
            $table->foreign('publication_id')->references('id')->on('pdv_catalog_publications')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_terminal_catalog_states');
        Schema::dropIfExists('pdv_catalog_snapshot_items');
        Schema::dropIfExists('pdv_catalog_deliveries');
        Schema::dropIfExists('pdv_catalog_publications');
    }
};
