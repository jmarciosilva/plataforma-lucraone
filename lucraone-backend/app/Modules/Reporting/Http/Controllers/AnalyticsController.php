<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Reporting\Application\TrendAnalysisService;
use App\Modules\Reporting\Http\Requests\ReportPeriodRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class AnalyticsController extends Controller
{
    public function __construct(
        private TenantContext $context,
        private TrendAnalysisService $tendencias,
    ) {}

    public function trends(ReportPeriodRequest $request): JsonResponse
    {
        $periodo = $request->periodo($this->context);

        return response()->json([
            'data' => [
                'periodo' => $periodo->toArray(),
                ...$this->tendencias->analisar($periodo, $request->filtros()),
                'metodo' => 'regressão linear simples sobre a série do período; estimativa, não previsão estatística.',
            ],
        ]);
    }
}
