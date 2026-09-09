<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('automation_rule_id')->nullable();
            $table->string('trigger', 60);

            // executed · skipped (condição não bateu) · failed
            $table->string('result', 20);
            $table->string('action', 60)->nullable();
            $table->string('message', 500)->nullable();

            // Payload do gatilho e o que a ação fez — a trilha de auditoria
            $table->json('payload')->nullable();
            $table->json('outcome')->nullable();

            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('ran_at');
            $table->timestamps();

            $table->index(['tenant_id', 'ran_at']);
            $table->index(['tenant_id', 'automation_rule_id']);
            $table->index(['tenant_id', 'result']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('automation_rule_id')->references('id')->on('automation_rules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_logs');
    }
};
