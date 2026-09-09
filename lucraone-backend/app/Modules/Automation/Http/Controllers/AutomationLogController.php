<?php

namespace App\Modules\Automation\Http\Controllers;

use App\Modules\Automation\Domain\Models\AutomationLog;
use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Automation\Http\Resources\AutomationLogResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class AutomationLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AutomationRule::class);

        $logs = AutomationLog::query()
            ->with('rule')
            ->when($request->filled('automation_rule_id'), fn ($query) => $query->where('automation_rule_id', $request->string('automation_rule_id')->toString()))
            ->when($request->filled('result'), fn ($query) => $query->where('result', $request->string('result')->toString()))
            ->when($request->filled('trigger'), fn ($query) => $query->where('trigger', $request->string('trigger')->toString()))
            ->latest('ran_at')
            ->paginate(30);

        return AutomationLogResource::collection($logs);
    }
}
