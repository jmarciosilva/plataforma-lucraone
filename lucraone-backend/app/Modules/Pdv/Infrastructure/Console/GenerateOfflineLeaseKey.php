<?php

namespace App\Modules\Pdv\Infrastructure\Console;

use Illuminate\Console\Command;

/**
 * Gera um par Ed25519 para o lease offline e IMPRIME na saída.
 *
 * Não escreve em `.env`, não grava arquivo, não guarda nada e não roda sozinho:
 * precisa ser invocado explicitamente, com `--confirmar`. A política é que
 * chave privada operacional não seja criada nem publicada automaticamente —
 * um comando que editasse o `.env` do servidor faria exatamente isso, e o
 * material acabaria em backup, log de deploy e histórico de shell sem ninguém
 * decidir.
 *
 * Quem opera recebe os três valores, guarda a privada no gerenciador de
 * segredos, distribui a pública ao cliente Java e configura o `key_id`. A
 * rotação é trocar o `key_id` junto do par: o lease carrega o identificador,
 * então o cliente sabe qual chave usar e detecta que a chave mudou.
 */
final class GenerateOfflineLeaseKey extends Command
{
    protected $signature = 'pdv:offline-lease-key {--confirmar : Confirma a geração e a impressão do material}';

    protected $description = 'Print a new Ed25519 key pair for the PDV offline lease (never stored by this command)';

    public function handle(): int
    {
        if (! $this->option('confirmar')) {
            $this->error('Geração não confirmada. Repita com --confirmar, em terminal e host apropriados.');
            $this->line('A chave privada será IMPRESSA; não execute em sessão registrada ou compartilhada.');

            return self::FAILURE;
        }

        $par = sodium_crypto_sign_keypair();

        $this->line('PDV_OFFLINE_LEASE_KEY_ID='.now()->utc()->format('Ymd').'-1');
        $this->line('PDV_OFFLINE_LEASE_PRIVATE_KEY='.base64_encode(sodium_crypto_sign_secretkey($par)));
        $this->line('PDV_OFFLINE_LEASE_PUBLIC_KEY='.base64_encode(sodium_crypto_sign_publickey($par)));
        $this->newLine();
        $this->warn('Guarde a chave privada apenas no backend. Habilite PDV_OFFLINE_LEASE_ENABLED somente depois.');

        return self::SUCCESS;
    }
}
