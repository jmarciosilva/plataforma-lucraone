<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Branches\Domain\BranchSegment;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Pdv\Application\ResumoVendasPdv;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Reporting\Application\DashboardSummaryService;
use App\Modules\Reporting\Domain\ReportPeriod;
use App\Modules\Tenancy\Application\BranchContext;
use App\Modules\Tenancy\Application\ChecklistInicial;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Terminals\Domain\Models\Terminal;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardSummaryService $resumo,
        private ResumoVendasPdv $vendasPdv,
    ) {}

    public function __invoke(Request $request, TenantContext $context, ChecklistInicial $checklist, BranchContext $branch)
    {
        if ($branch->resolved() && $branch->segment() === BranchSegment::RESTAURANT) {
            return $this->dashboardRestaurante($context, $branch);
        }

        return $this->dashboardSupermercado($request, $context, $checklist, $branch);
    }

    private function dashboardRestaurante(TenantContext $context, BranchContext $branch): \Illuminate\View\View
    {
        $filial = $branch->branch();
        $tenant = $context->tenant();

        return view('dashboard.restaurante', [
            'tenantNome' => $tenant->name,
            'filialNome' => $filial->name,
            'filialId' => $filial->id,
            'breadcrumbs' => [
                ['label' => 'dashboard', 'url' => route('dashboard')],
            ],
        ]);
    }

    private function dashboardSupermercado(Request $request, TenantContext $context, ChecklistInicial $checklist, BranchContext $branch): \Illuminate\View\View
    {
        $tenant = $context->tenant();
        $usuario = $request->user();

        // Vendas do PDV de hoje, no fuso do estabelecimento. Só para quem já vê
        // o PDV (mesma autoridade da tela dedicada) e quando há terminal — sem
        // isso o cartão apareceria vazio para quem nem usa PDV.
        $vendasPdv = null;
        if ($usuario->hasAnyPermission(['view-branches', 'view-all-branches', 'manage-branches'], $tenant->id)
            && Terminal::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists()) {
            $hoje = CarbonImmutable::now($tenant->timezone ?: 'America/Sao_Paulo');
            $vendasPdv = $this->vendasPdv->noPeriodo($tenant->id, null, null, $hoje->startOfDay()->utc(), $hoje->endOfDay()->utc());
        }

        $temMultiplas = Branch::where('tenant_id', $tenant->id)
            ->where('status', 'ACTIVE')
            ->limit(2)
            ->count() > 1;

        return view('dashboard.index', [
            'onboardingChecklist' => $checklist->itens($context),
            'vendasPdv' => $vendasPdv,
            // Quem não pode ver relatório também não vê a faixa comercial.
            'comercial' => Gate::allows('view-reports')
                ? $this->resumo->resumo(ReportPeriod::fromInput(
                    timezone: $tenant->timezone ?: 'UTC',
                ))
                : null,
            'tenantNome' => $tenant->name,
            'tenantTimezone' => $tenant->timezone,
            'metricas' => [
                'tenants' => $usuario->estabelecimentosDisponiveis()->count(),
                'usuarios' => $tenant->activeUsers()->count(),
                'empresas' => Company::count(),
                'produtos' => Product::count(),
                'ultimoAcesso' => $usuario->last_login_at
                    ? $usuario->last_login_at->timezone($tenant->timezone)->format('d/m/Y H:i')
                    : 'sem registro',
            ],
            'modoConsolidado' => !$branch->resolved() && $temMultiplas,
            'breadcrumbs' => [
                ['label' => 'dashboard', 'url' => route('dashboard')],
            ],
        ]);
    }
}
