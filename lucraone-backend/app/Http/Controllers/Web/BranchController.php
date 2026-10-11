<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Branches\Application\BranchService;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Gestão de filiais no painel administrativo.
 *
 * A autoridade é verificada no servidor em toda ação. Leitura exige
 * view-branches / view-all-branches / manage-branches. Escrita exige
 * manage-branches. A filial é sempre carregada com filtro explícito de
 * tenant_id para que uma filial de outro tenant seja indistinguível de
 * um id inexistente.
 */
class BranchController extends Controller
{
    public function __construct(private BranchService $servico) {}

    public function index(Request $request, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirConsulta($request, $tenantId);

        $filiais = Branch::where('tenant_id', $tenantId)
            ->with(['company', 'primaryAddress'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $busca = $request->string('search')->toString();
                $q->where(function ($q) use ($busca) {
                    $q->where('name', 'like', "%{$busca}%")
                        ->orWhere('code', 'like', "%{$busca}%");
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('is_headquarters')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('branches.index', [
            'filiais' => $filiais,
            'podeAdministrar' => $this->podeManejar($request, $tenantId),
            'statusOptions' => ['ACTIVE' => 'ativa', 'INACTIVE' => 'inativa', 'SUSPENDED' => 'suspensa'],
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => $this->breadcrumbs(),
        ]);
    }

    public function create(Request $request, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirAdministracao($request, $tenantId);

        return view('branches.create', [
            'empresas' => Company::where('tenant_id', $tenantId)->where('status', 'ACTIVE')->orderBy('legal_name')->get(),
            'temMatriz' => Branch::where('tenant_id', $tenantId)->where('is_headquarters', true)->exists(),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => 'nova']],
        ]);
    }

    public function store(StoreBranchRequest $request, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirAdministracao($request, $tenantId);

        $dados = $request->validated();

        $empresa = Company::where('id', $dados['company_id'])
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $empresa) {
            abort(422, 'Empresa não encontrada neste estabelecimento.');
        }

        try {
            $branch = $this->servico->criarUnidade($tenantId, $empresa->id, $dados);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('branches.show', $branch)
            ->with('sucesso', 'Unidade cadastrada.');
    }

    public function show(Request $request, string $branch, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirConsulta($request, $tenantId);

        $filial = $this->filial($branch, $tenantId);

        return view('branches.show', [
            'filial' => $filial,
            'terminais' => Terminal::withoutGlobalScopes()
                ->where('branch_id', $filial->id)
                ->orderBy('name')
                ->get(),
            'historico' => AuditLog::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('entity_type', 'branch')
                ->where('entity_id', $filial->id)
                ->latest()
                ->limit(20)
                ->get(),
            'podeAdministrar' => $this->podeManejar($request, $tenantId),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $filial->name]],
        ]);
    }

    public function edit(Request $request, string $branch, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirAdministracao($request, $tenantId);

        $filial = $this->filial($branch, $tenantId);

        return view('branches.edit', [
            'filial' => $filial,
            'temMatriz' => Branch::where('tenant_id', $tenantId)
                ->where('is_headquarters', true)
                ->where('id', '!=', $filial->id)
                ->exists(),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $filial->name], ['label' => 'editar']],
        ]);
    }

    public function update(UpdateBranchRequest $request, string $branch, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirAdministracao($request, $tenantId);

        $filial = $this->filial($branch, $tenantId);

        try {
            $filial = $this->servico->atualizarUnidade($filial, $request->validated());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('branches.show', $filial)
            ->with('sucesso', 'Unidade atualizada.');
    }

    public function toggle(Request $request, string $branch, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirAdministracao($request, $tenantId);

        $filial = $this->filial($branch, $tenantId);
        $this->servico->toggleStatus($filial);

        return back()->with(
            'sucesso',
            $filial->status === 'ACTIVE' ? 'Unidade desativada.' : 'Unidade reativada.'
        );
    }

    // -------------------------------------------------------------------------

    private function filial(string $id, string $tenantId): Branch
    {
        return Branch::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->with(['company', 'primaryAddress', 'addresses'])
            ->firstOrFail();
    }

    private function exigirConsulta(Request $request, string $tenantId): void
    {
        Gate::forUser($request->user())->authorize('viewAny', Branch::class);
    }

    private function exigirAdministracao(Request $request, string $tenantId): void
    {
        Gate::forUser($request->user())->authorize('create', Branch::class);
    }

    private function podeManejar(Request $request, string $tenantId): bool
    {
        return Gate::forUser($request->user())->allows('create', Branch::class);
    }

    private function breadcrumbs(): array
    {
        return [['label' => 'unidades', 'rota' => 'branches.index']];
    }
}
