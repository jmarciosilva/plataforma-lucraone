<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendas recebidas do PDV (PDV-BE-06).
 *
 * O PDV Java grava a venda num outbox local e reenvia a cada 30s até receber
 * 2xx/409. A retaguarda deduplica pelo `idempotency_key` (UNIQUE) — é ele, e
 * não o `sale_id`, que garante um único registro por evento reenviado.
 *
 * tenant/company/branch/terminal são os VÍNCULOS do próprio Terminal
 * autenticado, resolvidos pelo contexto de máquina, nunca o que viesse no
 * corpo. Guardá-los aqui mantém a venda consultável por estabelecimento sem
 * confiar no cliente de loja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_received_sales', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('idempotency_key', 64)->unique();
            $table->string('event_type', 20)->index();
            $table->unsignedSmallInteger('payload_version')->default(1);

            // Identificador da venda no PDV (UUID do app Java); não é a PK.
            $table->string('sale_id', 64)->index();

            // Vínculos do Terminal autenticado.
            $table->char('tenant_id', 26)->index();
            $table->char('company_id', 26)->index();
            $table->char('branch_id', 26)->index();
            $table->char('terminal_id', 26)->index();

            // Operador informado pelo PDV (texto do caixa, sem FK: o perfil pode
            // mudar sem reescrever o histórico da venda).
            $table->string('operator_id', 64)->nullable();
            $table->string('operator_name')->nullable();

            $table->char('currency', 3)->default('BRL');
            $table->decimal('subtotal_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            // Pagamento primário. As entradas de split ficam na tabela própria.
            $table->string('payment_method', 20)->nullable();
            $table->decimal('payment_amount', 12, 2)->nullable();
            $table->decimal('change_amount', 12, 2)->nullable();
            $table->string('payment_declaration', 20)->nullable();

            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            // created_at: criação da venda no PDV (vem do payload).
            // received_at: instante em que a retaguarda aceitou o evento.
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('received_at');

            // Consulta do dashboard: vendas de um estabelecimento por período.
            $table->index(['tenant_id', 'branch_id', 'confirmed_at']);
            $table->index(['tenant_id', 'confirmed_at']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('terminal_id')->references('id')->on('terminals')->cascadeOnDelete();
        });

        Schema::create('pdv_received_sale_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('sale_id', 26)->index();
            $table->unsignedInteger('line_number');
            $table->string('product_id', 64)->nullable();
            $table->string('product_name');
            $table->string('sku', 64)->nullable();
            $table->string('unit', 10)->nullable();
            $table->decimal('quantity', 14, 4)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            $table->foreign('sale_id')->references('id')->on('pdv_received_sales')->cascadeOnDelete();
        });

        Schema::create('pdv_received_sale_payment_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('sale_id', 26)->index();
            $table->unsignedSmallInteger('seq');
            $table->string('method', 20);
            $table->decimal('amount', 12, 2)->default(0);

            $table->foreign('sale_id')->references('id')->on('pdv_received_sales')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_received_sale_payment_entries');
        Schema::dropIfExists('pdv_received_sale_items');
        Schema::dropIfExists('pdv_received_sales');
    }
};
