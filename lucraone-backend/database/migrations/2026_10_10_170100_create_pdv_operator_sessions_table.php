<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessão operacional do caixa (Fase 5).
 *
 * Identidade própria, separada da sessão web, do PAT humano genérico e da
 * credencial de máquina. Reaproveitar um PAT humano para representar o turno de
 * caixa faria o token do operador valer em toda a API de negócio, e faria a
 * revogação do turno derrubar as outras sessões da pessoa — são escopos
 * diferentes, e um deles está preso a um computador específico.
 *
 * O `id` é o identificador PÚBLICO da sessão, usado em log, auditoria e lease.
 * Ele NÃO autentica nada. Quem autentica é o segredo opaco de 32 bytes
 * entregue uma única vez, do qual o banco guarda apenas o SHA-256 em
 * `credential_hash` — um dump do banco não permite autenticar como ninguém.
 *
 * A sessão é amarrada ao Terminal, à instalação e ao ID do PAT de máquina que a
 * abriu. É o vínculo com o PAT que faz a rotação ou a revogação da credencial
 * de máquina derrubar a sessão sem depender de nenhum evento: o PAT antigo
 * deixa de existir, e a revalidação por requisição não encontra mais a linha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_operator_sessions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('company_id', 26);
            $table->char('branch_id', 26);
            $table->char('terminal_id', 26);
            $table->uuid('installation_id');
            $table->char('user_id', 26);
            // Referência histórica ao PAT de máquina. Sem cascata, pelo mesmo
            // motivo de `terminal_credential_operations`: o Sanctum apaga
            // tokens, e apagar o histórico da sessão junto esconderia por que
            // ela deixou de valer.
            $table->unsignedBigInteger('machine_token_id');
            $table->char('credential_hash', 64)->unique();
            $table->string('status', 16);
            $table->timestamp('authenticated_at');
            // Última revalidação ONLINE bem-sucedida. É o marco do prazo
            // offline: a graça de 2 horas conta daqui, não do login.
            $table->timestamp('last_revalidated_at');
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
            $table->foreign('terminal_id')->references('id')->on('terminals')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');

            // Uma sessão ativa por Terminal é serializada por lock no
            // Terminal, não por índice: o MySQL 8 não tem índice único
            // parcial, e um único por (terminal_id, status) impediria duas
            // sessões ENDED no mesmo caixa — ou seja, o segundo turno do dia.
            $table->index(['terminal_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        // Rollback descarta o histórico de turnos. Encerrar as sessões em
        // andamento sob controle antes de executar.
        Schema::dropIfExists('pdv_operator_sessions');
    }
};
