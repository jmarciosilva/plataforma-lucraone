<?php

namespace App\Modules\Automation\Http\Requests;

use App\Modules\Automation\Domain\Models\AutomationRule;
use Illuminate\Support\Facades\Gate;

class UpdateAutomationRuleRequest extends AutomationRuleRequest
{
    public function authorize(): bool
    {
        $regra = $this->route('rule') ?? $this->route('automation_rule');

        if ($regra instanceof AutomationRule) {
            return $this->user()
                ? Gate::forUser($this->user())->allows('update', $regra)
                : false;
        }

        // Rota de API entrega o id como string; a autorização por instância
        // acontece no controller, depois do findOrFail.
        return $this->user()
            ? Gate::forUser($this->user())->allows('create', AutomationRule::class)
            : false;
    }
}
