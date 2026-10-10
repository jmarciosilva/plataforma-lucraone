<?php

namespace App\Modules\Terminals\Application;

use App\Modules\Terminals\Domain\Models\Terminal;

/**
 * Apaga as credenciais de máquina de um Terminal.
 *
 * Ponto único de deleção de PAT de Terminal. Espalhar `tokens()->delete()` pelo
 * código faria com que cada chamador decidisse por conta própria o que contar
 * como "credencial do Terminal", e é exatamente aí que um filtro largo apaga
 * token de pessoa.
 *
 * O alcance vem da relação polimórfica do tokenable: a morphMany do
 * HasApiTokens restringe por tokenable_type = Terminal E tokenable_id = este
 * Terminal. Nunca por nome do token, ability ou tenant_id — nenhum desses
 * distingue um sujeito de outro, e `name` é escolha nossa, não identidade.
 */
class RevokeTerminalMachineCredentials
{
    /**
     * @return int quantidade de credenciais removidas
     */
    public function revoke(Terminal $terminal): int
    {
        return $terminal->tokens()->delete();
    }
}
