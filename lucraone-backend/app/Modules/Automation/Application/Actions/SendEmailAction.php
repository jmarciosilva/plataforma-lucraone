<?php

namespace App\Modules\Automation\Application\Actions;

use App\Modules\Automation\Domain\EmailRecipients;
use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Automation\Http\Rules\DestinatariosDeEmail;
use App\Modules\Automation\Infrastructure\Mail\AutomationAlertMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Envia e-mail para os destinatários configurados na regra.
 *
 * O envio entra na fila do Mailable (ShouldQueue), então uma caixa lenta não
 * segura a execução das outras regras.
 *
 * Os limites daqui (SEC-05) tratam abuso de envio, não permissão: quem pode
 * criar regra é decidido pela `AutomationRulePolicy` com `manage-automations`.
 * Um operador legítimo também erra — uma regra em gatilho movimentado com dez
 * destinatários vira volume de e-mail que queima a reputação do domínio
 * remetente. Por isso o freio fica aqui, junto de quem envia, e não na rota.
 */
class SendEmailAction implements ActionHandler
{
    public const CHAVE = 'send_email';

    /**
     * Execuções desta regra na janela.
     *
     * Os três gatilhos existentes — produto criado, estoque baixo e pedido
     * concluído — nascem de ação humana; nenhum é agendado ou de alta
     * frequência. Uma por minuto sustentada já está muito acima de qualquer
     * cadência operacional real e ainda segura uma regra desgovernada.
     */
    public const EXECUCOES_POR_REGRA = 60;

    /**
     * Entregas do estabelecimento na janela.
     *
     * Conta destinatários, não execuções: é a entrega que gasta reputação do
     * remetente. Impede que somar regras multiplique o volume — com o limite
     * por regra sozinho, dez regras entregariam 6.000 e-mails por hora.
     */
    public const ENTREGAS_POR_TENANT = 500;

    public const JANELA_SEGUNDOS = 3600;

    public const MOTIVO_LIMITE = 'limite de envio de e-mail da automação atingido; nenhum e-mail foi enviado.';

    public function __construct(
        private PlaceholderInterpolator $interpolador
    ) {}

    public static function rotulo(): string
    {
        return 'enviar e-mail';
    }

    public static function regrasDeConfiguracao(): array
    {
        return [
            // max:1000 continua como freio barato de tamanho; o limite que
            // vale é a quantidade de destinatários, em DestinatariosDeEmail.
            'action_config.recipients' => ['required', 'string', 'max:1000', new DestinatariosDeEmail],
            'action_config.subject' => ['required', 'string', 'max:255'],
            'action_config.message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** Chave do limite desta regra. Inclui o estabelecimento para não colidir. */
    public static function chaveDaRegra(AutomationRule $regra): string
    {
        return "automation-email:rule:{$regra->tenant_id}:{$regra->id}";
    }

    /** Chave do orçamento de entregas do estabelecimento. */
    public static function chaveDoTenant(string $tenantId): string
    {
        return "automation-email:tenant:{$tenantId}";
    }

    public function executar(AutomationRule $regra, array $payload): array
    {
        // Relê a configuração persistida em vez de confiar nela: regras
        // gravadas antes desta política, ou por import futuro, podem estar
        // fora do limite.
        $destinatarios = EmailRecipients::deConfiguracao(
            $regra->action_config['recipients'] ?? null
        );

        if ($motivo = $destinatarios->motivoDeRecusa()) {
            throw new \InvalidArgumentException($motivo);
        }

        $this->garantirQueCabeNoLimite($regra, $destinatarios->quantidade());

        $assunto = $this->interpolador->aplicar(
            (string) ($regra->action_config['subject'] ?? $regra->name),
            $payload
        );

        $mensagem = $this->interpolador->aplicar(
            (string) ($regra->action_config['message'] ?? ''),
            $payload
        );

        $this->registrarConsumo($regra, $destinatarios->quantidade());

        Mail::to($destinatarios->lista())->send(new AutomationAlertMail(
            assunto: $assunto,
            mensagem: $mensagem,
            regra: $regra->name,
            gatilho: $regra->triggerLabel(),
            dados: $payload,
        ));

        // Só a contagem: a lista de endereços é dado de contato das pessoas
        // notificadas e não precisa ficar no histórico de execuções, que é
        // consultável pelo painel.
        return [
            'recipient_count' => $destinatarios->quantidade(),
            'subject' => $assunto,
        ];
    }

    /**
     * Recusa antes de enviar qualquer coisa.
     *
     * A exceção é capturada pelo RuleEngine, que grava a execução como
     * `failed` com este motivo e segue para as outras regras — sem relançar.
     * Isso importa: o gatilho roda num listener enfileirado com `tries = 3`,
     * e um bloqueio que escapasse como exceção faria a fila repetir a
     * passagem inteira.
     *
     * O orçamento do estabelecimento considera as entregas desta execução, em
     * vez de só o total corrente: enviar para metade da lista e cortar o resto
     * seria pior que não enviar.
     */
    private function garantirQueCabeNoLimite(AutomationRule $regra, int $entregas): void
    {
        $porRegra = RateLimiter::tooManyAttempts(
            self::chaveDaRegra($regra),
            self::EXECUCOES_POR_REGRA
        );

        $porTenant = RateLimiter::attempts(self::chaveDoTenant($regra->tenant_id)) + $entregas
            > self::ENTREGAS_POR_TENANT;

        if ($porRegra || $porTenant) {
            throw new \RuntimeException(self::MOTIVO_LIMITE);
        }
    }

    private function registrarConsumo(AutomationRule $regra, int $entregas): void
    {
        RateLimiter::increment(self::chaveDaRegra($regra), self::JANELA_SEGUNDOS);
        RateLimiter::increment(self::chaveDoTenant($regra->tenant_id), self::JANELA_SEGUNDOS, $entregas);
    }
}
