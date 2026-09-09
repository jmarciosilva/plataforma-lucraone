<?php

namespace App\Modules\Automation\Http\Controllers;

use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Automation\Http\Requests\StoreAutomationRuleRequest;
use App\Modules\Automation\Http\Requests\UpdateAutomationRuleRequest;
use App\Modules\Automation\Http\Resources\AutomationRuleResource;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class AutomationRuleController extends Controller
{
    public function __construct(
        private TenantContext $context
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AutomationRule::class);

        $regras = AutomationRule::query()
            ->when($request->filled('trigger'), fn ($query) => $query->where('trigger', $request->string('trigger')->toString()))
            ->when($request->filled('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->latest()
            ->paginate(20);

        return AutomationRuleResource::collection($regras);
    }

    public function store(StoreAutomationRuleRequest $request): JsonResponse
    {
        $regra = AutomationRule::create([
            ...$request->validated(),
            'tenant_id' => $this->context->id(),
            'active' => $request->boolean('active', true),
            'created_by' => $request->user()?->id,
        ]);

        return (new AutomationRuleResource($regra))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): AutomationRuleResource
    {
        $regra = AutomationRule::query()->findOrFail($id);

        Gate::authorize('view', $regra);

        return new AutomationRuleResource($regra);
    }

    public function update(UpdateAutomationRuleRequest $request, string $id): AutomationRuleResource
    {
        $regra = AutomationRule::query()->findOrFail($id);

        Gate::authorize('update', $regra);

        $regra->update([
            ...$request->validated(),
            'active' => $request->boolean('active', $regra->active),
        ]);

        return new AutomationRuleResource($regra->fresh());
    }

    public function destroy(string $id): JsonResponse
    {
        $regra = AutomationRule::query()->findOrFail($id);

        Gate::authorize('delete', $regra);

        $regra->delete();

        return response()->json(['message' => 'regra removida.']);
    }
}
