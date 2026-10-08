<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_pairing_codes', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('terminal_id', 26)->index();
            $table->char('selector', 6)->unique();
            $table->char('code_hash', 64);
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();
            $table->foreign('terminal_id')->references('id')->on('terminals')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_pairing_codes');
    }
};
