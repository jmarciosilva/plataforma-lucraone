<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Execute the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('status', ['TRIAL', 'ACTIVE', 'SUSPENDED', 'CANCELLED'])->default('TRIAL');
            $table->string('plan')->default('free');
            $table->string('timezone')->default('America/Sao_Paulo');
            $table->string('locale')->default('pt-BR');
            $table->string('currency')->default('BRL');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('status');
            $table->index('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
