<?php

namespace App\Modules\Identity\Infrastructure\Console;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Promove uma identidade global a Platform Admin (ONB-01A).
 *
 * O marcador não é mass assignable e nenhuma tela o escreve, por decisão do
 * SEC-04. Até aqui o único caminho era Tinker ou SQL no servidor: sem rastro
 * na aplicação e sem nada que impedisse a mão escorregar para outra coluna.
 *
 * Este comando é cirúrgico de propósito: encontra a identidade pelo e-mail e
 * grava `is_platform_admin`. Não cria conta, não mexe em senha, status, vínculo
 * nem papel de estabelecimento — e não aceita opção que o leve a fazer isso.
 *
 * A autoridade concedida é global e não se desfaz por tela, então por padrão o
 * comando mostra de quem é a identidade e pergunta antes de gravar. `--force`
 * existe para automação, onde não há ninguém para responder.
 *
 * Platform Admin não é Tenant Admin: ele administra os clientes do LucraOne e
 * não recebe nenhuma permissão dentro de um estabelecimento.
 */
class PromoverPlatformAdminCommand extends Command
{
    /**
     * A pergunta vive numa constante para que o teste não dependa de uma
     * cópia do texto.
     */
    public const PERGUNTA_DE_CONFIRMACAO = 'confirma conceder autoridade global de Platform Admin a esta identidade?';

    protected $signature = 'plataforma:promover
        {email : e-mail da identidade global que passa a administrar a plataforma}
        {--force : concede sem pedir confirmação, para uso não interativo}';

    protected $description = 'Concede a autoridade de Platform Admin a uma identidade já existente';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        $usuario = User::where('email', $email)->first();

        // Recusas vêm antes de qualquer pergunta: não se confirma o que não
        // existe nem o que não pode ser promovido.
        if (! $usuario) {
            return $this->recusar($email);
        }

        // Já concedida: nada a confirmar e nada a gravar. Uma segunda execução
        // não deve sequer tocar o updated_at.
        if ($usuario->isPlatformAdmin()) {
            $this->info("{$usuario->email} já é Platform Admin. nada foi alterado.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirmarConcessao($usuario)) {
            $this->comment('operação cancelada. nada foi alterado.');

            return self::SUCCESS;
        }

        $usuario->forceFill(['is_platform_admin' => true])->save();

        // Sem e-mail e sem nome: o `user_id` identifica a linha para quem
        // audita, e o log não precisa carregar dado pessoal para isso.
        Log::info('platform admin concedido', [
            'user_id' => $usuario->id,
            'origem' => 'console',
            'comando' => 'plataforma:promover',
        ]);

        $this->info("{$usuario->email} agora é Platform Admin.");
        $this->line('  autoridade concedida: administrar os clientes do LucraOne (/tenants).');
        $this->line('  papéis e vínculos de estabelecimento não foram alterados.');

        return self::SUCCESS;
    }

    /**
     * Mostra de quem é a identidade e o que está sendo concedido.
     *
     * O e-mail digitado pode ser de outra pessoa com endereço parecido, então
     * o nome aparece junto: é o que permite perceber o erro antes de gravar.
     */
    private function confirmarConcessao(User $usuario): bool
    {
        $this->newLine();
        $this->line("  identidade: {$usuario->name}");
        $this->line("  e-mail:     {$usuario->email}");
        $this->newLine();
        $this->warn('  esta operação concede autoridade GLOBAL de Platform Admin.');
        $this->line('  quem a recebe passa a administrar todos os clientes do LucraOne,');
        $this->line('  e nenhuma tela do painel desfaz essa concessão.');
        $this->newLine();

        // Padrão negativo: um Enter distraído não concede nada.
        return $this->confirm(self::PERGUNTA_DE_CONFIRMACAO, false);
    }

    /**
     * Identidade inexistente ou arquivada: nada é gravado e o motivo é dito.
     *
     * O comando não cria conta. Criar identidade é operação do painel, com
     * nome, senha e vínculo — não de um comando de plataforma.
     */
    private function recusar(string $email): int
    {
        $arquivada = User::onlyTrashed()->where('email', $email)->exists();

        if ($arquivada) {
            $this->error("a identidade {$email} está arquivada. restaure-a pelo painel antes de promover.");

            return self::FAILURE;
        }

        $this->error("nenhuma identidade encontrada para {$email}.");
        $this->line('  o comando não cria usuário: cadastre a pessoa pelo painel e promova depois.');

        return self::FAILURE;
    }
}
