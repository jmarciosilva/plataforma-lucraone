<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Reporting\Application\CustomerReportService;
use App\Modules\Reporting\Application\InventoryReportService;
use App\Modules\Reporting\Application\SalesReportService;
use App\Modules\Reporting\Http\Requests\ReportPeriodRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Relatórios são leitura agregada: não há recurso persistido, então respondem
 * um envelope `data` montado à mão em vez de um API Resource.
 */
class ReportController extends Controller
{
    public function __construct(
        private TenantContext $context,
        private SalesReportService $vendas,
        private InventoryReportService $estoque,
        private CustomerReportService $clientes,
    ) {}

    public function sales(ReportPeriodRequest $request): JsonResponse
    {
        $periodo = $request->periodo($this->context);
        $filtros = $request->filtros();

        return response()->json([
            'data' => [
                'periodo' => $periodo->toArray(),
                'totais' => $this->vendas->totais($periodo, $filtros),
                'serie' => $this->vendas->serie($periodo, $filtros),
                'por_situacao' => $this->vendas->porSituacao($periodo, $filtros),
                'top_produtos' => $this->vendas->topProdutos($periodo, $filtros, (int) $request->input('limite', 10)),
            ],
        ]);
    }

    public function inventory(ReportPeriodRequest $request): JsonResponse
    {
        $filtros = $request->filtros();
        $linhas = $this->estoque->linhas($filtros);

        return response()->json([
            'data' => [
                'resumo' => $this->estoque->resumo($linhas),
                'por_situacao' => $this->estoque->porSituacao($linhas),
                'itens' => $linhas,
            ],
        ]);
    }

    public function customers(ReportPeriodRequest $request): JsonResponse
    {
        $periodo = $request->periodo($this->context);
        $filtros = $request->filtros();

        return response()->json([
            'data' => [
                'periodo' => $periodo->toArray(),
                'totais' => $this->clientes->totais($periodo, $filtros),
                'segmentacao' => $this->clientes->segmentacao($periodo, $filtros),
                'top_clientes' => $this->clientes->top($periodo, $filtros, (int) $request->input('limite', 10)),
            ],
        ]);
    }
}
