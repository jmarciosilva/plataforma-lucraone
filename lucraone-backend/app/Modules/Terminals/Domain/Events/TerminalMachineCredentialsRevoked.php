<?php

namespace App\Modules\Terminals\Domain\Events;

use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Todas as credenciais de máquina de um Terminal foram apagadas.
 *
 * Disparado do ponto único de revogação, dentro da transação dele. Acontece em
 * quatro situações, e as quatro são rupturas de confiança no equipamento:
 * recuperação administrativa, bloqueio, revogação definitiva e emissão direta
 * de uma credencial nova fora do protocolo de rotação.
 *
 * O contraste com TerminalCredentialRotated é o ponto: lá a credencial é
 * SUBSTITUÍDA por protocolo, com prova de que é a mesma instalação, e a sessão
 * humana sobrevive; aqui ela é APAGADA, e nada que dependia dela deveria
 * sobreviver sem reautenticação.
 *
 * Carrega fatos, não autoridade. Existe para que o módulo Terminals continue
 * sem conhecer o conceito de operador.
 */
final class TerminalMachineCredentialsRevoked
{
    use Dispatchable;

    public function __construct(
        public readonly Terminal $terminal,
        public readonly int $removidas,
    ) {}
}
