<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Base das suítes de concorrência contra o MySQL descartável.
 *
 * Processos independentes, conexões independentes e uma barreira de socket que
 * solta todos no mesmo instante. É o único arranjo que exercita lock de verdade:
 * threads simuladas dentro de um processo compartilhariam a conexão, e o SQLite
 * em memória da suíte padrão não tem o comportamento de locking do MySQL — por
 * isso nada aqui vale como evidência fora deste ambiente.
 *
 * Extraída de CredentialConcurrencyTest quando a suíte de operador passou a
 * precisar do mesmo arranjo. Uma única cópia do harness: duas divergiriam, e um
 * harness de corrida que diverge produz teste que passa sem provar nada.
 */
abstract class ConcurrencyTestCase extends TestCase
{
    /**
     * Executa os trabalhos em paralelo e devolve o resultado de cada um.
     *
     * Cada filho só começa depois de receber o byte da barreira, então todos
     * disputam o mesmo lock. O resultado volta por socket em JSON; exceção
     * volta apenas como NOME DE CLASSE — mensagem de exceção pode carregar
     * binding de SQL, e binding pode carregar segredo.
     *
     * @param  array<int, callable>  $jobs
     * @return array<int, array<string, mixed>>
     */
    protected function race(array $jobs): array
    {
        DB::disconnect();
        $children = [];

        foreach ($jobs as $job) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            $pid = pcntl_fork();

            if ($pid === 0) {
                foreach ($children as $child) {
                    fclose($child[1]);
                }
                fclose($pair[0]);
                fread($pair[1], 1);
                DB::purge();

                try {
                    $result = ['ok' => true, 'result' => $job()];
                } catch (\Throwable $error) {
                    $result = ['ok' => false, 'type' => get_class($error)];
                }

                fwrite($pair[1], json_encode($result));
                fclose($pair[1]);
                exit(0);
            }

            $this->assertGreaterThan(0, $pid);
            fclose($pair[1]);
            $children[] = [$pid, $pair[0]];
        }

        foreach ($children as $child) {
            fwrite($child[1], 'x');
            stream_set_timeout($child[1], 30);
        }

        $results = [];

        foreach ($children as [$pid, $socket]) {
            $results[] = json_decode(stream_get_contents($socket), true);
            fclose($socket);
            pcntl_waitpid($pid, $status);
            $this->assertSame(0, pcntl_wexitstatus($status));
        }

        DB::purge();

        return $results;
    }
}
