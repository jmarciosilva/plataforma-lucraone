<?php

use Brick\Math\BigDecimal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prices', function (Blueprint $table) {
            $table->decimal('reference_cost_amount', 12, 2)->nullable();
            $table->decimal('effective_margin_percentage', 18, 4)->nullable();
        });
        Schema::table('price_histories', function (Blueprint $table) {
            $table->dropForeign(['price_id']);
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['product_id']);
            $table->decimal('old_amount', 12, 2)->nullable()->change();
            $table->decimal('new_amount', 12, 2)->nullable()->change();
            $table->ulid('price_id')->nullable()->change();
            $table->string('price_type', 20)->nullable();
            $table->string('event_type', 32)->nullable();
            $table->decimal('old_reference_cost_amount', 12, 2)->nullable();
            $table->decimal('new_reference_cost_amount', 12, 2)->nullable();
            $table->decimal('old_effective_margin_percentage', 18, 4)->nullable();
            $table->decimal('new_effective_margin_percentage', 18, 4)->nullable();
            $table->foreign('price_id')->references('id')->on('prices')->nullOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->index(['tenant_id', 'product_id', 'currency', 'price_type', 'changed_at', 'id'], 'price_histories_product_timeline_idx');
            $table->index(['tenant_id', 'changed_at', 'id'], 'price_histories_tenant_timeline_idx');
        });

        // Recupera somente a identidade verificável; não reconstrói custo/margem.
        DB::table('price_histories as h')->join('prices as p', function ($join) {
            $join->on('h.price_id', '=', 'p.id')->on('h.tenant_id', '=', 'p.tenant_id')
                ->on('h.product_id', '=', 'p.product_id')->on('h.currency', '=', 'p.currency');
        })->whereIn('p.type', ['cost', 'sale', 'suggested_retail'])
            ->select(['h.id', 'h.old_amount', 'h.new_amount', 'p.type'])
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $event = $row->old_amount !== null && $row->new_amount !== null
                        && BigDecimal::of((string) $row->old_amount)->compareTo((string) $row->new_amount) !== 0
                        ? 'amount_changed' : null;
                    DB::table('price_histories')->where('id', $row->id)->update(['price_type' => $row->type, 'event_type' => $event]);
                }
            }, 'h.id', 'id');
    }

    public function down(): void
    {
        if (DB::table('price_histories')->whereNull('old_amount')->orWhereNull('new_amount')->orWhereNull('price_id')
            ->orWhereNotNull('old_reference_cost_amount')->orWhereNotNull('new_reference_cost_amount')
            ->orWhereNotNull('old_effective_margin_percentage')->orWhereNotNull('new_effective_margin_percentage')->exists()
            || DB::table('prices')->whereNotNull('reference_cost_amount')->orWhereNotNull('effective_margin_percentage')->exists()) {
            throw new RuntimeException('Rollback descartaria snapshots financeiros ou eventos históricos. Preserve esses dados antes de reverter.');
        }
        Schema::table('price_histories', function (Blueprint $table) {
            $table->dropIndex('price_histories_product_timeline_idx');
            $table->dropIndex('price_histories_tenant_timeline_idx');
            $table->dropForeign(['price_id']);
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['product_id']);
            $table->decimal('old_amount', 12, 2)->nullable(false)->change();
            $table->decimal('new_amount', 12, 2)->nullable(false)->change();
            $table->ulid('price_id')->nullable(false)->change();
            $table->dropColumn(['price_type', 'event_type', 'old_reference_cost_amount', 'new_reference_cost_amount', 'old_effective_margin_percentage', 'new_effective_margin_percentage']);
            $table->foreign('price_id')->references('id')->on('prices')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
        Schema::table('prices', fn (Blueprint $table) => $table->dropColumn(['reference_cost_amount', 'effective_margin_percentage']));
    }
};
