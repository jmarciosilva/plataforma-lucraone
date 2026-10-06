<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('ncm_code', 8)->nullable();
            $table->string('cest_code', 7)->nullable();
            $table->string('default_origin_code', 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['ncm_code', 'cest_code', 'default_origin_code']);
        });
    }
};
