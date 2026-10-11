<?php

namespace App\Modules\Terminals\Application;

use App\Modules\Terminals\Domain\Events\TerminalMachineCredentialsRevoked;
use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Domain\Models\TerminalCredentialOperation;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($terminal) {
            Terminal::withoutGlobalScopes()->whereKey($terminal->id)->lockForUpdate()->firstOrFail();
            TerminalCredentialOperation::where('terminal_id', $terminal->id)
                ->whereIn('status', ['ISSUED', 'PENDING'])->update(['status' => 'INVALIDATED', 'updated_at' => now()]);

            $removidas = $terminal->tokens()->delete();

            // Dentro da transação: quem depende destas credenciais precisa
            // perder validade no MESMO commit. Terminals não sabe o que é um
            // operador; quem liga as duas coisas é o listener do módulo Pdv.
            TerminalMachineCredentialsRevoked::dispatch($terminal, (int) $removidas);

            return $removidas;
        }, 3);
    }
}
