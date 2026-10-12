<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthorizeTerminalRecoveryRequest;
use App\Http\Requests\StoreTerminalRequest;
use App\Http\Requests\UpdateTerminalRequest;
use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Terminals\Application\IssueTerminalPairingCode;
use App\Modules\Terminals\Application\TerminalAuthenticationEligibility;
use App\Modules\Terminals\Application\TerminalCredentialLifecycle;
use App\Modules\Terminals\Application\TerminalManagementService;
use App\Modules\Terminals\Domain\Exceptions\CredentialLifecycleFailed;
use App\Modules\Terminals\Domain\Exceptions\PairingFailed;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Gestão de terminais PDV no painel administrativo.
 *
 * Autoridade: reutiliza manage-branches (via TerminalPolicy que delega para
 * BranchPolicy). Um usuário que administra filiais administra terminais —
 * decisão intencional enquanto não houver perfil dedicado.
 *
 * Isolamento de tenant: o terminal é sempre carregado com filtro explícito de
 * tenant_id; terminal de outro tenant é indistinguível de id inexistente.
 */
class TerminalController extends Controller
{
    public function __construct(
        private TerminalManagementService $servico,
        private IssueTerminalPairingCode $emissaoCodigo,
        private TerminalCredentialLifecycle $cicloCredenciais,
    ) {}

    public function index(Request $request, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirConsulta($request);

        $terminais = Terminal::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['branch', 'branch.company'])
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->string('branch_id')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->string('search')->toString().'%');
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $filiais = Branch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('terminals.index', [
            'terminais' => $terminais,
            'filiais' => $filiais,
            'podeAdministrar' => $this->podeManejar($request),
            'statusOptions' => [
                Terminal::STATUS_PENDING => 'aguardando pareamento',
                Terminal::STATUS_ACTIVE => 'ativo',
                Terminal::STATUS_BLOCKED => 'bloqueado',
                Terminal::STATUS_REVOKED => 'revogado',
            ],
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => $this->breadcrumbs(),
        ]);
    }

    public function create(Request $request, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirAdministracao($request);

        return view('terminals.create', [
            'filiais' => Branch::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->with('company')
                ->orderBy('name')
                ->get(),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => 'novo']],
        ]);
    }

    public function store(StoreTerminalRequest $request, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirAdministracao($request);

        try {
            $terminal = $this->servico->criar($tenantId, $request->validated());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('terminals.show', $terminal)
            ->with('sucesso', 'Terminal cadastrado.');
    }

    public function show(Request $request, string $terminal, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirConsulta($request);

        $terminal = $this->terminal($terminal, $tenantId);

        $codigoPendente = $terminal->pairingCodes()
            ->whereNull('consumed_at')
            ->whereNull('invalidated_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        return view('terminals.show', [
            'terminal' => $terminal,
            'codigoPendente' => $codigoPendente,
            'historico' => AuditLog::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('entity_type', 'terminal')
                ->where('entity_id', $terminal->id)
                ->latest()
                ->limit(20)
                ->get(),
            'podeAdministrar' => $this->podeManejar($request),
            'podeRecuperarCredencial' => $terminal->status === Terminal::STATUS_ACTIVE
                && $terminal->installation_id !== null
                && app(TerminalAuthenticationEligibility::class)->allows($terminal)
                && Gate::forUser($request->user())->allows('update', $terminal),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $terminal->name]],
        ]);
    }

    public function edit(Request $request, string $terminal, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirAdministracao($request);

        $terminal = $this->terminal($terminal, $tenantId);

        return view('terminals.edit', [
            'terminal' => $terminal,
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $terminal->name], ['label' => 'editar']],
        ]);
    }

    public function update(UpdateTerminalRequest $request, string $terminal, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirAdministracao($request);

        $terminal = $this->terminal($terminal, $tenantId);

        try {
            $terminal = $this->servico->atualizar($terminal, $request->validated());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('terminals.show', $terminal)
            ->with('sucesso', 'Terminal atualizado.');
    }

    public function block(Request $request, string $terminal, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirAdministracao($request);

        $terminal = $this->terminal($terminal, $tenantId);

        try {
            $this->servico->bloquear($terminal);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('sucesso', 'Terminal bloqueado.');
    }

    public function unblock(Request $request, string $terminal, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirAdministracao($request);

        $terminal = $this->terminal($terminal, $tenantId);

        try {
            $this->servico->desbloquear($terminal);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('sucesso', 'Terminal desbloqueado.');
    }

    public function revoke(Request $request, string $terminal, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirAdministracao($request);

        $terminal = $this->terminal($terminal, $tenantId);

        try {
            $this->servico->revogar($terminal);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('sucesso', 'Terminal revogado.');
    }

    public function issuePairingCode(Request $request, string $terminal, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirAdministracao($request);

        $terminal = $this->terminal($terminal, $tenantId);

        try {
            $issued = $this->emissaoCodigo->issue($terminal);
        } catch (PairingFailed $e) {
            return back()->withErrors(['pairing' => 'Não foi possível emitir o código: '.$e->getMessage()]);
        }

        // Sessão com driver cookie usa JSON; objetos Carbon viram string. Formatar aqui.
        return back()->with('codigoPareamento', $issued->code)->with('codigoExpira', $issued->expiresAt->format('H:i \d\e d/m/Y'));
    }

    public function authorizeRecovery(AuthorizeTerminalRecoveryRequest $request, string $terminal, TenantContext $context): JsonResponse
    {
        $subject = $this->terminal($terminal, $context->id());
        Gate::forUser($request->user())->authorize('update', $subject);

        if ($subject->status !== Terminal::STATUS_ACTIVE || $subject->installation_id === null) {
            return $this->recoveryError('Este terminal não está disponível para recuperação.', 409);
        }

        try {
            $result = $this->cicloCredenciais->authorizeRecovery($request->user(), $subject, $request->validated('reason'));
        } catch (AuthenticationException) {
            return $this->recoveryError('O terminal ou sua estrutura não está operacional. Atualize a página e tente novamente.', 409);
        } catch (CredentialLifecycleFailed $exception) {
            return $this->recoveryError(
                $exception->httpStatus === 403
                    ? 'Você não tem autorização para recuperar este terminal.'
                    : 'Não foi possível iniciar a recuperação. Atualize a página e tente novamente.',
                $exception->httpStatus === 403 ? 403 : 409,
            );
        }

        return response()->json([
            'data' => [
                'recovery_code' => $result['recovery_code'],
                'expires_at' => $result['expires_at'],
                'terminal' => ['id' => $subject->id, 'name' => $subject->name],
            ],
        ])->withHeaders($this->recoveryHeaders());
    }

    // -------------------------------------------------------------------------

    private function terminal(string $id, string $tenantId): Terminal
    {
        return Terminal::withoutGlobalScopes()
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->with(['branch', 'branch.company', 'pairingCodes'])
            ->firstOrFail();
    }

    private function exigirConsulta(Request $request): void
    {
        Gate::forUser($request->user())->authorize('viewAny', Terminal::class);
    }

    private function exigirAdministracao(Request $request): void
    {
        Gate::forUser($request->user())->authorize('create', Terminal::class);
    }

    private function podeManejar(Request $request): bool
    {
        return Gate::forUser($request->user())->allows('create', Terminal::class);
    }

    private function breadcrumbs(): array
    {
        return [['label' => 'terminais', 'rota' => 'terminals.index']];
    }

    private function recoveryError(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status)->withHeaders($this->recoveryHeaders());
    }

    /** @return array<string, string> */
    private function recoveryHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Referrer-Policy' => 'no-referrer',
        ];
    }
}
