<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REST-02 — seções do cardápio de restaurante.
 *
 * Retrocompatível: não altera tabelas existentes.
 * Escopo: uma seção pertence a uma filial específica (branch_id).
 * RESTRICT na FK de filial: filial com seções não pode ser removida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_sections', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('branch_id');
            $table->string('tenant_id');
            $table->string('name', 120);
            $table->smallInteger('position')->default(0);
            $table->boolean('available')->default(true);
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->index(['tenant_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_sections');
    }
};
