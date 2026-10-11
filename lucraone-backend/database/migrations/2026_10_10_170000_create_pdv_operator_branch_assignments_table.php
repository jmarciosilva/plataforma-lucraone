<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atribuição explícita de um operador a uma filial (Fase 5).
 *
 * O backend já vinculava pessoa a estabelecimento (`tenant_user`) e já tinha
 * permissões por estabelecimento, mas nada dizia EM QUAL FILIAL alguém pode
 * operar um caixa. Sem esta tabela, `access-pdv` autorizaria todas as filiais
 * do estabelecimento — inclusive as que a pessoa nunca viu.
 *
 * Uma linha por (estabelecimento, pessoa, filial), garantida pelo índice único:
 * conceder de novo depois de revogar reativa a MESMA linha, em vez de criar uma
 * segunda e deixar ambíguo qual delas vale. O histórico de quem concedeu, quem
 * revogou e quando fica no AuditLog, que é o registro de rastreabilidade do
 * projeto; duplicá-lo aqui criaria duas verdades.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_operator_branch_assignments', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('user_id', 26);
            $table->char('branch_id', 26);
            $table->string('status', 16);
            $table->char('granted_by', 26)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
            // Quem concedeu é referência histórica: arquivar o administrador
            // não pode apagar a concessão nem travar a remoção da conta dele.
            $table->foreign('granted_by')->references('id')->on('users')->onDelete('set null');

            // Impede duplicidade e é a chave de leitura do login: a
            // revalidação procura exatamente por esta tripla.
            //
            // O nome é explícito porque o gerado pelo Laravel
            // (`pdv_operator_branch_assignments_tenant_id_user_id_branch_id_unique`,
            // 66 caracteres) passa do limite de 64 do MySQL e a migration falha
            // lá — o SQLite da suíte padrão aceita e esconderia o problema.
            $table->unique(['tenant_id', 'user_id', 'branch_id'], 'pdv_operator_branch_unique');
            // Listagem administrativa de operadores de uma filial.
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        // Rollback descarta as concessões; nenhuma sessão operacional
        // sobrevive sem elas. Desabilitar as rotas antes.
        Schema::dropIfExists('pdv_operator_branch_assignments');
    }
};
