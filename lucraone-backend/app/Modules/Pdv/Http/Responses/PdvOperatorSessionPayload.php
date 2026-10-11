<?php

namespace App\Modules\Pdv\Http\Responses;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Application\OfflineLeaseIssuer;
use App\Modules\Pdv\Domain\Models\PdvOperatorProfile;
use App\Modules\Pdv\Domain\Models\PdvOperatorSession;
use App\Modules\Pdv\Domain\Models\PdvTenantSetting;
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
        $perfil = $operador === null
            ? null
            : PdvOperatorProfile::para($terminal->tenant_id, $operador->id);

        return [
            'session' => [
                // Identificador PÚBLICO. Serve para suporte e correlação de
                // log; não autentica nada.
                'id' => $sessao->id,
                'status' => $sessao->status,
                'authenticated_at' => $sessao->authenticated_at?->toIso8601String(),
                'last_revalidated_at' => $sessao->last_revalidated_at?->toIso8601String(),
                // Prazo TÉCNICO, deslizante: avança a cada uso autenticado
                // legítimo. O cliente não deve tratá-lo como fim do turno.
                'expires_at' => $sessao->expires_at?->toIso8601String(),
                // Teto da JORNADA. Null significa sem teto — o turno dura o
                // dia inteiro enquanto a autorização continuar valendo.
                'absolute_expires_at' => $sessao->absolute_expires_at?->toIso8601String(),
                // Quantas rotações de credencial de máquina este turno
                // atravessou. Rastro operacional, não controle de acesso.
                'machine_rotations' => (int) $sessao->machine_rotations,
            ],
            'operator' => $operador === null ? null : [
                'id' => $operador->id,
                'name' => (string) $operador->name,
                // Identificador operacional. É o que o caixa digita.
                'username' => $perfil?->username,
                // Pode ser null: operador de caixa não precisa de e-mail, e
                // inventar um seria pior que não ter.
                'email' => $operador->email,
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
            'policy' => self::politica($terminal),
            'offline' => $offline,
        ];
    }

    /**
     * Política operacional que o CLIENTE aplica.
     *
     * `screen_lock_minutes` null significa bloqueio de tela desligado, que é o
     * padrão do piloto. Bloqueio de tela não é logout: a sessão continua
     * valendo, o aplicativo apenas pede confirmação de identidade para voltar a
     * operar, e nada de caixa financeiro é afetado.
     *
     * `sliding_minutes` é publicado para o cliente saber de quanto em quanto
     * tempo vale a pena revalidar. Não é promessa de validade: quem decide é o
     * `session.expires_at` de cada resposta.
     *
     * @return array<string, mixed>
     */
    private static function politica(Terminal $terminal): array
    {
        return [
            'screen_lock_minutes' => PdvTenantSetting::para($terminal->tenant_id)->bloqueioDeTela(),
            'sliding_minutes' => (int) config('pdv.operator_sessions.sliding_minutes'),
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
            // Prazo TÉCNICO da credencial, que desliza com o uso. Não é o fim
            // do turno — o turno acaba no logout, na revogação ou no teto da
            // jornada, quando existe.
            'expires_at' => $sessao->expires_at?->toIso8601String(),
        ];

        return $dados;
    }
}
