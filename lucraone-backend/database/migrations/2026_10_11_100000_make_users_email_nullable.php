<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Torna `users.email` opcional, para que um operador de caixa possa existir sem
 * e-mail.
 *
 * Um caixa de lanchonete nao tem e-mail corporativo, e inventar um endereco
 * ficticio seria pior que nao ter: entraria no indice unico, poderia receber
 * notificacao, apareceria em relatorio e um dia alguem tentaria recuperar senha
 * por ele. A identidade continua sendo a mesma `users` — nao ha segunda tabela
 * de senhas nem sistema paralelo de pessoas.
 *
 * O indice unico PERMANECE. Em MySQL e em SQLite um indice unico aceita varios
 * NULL, porque NULL nao e igual a NULL: dois operadores sem e-mail convivem, e
 * dois com o mesmo e-mail continuam proibidos.
 *
 * Nada passa a aceitar e-mail vazio nos fluxos que o exigem. Login web, login
 * humano da API, cadastro administrativo de usuario, criacao de cliente e
 * promocao a Platform Admin continuam pedindo e-mail e continuam procurando
 * por ele — uma pessoa sem e-mail simplesmente nao entra por esses caminhos,
 * que e exatamente o desejado para quem so opera o caixa.
 *
 * Aditiva. O `down()` so reverte enquanto nao houver identidade sem e-mail, e
 * recusa com mensagem explicita em vez de inventar dado — ver abaixo. Isso faz
 * da reversao uma operacao com PRE-REQUISITO, nao uma operacao impossivel: quem
 * precisa reverter resolve primeiro as identidades sem e-mail (atribuindo
 * endereco ou arquivando a pessoa) e so entao executa o rollback. O
 * procedimento de implantacao registra esse passo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Voltar a coluna para NOT NULL exigiria decidir o que fazer com quem
        // nao tem e-mail: inventar um endereco, apagar a pessoa ou falhar. As
        // duas primeiras sao perda de dado disfarcada de rollback, entao a
        // migration falha alto e deixa a decisao para quem opera.
        $semEmail = (int) DB::table('users')->whereNull('email')->count();

        if ($semEmail > 0) {
            throw new RuntimeException(
                "Existem {$semEmail} identidades sem e-mail. Resolva-as antes de reverter esta migration."
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
