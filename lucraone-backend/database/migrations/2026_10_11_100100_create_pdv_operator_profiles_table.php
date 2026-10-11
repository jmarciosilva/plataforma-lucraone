<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil operacional de uma pessoa no caixa de UM estabelecimento.
 *
 * O nome de usuario do PDV e por estabelecimento, nao global: `marcio` pode ser
 * de uma pessoa na Loja Centro e de outra pessoa em outro cliente do LucraOne.
 * Por isso ele nao cabe em `users` — uma coluna global so aceitaria um `marcio`
 * no mundo — nem em `pdv_operator_branch_assignments`, que e por FILIAL e teria
 * de repetir o mesmo username em cada filial da mesma loja.
 *
 * `tenant_user` seria o outro candidato natural, e foi recusado de proposito: e
 * o pivo do modulo Identity, e enfiar nele o identificador de caixa faria o
 * Identity carregar conceito de PDV. Aqui o perfil tambem tem onde crescer
 * (preferencias operacionais) sem tocar na identidade.
 *
 * `status` e o do OPERADOR, separado de `users.status` (a conta) e de
 * `tenant_user.status` (o vinculo). Desativar um caixa nao pode mexer na conta
 * de alguem que tambem e gerente em outra loja.
 *
 * `username_canonical` e a forma usada para unicidade e para o login: minuscula,
 * sem acento e sem separadores. E ela que impede `jose.silva`, `josesilva` e
 * `José_Silva` de coexistirem no mesmo estabelecimento e de serem digitados
 * errado por quem esta no caixa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_operator_profiles', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('user_id', 26);
            // Forma de exibicao, ja normalizada em minuscula.
            $table->string('username', 32);
            // Forma de comparacao. Nunca exibida.
            $table->string('username_canonical', 32);
            $table->string('status', 16);
            $table->char('created_by', 26)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            // Referencia historica: arquivar quem cadastrou nao apaga o perfil.
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            // Nomes explicitos: os gerados pelo Laravel passariam dos 64
            // caracteres do MySQL, e o SQLite da suite aceitaria em silencio.
            $table->unique(['tenant_id', 'username_canonical'], 'pdv_operator_profile_username_unique');
            $table->unique(['tenant_id', 'user_id'], 'pdv_operator_profile_person_unique');
            $table->index(['tenant_id', 'status'], 'pdv_operator_profile_status_index');
        });
    }

    public function down(): void
    {
        // Descarta os identificadores operacionais: sem eles ninguem entra no
        // caixa. Encerrar as sessoes em andamento antes de executar.
        Schema::dropIfExists('pdv_operator_profiles');
    }
};
