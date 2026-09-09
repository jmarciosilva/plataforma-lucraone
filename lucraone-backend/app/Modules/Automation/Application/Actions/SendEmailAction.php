<?php

namespace App\Modules\Automation\Application\Actions;

use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Automation\Infrastructure\Mail\AutomationAlertMail;
use Illuminate\Support\Facades\Mail;

/**
 * Envia e-mail para os destinatários configurados na regra.
 *
 * O envio entra na fila do Mailable (ShouldQueue), então uma caixa lenta não
 * segura a execução das outras regras.
 */
class SendEmailAction implements ActionHandler
{
    public const CHAVE = 'send_email';

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
            'action_config.recipients' => ['required', 'string', 'max:1000'],
            'action_config.subject' => ['required', 'string', 'max:255'],
            'action_config.message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function executar(AutomationRule $regra, array $payload): array
    {
        $destinatarios = $this->destinatarios($regra);

        if ($destinatarios === []) {
            throw new \InvalidArgumentException('nenhum destinatário válido configurado na regra.');
        }

        $assunto = $this->interpolador->aplicar(
            (string) ($regra->action_config['subject'] ?? $regra->name),
            $payload
        );

        $mensagem = $this->interpolador->aplicar(
            (string) ($regra->action_config['message'] ?? ''),
            $payload
        );

        Mail::to($destinatarios)->send(new AutomationAlertMail(
            assunto: $assunto,
            mensagem: $mensagem,
            regra: $regra->name,
            gatilho: $regra->triggerLabel(),
            dados: $payload,
        ));

        return [
            'recipients' => $destinatarios,
            'subject' => $assunto,
        ];
    }

    /**
     * Aceita e-mails separados por vírgula, ponto e vírgula ou quebra de linha.
     */
    private function destinatarios(AutomationRule $regra): array
    {
        $bruto = (string) ($regra->action_config['recipients'] ?? '');

        return collect(preg_split('/[,;\r\n]+/', $bruto))
            ->map(fn (string $email) => trim($email))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }
}
