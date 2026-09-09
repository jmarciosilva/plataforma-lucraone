<?php

namespace App\Http\Requests;

use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Automation\Http\Requests\AutomationRuleRequest;
use Illuminate\Support\Facades\Gate;

class StoreWebAutomationRuleRequest extends AutomationRuleRequest
{
    public function authorize(): bool
    {
        return $this->user()
            ? Gate::forUser($this->user())->allows('create', AutomationRule::class)
            : false;
    }
}
