<?php

namespace App\Modules\Pdv\Application;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Domain\Models\PdvOperatorSession;
use App\Modules\Pdv\Domain\OfflineLeaseKeys;
use App\Modules\Terminals\Application\MachineCredentialExpiration;
use App\Modules\Terminals\Domain\Models\Terminal;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Evidência assinada de que um operador foi legitimamente autenticado.
 *
 * O caixa precisa continuar vendendo quando a loja perde a internet, mas não
 * pode inventar autorização. O lease resolve só essa metade: ele prova que
 * ESTE operador estava autorizado NESTE caixa até ESTE instante, com uma
 * assinatura que o cliente verifica sem poder produzir.
 *
 * O que o lease NÃO é:
 *
 *   - não é credencial: não autentica requisição nenhuma contra o servidor;
 *   - não é login offline: um operador que nunca se autenticou online não tem
 *     lease, e não há caminho para obter um;
 *   - não é autorização comercial: as capacidades listadas nesta fase não
 *     incluem venda, desconto, pagamento, cancelamento, sangria ou fechamento.
 *     Essas pertencem às fases que as definirem, e emiti-las agora seria
 *     autorizar operação que ninguém revisou.
 *
 * O prazo é o MENOR entre a graça de política, o fim da sessão e a expiração
 * efetiva da credencial de máquina. Um lease nunca sobrevive à sessão nem à
 * máquina, e o relógio do cliente não participa da decisão.
 *
 * Sem par de chaves válido, ou com a feature flag desligada, o emissor devolve
 * null e o contrato publica `offline.enabled = false`. Não existe lease sem
 * assinatura.
 */
class OfflineLeaseIssuer
{
    /**
     * Capacidade única desta fase: um operador está autenticado neste caixa.
     *
     * Não autoriza operação comercial. Existe para o cliente distinguir
     * "sessão provada" de "sem ninguém no caixa", que é a decisão que ele
     * precisa tomar offline.
     */
    public const CAPACIDADE_SESSAO = 'pdv:operator:session';

    /** Prefixo de separação de domínio do texto assinado. */
    private const DOMINIO = 'pdv-offline-lease-v1';

    public function __construct(
        private MachineCredentialExpiration $expiracao,
        private OperatorAuthorization $autorizacao,
    ) {}

    public function habilitado(): bool
    {
        return (bool) config('pdv.offline.enabled') && OfflineLeaseKeys::fromConfig() !== null;
    }

    /**
     * Bloco `offline` do contrato público.
     *
     * Sempre presente, mesmo desligado: o Java precisa saber que não deve
     * contar com autorização offline, em vez de deduzir isso da ausência de um
     * campo.
     *
     * @return array<string, mixed>
     */
    public function payload(PdvOperatorSession $sessao, Terminal $terminal, ?User $operador, PersonalAccessToken $maquina): array
    {
        $chaves = OfflineLeaseKeys::fromConfig();

        if (! (bool) config('pdv.offline.enabled') || $chaves === null || $operador === null) {
            return ['enabled' => false];
        }

        $lease = $this->emitir($sessao, $terminal, $operador, $maquina, $chaves);

        if ($lease === null) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'format_version' => (int) config('pdv.offline.format_version'),
            'algorithm' => 'Ed25519',
            'key_id' => $chaves->keyId,
            // Cópia da chave pública para descobrir rotação. A recomendação
            // para o Java é fixar a chave na instalação e usar este campo
            // apenas para detectar que o `key_id` mudou.
            'public_key' => $chaves->publicKeyBase64,
            'grace_minutes' => (int) config('pdv.offline.grace_minutes'),
            'lease' => $lease['token'],
            'expires_at' => $lease['expires_at'],
        ];
    }

    /**
     * O lease em si, ou null quando a sessão não é elegível.
     *
     * @return array{token: string, expires_at: string}|null
     */
    private function emitir(
        PdvOperatorSession $sessao,
        Terminal $terminal,
        User $operador,
        PersonalAccessToken $maquina,
        OfflineLeaseKeys $chaves,
    ): ?array {
        // Reavalia a autorização em vez de confiar na sessão: emitir lease é
        // estender confiança para um período em que nada poderá ser conferido.
        $atribuicao = $this->autorizacao->atribuicaoAtiva($operador, $terminal);

        if (! $sessao->vigente() || $atribuicao === null) {
            return null;
        }

        $expira = $this->prazo($sessao, $maquina);

        if (! $expira->isFuture()) {
            return null;
        }

        $payload = [
            'format_version' => (int) config('pdv.offline.format_version'),
            'key_id' => $chaves->keyId,
            'session_id' => $sessao->id,
            'operator_id' => $operador->id,
            'terminal_id' => $terminal->id,
            'installation_id' => $terminal->installation_id,
            'tenant_id' => $terminal->tenant_id,
            'company_id' => $terminal->company_id,
            'branch_id' => $terminal->branch_id,
            // Identidade da credencial de máquina, não a credencial. O Java
            // compara com o id que recebeu na confirmação da rotação e descobre
            // que o lease ficou velho sem precisar do segredo.
            'machine_credential_reference' => (string) $maquina->id,
            'authorization_version' => $this->autorizacao->versao($operador, $terminal, $atribuicao),
            'capabilities' => [self::CAPACIDADE_SESSAO],
            'issued_at' => now()->utc()->toIso8601ZuluString(),
            'expires_at' => $expira->utc()->toIso8601ZuluString(),
        ];

        // Campos em ordem fixa, serializados uma única vez: a assinatura cobre
        // exatamente os bytes que o cliente recebe, então o Java verifica o
        // texto codificado e só depois o decodifica. Nenhum re-encode no meio.
        $corpo = $this->base64url((string) json_encode($payload, JSON_UNESCAPED_SLASHES));
        $cabecalho = self::DOMINIO.'.'.$chaves->keyId;
        $assinatura = $this->base64url($chaves->assinar($cabecalho.'.'.$corpo));

        return [
            'token' => $cabecalho.'.'.$corpo.'.'.$assinatura,
            'expires_at' => $payload['expires_at'],
        ];
    }

    private function prazo(PdvOperatorSession $sessao, PersonalAccessToken $maquina): CarbonImmutable
    {
        // A graça e o fim da sessão existem sempre; a expiração efetiva da
        // credencial de máquina só entra quando há uma — e, quando entra, pode
        // ser a menor das três.
        $prazo = CarbonImmutable::instance(now())
            ->addMinutes((int) config('pdv.offline.grace_minutes'))
            ->min(CarbonImmutable::instance($sessao->expires_at));

        $credencial = $this->expiracao->effective($maquina);

        return $credencial === null ? $prazo : $prazo->min($credencial);
    }

    private function base64url(string $bruto): string
    {
        return rtrim(strtr(base64_encode($bruto), '+/', '-_'), '=');
    }
}
