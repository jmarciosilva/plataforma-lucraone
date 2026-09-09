<?php

namespace App\Modules\Automation\Application\Actions;

use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Automation\Domain\Models\Notification;
use Illuminate\Validation\Rule;

/**
 * Cria um aviso no painel do estabelecimento.
 *
 * É a resposta para "quero ser avisado" sem depender de e-mail configurado.
 */
class CreateNotificationAction implements ActionHandler
{
    public const CHAVE = 'create_notification';

    public function __construct(
        private PlaceholderInterpolator $interpolador
    ) {}

    public static function rotulo(): string
    {
        return 'criar aviso no painel';
    }

    public static function regrasDeConfiguracao(): array
    {
        return [
            'action_config.title' => ['required', 'string', 'max:255'],
            'action_config.message' => ['required', 'string', 'max:1000'],
            'action_config.level' => ['required', Rule::in(array_keys(Notification::NIVEIS))],
        ];
    }

    public function executar(AutomationRule $regra, array $payload): array
    {
        $titulo = $this->interpolador->aplicar(
            (string) ($regra->action_config['title'] ?? $regra->name),
            $payload
        );

        $mensagem = $this->interpolador->aplicar(
            (string) ($regra->action_config['message'] ?? ''),
            $payload
        );

        $aviso = Notification::create([
            'tenant_id' => $regra->tenant_id,
            'user_id' => null,
            'title' => $titulo,
            'message' => $mensagem,
            'level' => $regra->action_config['level'] ?? Notification::NIVEL_INFO,
            'link' => $payload['link'] ?? null,
            'automation_rule_id' => $regra->id,
        ]);

        return [
            'notification_id' => $aviso->id,
            'title' => $titulo,
        ];
    }
}
