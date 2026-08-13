<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->index();
            $table->char('user_id', 26)->nullable()->index();
            $table->string('action'); // create, update, delete, login, logout
            $table->string('entity_type'); // User, Role, Permission, etc
            $table->char('entity_id', 26)->nullable();
            $table->json('changes')->nullable(); // old_values, new_values
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id')->nullable()->index();
            $table->string('endpoint')->nullable();
            $table->string('method')->nullable(); // GET, POST, PUT, DELETE
            $table->integer('status_code')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['tenant_id', 'entity_type']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
