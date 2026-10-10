<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_credential_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('terminal_id', 26);
            $table->char('tenant_id', 26);
            $table->char('company_id', 26);
            $table->char('branch_id', 26);
            $table->uuid('installation_id');
            $table->string('kind', 16);
            $table->string('status', 16);
            // Historical references, deliberately not cascading with PAT deletion.
            $table->unsignedBigInteger('previous_token_id')->nullable();
            $table->unsignedBigInteger('candidate_token_id')->nullable()->unique();
            $table->char('delivery_secret_hash', 64)->nullable();
            $table->char('selector', 6)->nullable()->unique();
            $table->char('code_hash', 64)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->char('authorized_by', 26)->nullable();
            $table->string('reason', 32)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->foreign('terminal_id')->references('id')->on('terminals')->restrictOnDelete();
            $table->index(['terminal_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        // Explicit rollback discards lifecycle metadata; disable routes first.
        Schema::dropIfExists('terminal_credential_operations');
    }
};
