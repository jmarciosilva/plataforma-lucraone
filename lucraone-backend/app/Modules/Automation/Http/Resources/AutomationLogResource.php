<?php

namespace App\Modules\Automation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutomationLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'automation_rule_id' => $this->automation_rule_id,
            'rule_name' => $this->whenLoaded('rule', fn () => $this->rule?->name),
            'trigger' => $this->trigger,
            'result' => $this->result,
            'result_label' => $this->resultLabel(),
            'action' => $this->action,
            'message' => $this->message,
            'payload' => $this->payload,
            'outcome' => $this->outcome,
            'duration_ms' => $this->duration_ms,
            'ran_at' => $this->ran_at,
        ];
    }
}
