<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REST-02 — opções individuais dentro de um grupo de modificadores.
 *
 * price_delta: variação de preço em relação ao preço do item.
 *   ADDITIONAL: >= 0 (validado na aplicação).
 *   REMOVAL:    = 0  (validado na aplicação — remoção não gera desconto no MVP).
 *   CHOICE:     >= 0 (validado na aplicação).
 *
 * CASCADE em group_id: grupo removido remove seus modificadores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifiers', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('group_id');
            $table->string('tenant_id');
            $table->string('name', 100);
            $table->decimal('price_delta', 10, 2)->default(0.00);
            $table->smallInteger('position')->default(0);
            $table->boolean('available')->default(true);
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('modifier_groups')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifiers');
    }
};
