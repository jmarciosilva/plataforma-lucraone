<?php

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

/**
 * Verificação de senha com tempo igual para conta existente e inexistente.
 *
 * Extraída do AuthController quando o login do operador de PDV passou a
 * precisar da mesma garantia. O comportamento é exatamente o que já existia na
 * API; o que mudou é haver um único lugar que o define, em vez de dois que
 * precisariam ser corrigidos juntos.
 *
 * Não decide se a pessoa pode entrar: responde apenas se a senha confere. Conta
 * ativa, vínculo, filial e permissão são perguntas de quem chama, e cada fluxo
 * tem as suas.
 */
class PasswordVerification
{
    /**
     * Hash descartável usado quando o e-mail não existe.
     *
     * Sem isto o `Hash::check` só rodava quando a conta existia, e a resposta
     * do e-mail inexistente voltava mais rápido — diferença suficiente para
     * mapear quais e-mails têm conta, mesmo com status e mensagem idênticos.
     * Comparar contra este hash iguala os dois caminhos.
     *
     * É bcrypt de uma string aleatória descartada na geração: não é a senha de
     * ninguém e não corresponde a conta nenhuma. Fica constante de propósito —
     * gerar um hash por requisição custaria o mesmo que o ataque que se quer
     * evitar.
     */
    private const HASH_INEXISTENTE = '$2y$12$8X.RJxrqQRCDcbW8d6blS.nf7gji5zMmS1dSHANCxEe8TNmzKpna6';

    /**
     * A senha confere para esta pessoa?
     *
     * `$usuario` nulo representa e-mail sem conta, e o caminho passa pela mesma
     * verificação de hash. O custo do bcrypt é o ponto, não um desperdício.
     */
    public function confere(?User $usuario, #[SensitiveParameter] string $senha): bool
    {
        if ($usuario === null) {
            Hash::check($senha, self::HASH_INEXISTENTE);

            return false;
        }

        // Cast explícito: o contrato deste método é booleano, e um `Hash::spy()`
        // — usado pelos testes que provam que os dois caminhos verificam hash —
        // devolve null. Sem o cast, o dublê quebraria o tipo de retorno em vez
        // de exercitar o comportamento.
        return (bool) Hash::check($senha, $usuario->password);
    }
}
