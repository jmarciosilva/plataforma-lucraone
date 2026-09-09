<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebAutomationRuleRequest;
use App\Http\Requests\UpdateWebAutomationRuleRequest;
use App\Modules\Automation\Application\Actions\ActionRegistry;
use App\Modules\Automation\Application\Actions\UpdatePriceAction;
use App\Modules\Automation\Domain\Models\AutomationLog;
use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Automation\Domain\Models\Notification;
use App\Modules\Automation\Domain\Operator;
use App\Modules\Automation\Domain\TriggerCatalog;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AutomationWebController extends Controller
{
    public function index(Request $request, TenantContext $context)
    {
        Gate::authorize('viewAny', AutomationRule::class);

        $regras = AutomationRule::query()
            ->withCount('logs')
            ->when($request->filled('trigger'), fn ($query) => $query->where('trigger', $request->string('trigger')->toString()))
            ->when($request->input('status') === 'ativas', fn ($query) => $query->where('active', true))
            ->when($request->input('status') === 'inativas', fn ($query) => $query->where('active', false))
            ->when($request->filled('search'), function ($query) use ($request) {
                $busca = $request->string('search')->toString();
                $query->where('name', 'like', "%{$busca}%");
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('automations.index', [
            'regras' => $regras,
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => $this->breadcrumbs(),
            'gatilhos' => TriggerCatalog::opcoesDeGatilho(),
            'acoes' => ActionRegistry::opcoes(),
            'statusOptions' => ['' => 'todas', 'ativas' => 'ativas', 'inativas' => 'inativas'],
            'resumo' => $this->resumo(),
        ]);
    }

    public function create(TenantContext $context)
    {
        Gate::authorize('create', AutomationRule::class);

        return view('automations.create', [
            'regra' => new AutomationRule([
                'trigger' => TriggerCatalog::ESTOQUE_BAIXO,
                'action' => 'create_notification',
                'active' => true,
            ]),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => 'nova']],
            ...$this->opcoesDoFormulario(),
        ]);
    }

    public function store(StoreWebAutomationRuleRequest $request, TenantContext $context)
    {
        $regra = AutomationRule::create([
            ...$request->validated(),
            'tenant_id' => $context->id(),
            'active' => $request->boolean('active', true),
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('automations.show', $regra)
            ->with('sucesso', 'regra criada. ela já está valendo para os próximos gatilhos.');
    }

    public function show(AutomationRule $automation, TenantContext $context)
    {
        Gate::authorize('view', $automation);

        return view('automations.show', [
            'regra' => $automation,
            'execucoes' => AutomationLog::query()
                ->where('automation_rule_id', $automation->id)
                ->latest('ran_at')
                ->paginate(15),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $automation->name]],
            'campos' => TriggerCatalog::opcoesDeCampo($automation->trigger),
            'acoes' => ActionRegistry::opcoes(),
        ]);
    }

    public function edit(AutomationRule $automation, TenantContext $context)
    {
        Gate::authorize('update', $automation);

        return view('automations.edit', [
            'regra' => $automation,
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [
                ...$this->breadcrumbs(),
                ['label' => $automation->name, 'url' => route('automations.show', $automation)],
                ['label' => 'editar'],
            ],
            ...$this->opcoesDoFormulario(),
        ]);
    }

    public function update(UpdateWebAutomationRuleRequest $request, AutomationRule $automation)
    {
        $automation->update([
            ...$request->validated(),
            'active' => $request->boolean('active', $automation->active),
        ]);

        return redirect()
            ->route('automations.show', $automation)
            ->with('sucesso', 'regra atualizada.');
    }

    /**
     * Liga e desliga a regra sem passar pelo formulário inteiro.
     */
    public function toggle(AutomationRule $automation)
    {
        Gate::authorize('update', $automation);

        $automation->forceFill(['active' => ! $automation->active])->save();

        return back()->with(
            'sucesso',
            $automation->active ? 'regra ativada.' : 'regra desativada.'
        );
    }

    public function destroy(AutomationRule $automation)
    {
        Gate::authorize('delete', $automation);

        $automation->delete();

        return redirect()
            ->route('automations.index')
            ->with('sucesso', 'regra removida. o histórico de execuções foi preservado.');
    }

    /**
     * Histórico de todas as regras, com filtro por resultado.
     */
    public function logs(Request $request, TenantContext $context)
    {
        Gate::authorize('viewAny', AutomationRule::class);

        $execucoes = AutomationLog::query()
            ->with('rule')
            ->when($request->filled('result'), fn ($query) => $query->where('result', $request->string('result')->toString()))
            ->when($request->filled('trigger'), fn ($query) => $query->where('trigger', $request->string('trigger')->toString()))
            ->latest('ran_at')
            ->paginate(30)
            ->withQueryString();

        return view('automations.logs', [
            'execucoes' => $execucoes,
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => 'histórico']],
            'gatilhos' => TriggerCatalog::opcoesDeGatilho(),
            'resultados' => ['' => 'todos'] + AutomationLog::ROTULOS,
        ]);
    }

    private function breadcrumbs(): array
    {
        return [
            ['label' => 'dashboard', 'url' => route('dashboard')],
            ['label' => 'automações', 'url' => route('automations.index')],
        ];
    }

    /**
     * Tudo que o formulário precisa para montar os selects encadeados.
     *
     * Os campos e operadores vão para o JavaScript da tela, que troca as opções
     * conforme o gatilho escolhido — sem ida ao servidor.
     */
    private function opcoesDoFormulario(): array
    {
        return [
            'gatilhos' => TriggerCatalog::opcoesDeGatilho(),
            'acoes' => ActionRegistry::opcoes(),
            'catalogo' => collect(TriggerCatalog::todos())
                ->map(fn (array $gatilho) => [
                    'descricao' => $gatilho['descricao'],
                    'campos' => collect($gatilho['campos'])
                        ->map(fn (array $campo, string $chave) => [
                            'chave' => $chave,
                            'rotulo' => $campo['rotulo'],
                            'tipo' => $campo['tipo'],
                        ])
                        ->values()
                        ->all(),
                ])
                ->all(),
            'operadoresPorTipo' => Operator::opcoesPorTipo(),
            'acoesComProduto' => TriggerCatalog::comProduto(),
            'niveis' => Notification::NIVEIS,
            'tiposDePreco' => [
                Price::TYPE_SALE => 'preço de venda',
                Price::TYPE_COST => 'preço de custo',
            ],
            'operacoesDePreco' => UpdatePriceAction::OPERACOES,
            'limiteDeVariacao' => UpdatePriceAction::VARIACAO_MAXIMA_PERCENTUAL,
        ];
    }

    private function resumo(): array
    {
        $regras = AutomationRule::query()->get();

        return [
            'total' => $regras->count(),
            'ativas' => $regras->where('active', true)->count(),
            'execucoes' => AutomationLog::query()->count(),
            'falhas' => AutomationLog::query()->failed()->count(),
        ];
    }
}
