<?php

namespace App\Http\Requests;

use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Automation\Http\Requests\AutomationRuleRequest;
use Illuminate\Support\Facades\Gate;

class UpdateWebAutomationRuleRequest extends AutomationRuleRequest
{
    public function authorize(): bool
    {
        $regra = $this->route('automation');

        return $this->user() && $regra instanceof AutomationRule
            ? Gate::forUser($this->user())->allows('update', $regra)
            : false;
    }
}
