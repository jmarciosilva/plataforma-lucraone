<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Reporting\Application\DashboardSummaryService;
use App\Modules\Reporting\Domain\ReportPeriod;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardSummaryService $resumo
    ) {}

    public function __invoke(Request $request, TenantContext $context)
    {
        $tenant = $context->tenant();
        $usuario = $request->user();

        return view('dashboard.index', [
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
            'breadcrumbs' => [
                ['label' => 'dashboard', 'url' => route('dashboard')],
            ],
        ]);
    }
}
