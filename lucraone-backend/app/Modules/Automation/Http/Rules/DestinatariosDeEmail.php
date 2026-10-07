<?php

namespace App\Modules\Automation\Http\Rules;

use App\Modules\Automation\Domain\EmailRecipients;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida o campo `recipients` de uma regra que envia e-mail.
 *
 * Declarada em `SendEmailAction::regrasDeConfiguracao()`, que é a fonte única
 * consumida pelo `AutomationRuleRequest` — por isso a mesma política vale na
 * API e no painel web, sem repetição.
 */
class DestinatariosDeEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $destinatarios = EmailRecipients::deConfiguracao(
            is_string($value) ? $value : null
        );

        if ($destinatarios->temInvalidos()) {
            $fail('Há endereço de e-mail inválido na lista de destinatários.');

            return;
        }

        if ($destinatarios->vazio()) {
            $fail('Informe pelo menos um destinatário.');

            return;
        }

        if ($destinatarios->excedeMaximo()) {
            $fail('Informe no máximo '.EmailRecipients::MAXIMO.' destinatários.');
        }
    }
}
