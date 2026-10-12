<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_catalog_deliveries', function (Blueprint $table) {
            $table->timestamp('manifest_seen_at')->nullable()->after('last_part');
            $table->timestamp('transfer_verified_at')->nullable()->after('manifest_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('pdv_catalog_deliveries', function (Blueprint $table) {
            $table->dropColumn(['manifest_seen_at', 'transfer_verified_at']);
        });
    }
};
