<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Politica operacional do PDV por estabelecimento.
 *
 * Nasce com um unico ajuste, o bloqueio de tela por inatividade, porque e o
 * unico que o produto pediu: o lojista decide se o caixa pede a senha de novo
 * depois de alguns minutos parado. NULL significa desligado, que e o padrao do
 * piloto.
 *
 * Bloqueio de tela NAO e encerramento de sessao nem fechamento de caixa: a
 * sessao operacional continua valida, e o aplicativo apenas exige confirmacao
 * de identidade para voltar a operar. O backend publica o numero no contrato e
 * nao o aplica — quem bloqueia a tela e o cliente, que e quem tem tela.
 *
 * Uma linha por estabelecimento, criada sob demanda. A ausencia de linha e um
 * estado valido e significa "tudo no padrao".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_tenant_settings', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->unique();
            // Minutos de inatividade antes de a tela bloquear. NULL = desligado.
            $table->unsignedSmallInteger('screen_lock_minutes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_tenant_settings');
    }
};
