<?php

namespace App\Modules\Automation\Http\Requests;

use App\Modules\Automation\Domain\Models\AutomationRule;
use Illuminate\Support\Facades\Gate;

class StoreAutomationRuleRequest extends AutomationRuleRequest
{
    public function authorize(): bool
    {
        return $this->user()
            ? Gate::forUser($this->user())->allows('create', AutomationRule::class)
            : false;
    }
}
