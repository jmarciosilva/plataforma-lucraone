<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessao operacional continua: separa prazo TECNICO de jornada OPERACIONAL.
 *
 * A entrega anterior usava `expires_at` como prazo absoluto de oito horas, e o
 * turno morria no meio da tarde de um comerciante que abre as sete e fecha as
 * vinte e duas. A partir daqui `expires_at` e um prazo DESLIZANTE: vale algumas
 * horas, e avanca a cada uso autenticado legitimo. A jornada nao tem teto fixo;
 * a credencial continua tendo prazo, e portanto nunca e eterna.
 *
 * `absolute_expires_at` e o teto opcional, para quem quiser um: NULL significa
 * sem limite de jornada, que e a politica do piloto. Quando preenchido, nenhuma
 * renovacao passa dele.
 *
 * `machine_rotations` conta quantas vezes a credencial de maquina foi
 * rotacionada por baixo desta sessao. Nao e controle de acesso — e rastro: uma
 * sessao que atravessou varias rotacoes e um fato operacional que a auditoria
 * precisa mostrar sem precisar cruzar tabelas.
 *
 * Aditiva: nenhuma coluna existente muda de tipo ou de nulidade. Sessoes ja
 * gravadas continuam validas e simplesmente nascem sem teto absoluto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_operator_sessions', function (Blueprint $table) {
            $table->timestamp('absolute_expires_at')->nullable()->after('expires_at');
            $table->unsignedInteger('machine_rotations')->default(0)->after('machine_token_id');
        });
    }

    public function down(): void
    {
        Schema::table('pdv_operator_sessions', function (Blueprint $table) {
            $table->dropColumn(['absolute_expires_at', 'machine_rotations']);
        });
    }
};
