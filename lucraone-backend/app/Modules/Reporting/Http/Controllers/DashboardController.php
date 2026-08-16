<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Reporting\Application\DashboardSummaryService;
use App\Modules\Reporting\Http\Requests\ReportPeriodRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function __construct(
        private TenantContext $context,
        private DashboardSummaryService $resumo,
    ) {}

    public function summary(ReportPeriodRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->resumo->resumo(
                $request->periodo($this->context),
                $request->filtros()
            ),
        ]);
    }
}
