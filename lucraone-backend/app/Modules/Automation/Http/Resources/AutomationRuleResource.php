<?php

namespace App\Modules\Automation\Http\Resources;

use App\Modules\Automation\Application\Actions\ActionRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutomationRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'trigger' => $this->trigger,
            'trigger_label' => $this->triggerLabel(),
            'conditions' => $this->conditions ?? [],
            'action' => $this->action,
            'action_label' => ActionRegistry::rotulo($this->action),
            'action_config' => $this->action_config ?? [],
            'active' => $this->active,
            'run_count' => $this->run_count,
            'last_run_at' => $this->last_run_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
