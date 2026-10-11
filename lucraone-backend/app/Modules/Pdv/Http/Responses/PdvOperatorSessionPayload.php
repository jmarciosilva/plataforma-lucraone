<?php

namespace App\Modules\Pdv\Http\Responses;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Application\OfflineLeaseIssuer;
use App\Modules\Pdv\Domain\Models\PdvOperatorSession;
use App\Modules\Terminals\Domain\Models\Terminal;

/**
 * Mapeia a sessão operacional para o contrato público.
 *
 * Campo por campo, pela mesma razão do PdvTerminalPayload: `toArray()` faria
 * qualquer coluna futura entrar no contrato sem ninguém decidir — e aqui uma
 * dessas colunas é `credential_hash`.
 *
 * O que NUNCA sai: hash da credencial, credencial de máquina, senha, id do PAT
 * de máquina em claro no corpo da sessão (ele existe apenas dentro do lease
 * assinado, como referência) e qualquer campo de outra sessão.
 *
 * A credencial da sessão é devolvida por `comCredencial()` e somente nos dois
 * caminhos que a produzem: início e recuperação. O GET não tem como devolvê-la
 * porque ela não existe fora daquele instante — o banco só tem o hash.
 */
final class PdvOperatorSessionPayload
{
    /**
     * @param  array<string, mixed>  $offline
     * @return array<string, mixed>
     */
    public static function para(
        PdvOperatorSession $sessao,
        Terminal $terminal,
        ?User $operador,
        array $offline,
    ): array {
        return [
            'session' => [
                // Identificador PÚBLICO. Serve para suporte e correlação de
                // log; não autentica nada.
                'id' => $sessao->id,
                'status' => $sessao->status,
                'authenticated_at' => $sessao->authenticated_at?->toIso8601String(),
                'last_revalidated_at' => $sessao->last_revalidated_at?->toIso8601String(),
                // Prazo ABSOLUTO. Não cresce em revalidação.
                'expires_at' => $sessao->expires_at?->toIso8601String(),
            ],
            'operator' => $operador === null ? null : [
                'id' => $operador->id,
                'name' => (string) $operador->name,
                'email' => (string) $operador->email,
            ],
            'context' => PdvTerminalPayload::para(
                $terminal,
                $terminal->tenant,
                $terminal->company,
                $terminal->branch,
            ),
            // Capacidades efetivas desta fase. Deliberadamente sem venda,
            // desconto, pagamento, cancelamento, sangria ou fechamento: elas
            // pertencem às fases que as definirem.
            'capabilities' => [OfflineLeaseIssuer::CAPACIDADE_SESSAO],
            'offline' => $offline,
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    public static function comCredencial(array $dados, string $credencial, PdvOperatorSession $sessao): array
    {
        $dados['credential'] = [
            'token_type' => 'PdvOperatorSession',
            // Vai no cabeçalho X-PDV-Operator-Session das rotas protegidas.
            // Entregue uma única vez; o servidor guarda apenas o SHA-256.
            'access_token' => $credencial,
            'expires_at' => $sessao->expires_at?->toIso8601String(),
        ];

        return $dados;
    }
}
