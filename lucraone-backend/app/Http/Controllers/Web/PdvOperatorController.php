<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePdvOperatorRequest;
use App\Http\Requests\UpdatePdvOperatorRequest;
use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Application\OperatorDelegation;
use App\Modules\Pdv\Application\OperatorRegistry;
use App\Modules\Pdv\Application\OperatorSessionAudit;
use App\Modules\Pdv\Domain\Exceptions\OperatorSessionFailed;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Pdv\Domain\Models\PdvOperatorProfile;
use App\Modules\Pdv\Domain\Models\PdvOperatorSession;
use App\Modules\Pdv\Domain\Models\PdvTenantSetting;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Operadores de PDV no painel administrativo.
 *
 * A autoridade é verificada no SERVIDOR em toda ação, nunca só escondendo o
 * botão: a tela esconde o que a pessoa não pode fazer por educação, e o
 * controller recusa por segurança. Esconder sem recusar seria autorização por
 * CSS.
 *
 * Leitura exige autoridade de consulta sobre filiais; escrita exige
 * `manage-branches` mais a contenção de delegação — as duas definidas em
 * OperatorDelegation e compartilhadas com a API administrativa, para que a tela
 * e o endpoint nunca divirjam sobre quem pode o quê.
 *
 * O perfil é carregado EXPLICITAMENTE, filtrando pelo estabelecimento em uso, e
 * não por route model binding: o perfil de outra loja tem de ser indistinguível
 * de um id inexistente, e isso é mais fácil de garantir numa consulta à vista do
 * que num binding implícito.
 */
class PdvOperatorController extends Controller
{
    private const STATUS = [
        PdvOperatorProfile::STATUS_ACTIVE => 'ativo',
        PdvOperatorProfile::STATUS_INACTIVE => 'inativo',
    ];

    public function __construct(
        private OperatorRegistry $registro,
        private OperatorDelegation $delegacao,
    ) {}

    public function index(Request $request, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirConsulta($request, $tenantId);

        $operadores = PdvOperatorProfile::consultaIsolada($tenantId)
            ->with('user')
            ->when($request->filled('search'), function ($query) use ($request) {
                $busca = $request->string('search')->toString();

                $query->where(function ($query) use ($busca) {
                    $query->where('pdv_operator_profiles.username', 'like', "%{$busca}%")
                        ->orWhereHas('user', fn ($query) => $query->where('users.name', 'like', "%{$busca}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('pdv_operator_profiles.status', $request->string('status')->toString()));

        match ($request->input('sort', 'username')) {
            'created_desc' => $operadores->orderByDesc('pdv_operator_profiles.created_at'),
            'status' => $operadores->orderBy('pdv_operator_profiles.status')->orderBy('pdv_operator_profiles.username'),
            default => $operadores->orderBy('pdv_operator_profiles.username'),
        };

        $pagina = $operadores->paginate(20)->withQueryString();

        return view('pdv-operators.index', [
            'operadores' => $pagina,
            'sessoesAtivas' => $this->sessoesAtivasPor($tenantId, $pagina->pluck('user_id')->all()),
            'filiaisPorOperador' => $this->filiaisPorOperador($tenantId, $pagina->pluck('user_id')->all()),
            'politica' => PdvTenantSetting::para($tenantId),
            'podeAdministrar' => $this->delegacao->podeAdministrar($request->user(), $tenantId),
            'statusOptions' => self::STATUS,
            'sortOptions' => [
                'username' => 'usuário do pdv',
                'status' => 'status',
                'created_desc' => 'mais recentes',
            ],
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => $this->breadcrumbs(),
        ]);
    }

    public function create(Request $request, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirAdministracao($request, $tenantId);

        return view('pdv-operators.create', [
            'filiais' => $this->filiais($tenantId),
            'pessoasDisponiveis' => $this->pessoasSemPerfil($tenantId),
            'podeCriarIdentidade' => Gate::forUser($request->user())->allows('create', User::class),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => 'novo']],
        ]);
    }

    public function store(StorePdvOperatorRequest $request, TenantContext $context)
    {
        $tenantId = $context->id();
        $dados = $request->validated();

        try {
            $perfil = $dados['modo'] === 'novo'
                ? $this->registro->criar($request->user(), $tenantId, [
                    'name' => $dados['name'],
                    'username' => $dados['username'],
                    'password' => $dados['password'],
                    'email' => $dados['email'] ?? null,
                    'branch_ids' => $dados['branch_ids'],
                ])
                : $this->registro->vincular(
                    $request->user(),
                    $tenantId,
                    $this->pessoa($dados['user_id']),
                    ['username' => $dados['username'], 'branch_ids' => $dados['branch_ids']],
                );
        } catch (OperatorSessionFailed) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('erro', 'você não tem autoridade para cadastrar este operador.');
        }

        return redirect()
            ->route('pdv-operators.show', $perfil)
            ->with('sucesso', $dados['modo'] === 'novo'
                ? 'operador cadastrado.'
                : 'pessoa habilitada no caixa. os dados da conta existente foram mantidos.');
    }

    public function show(Request $request, string $operator, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirConsulta($request, $tenantId);

        $perfil = $this->perfil($operator, $tenantId);

        return view('pdv-operators.show', [
            'perfil' => $perfil,
            'atribuicoes' => PdvOperatorBranchAssignment::consultaIsolada($tenantId)
                ->where('user_id', $perfil->user_id)
                ->with('branch')
                ->orderBy('created_at')
                ->get(),
            'sessao' => $this->sessaoAtiva($tenantId, $perfil->user_id),
            'historico' => AuditLog::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($query) use ($perfil) {
                    $query->where('entity_id', $perfil->id)
                        ->orWhere('changes->operator_id', $perfil->user_id);
                })
                ->whereIn('action', $this->acoesAuditadas())
                ->latest()
                ->limit(20)
                ->get(),
            'membership' => $perfil->user?->membershipFor($tenantId),
            'podeAdministrar' => $this->delegacao->podeAdministrar($request->user(), $tenantId),
            'podeRedefinirSenha' => $perfil->user !== null
                && Gate::forUser($request->user())->allows('manageGlobalIdentity', [$perfil->user, $tenantId]),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $perfil->username]],
        ]);
    }

    public function edit(Request $request, string $operator, TenantContext $context)
    {
        $tenantId = $context->id();
        $this->exigirAdministracao($request, $tenantId);

        $perfil = $this->perfil($operator, $tenantId);

        return view('pdv-operators.edit', [
            'perfil' => $perfil,
            'filiais' => $this->filiais($tenantId),
            'filiaisSelecionadas' => PdvOperatorBranchAssignment::consultaIsolada($tenantId)
                ->where('user_id', $perfil->user_id)
                ->where('status', PdvOperatorBranchAssignment::STATUS_ACTIVE)
                ->pluck('branch_id')
                ->all(),
            'identidadeProtegida' => $perfil->user === null
                || Gate::forUser($request->user())->denies('manageGlobalIdentity', [$perfil->user, $tenantId]),
            'statusOptions' => self::STATUS,
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [
                ...$this->breadcrumbs(),
                ['label' => $perfil->username, 'url' => route('pdv-operators.show', $perfil)],
                ['label' => 'editar'],
            ],
        ]);
    }

    public function update(UpdatePdvOperatorRequest $request, string $operator, TenantContext $context)
    {
        $tenantId = $context->id();
        $perfil = $this->perfil($operator, $tenantId);
        $dados = $request->validated();

        try {
            DB::transaction(function () use ($request, $perfil, $dados) {
                $this->registro->atualizarIdentidade($request->user(), $perfil, $dados['name'], $dados['email'] ?? null);
                $this->registro->atualizarPerfil($request->user(), $perfil, $dados['username'], $dados['status']);
                $this->registro->sincronizarFiliais($request->user(), $perfil, $dados['branch_ids'] ?? []);
            }, 3);
        } catch (OperatorSessionFailed) {
            return back()->withInput()->with('erro', 'você não tem autoridade para alterar este operador.');
        }

        return redirect()
            ->route('pdv-operators.show', $perfil)
            ->with('sucesso', 'operador atualizado.');
    }

    /**
     * Ativa ou desativa, sem passar pelo formulário.
     *
     * Ação separada porque é a mais usada do dia a dia — alguém faltou, alguém
     * saiu da empresa — e porque desativar derruba o turno em andamento, o que
     * merece um botão com confirmação em vez de um campo no meio de um form.
     */
    public function toggle(Request $request, string $operator, TenantContext $context)
    {
        $tenantId = $context->id();
        $perfil = $this->perfil($operator, $tenantId);

        $novo = $perfil->isActive()
            ? PdvOperatorProfile::STATUS_INACTIVE
            : PdvOperatorProfile::STATUS_ACTIVE;

        try {
            $this->registro->atualizarPerfil($request->user(), $perfil, $perfil->username, $novo);
        } catch (OperatorSessionFailed) {
            return back()->with('erro', 'você não tem autoridade para alterar este operador.');
        }

        return redirect()
            ->route('pdv-operators.show', $perfil)
            ->with('sucesso', $novo === PdvOperatorProfile::STATUS_ACTIVE
                ? 'operador ativado.'
                : 'operador desativado. as sessões em andamento foram encerradas.');
    }

    public function resetPassword(Request $request, string $operator, TenantContext $context)
    {
        $tenantId = $context->id();
        $perfil = $this->perfil($operator, $tenantId);

        try {
            $temporaria = $this->registro->redefinirSenha($request->user(), $perfil);
        } catch (OperatorSessionFailed) {
            return back()->with('erro', 'a senha desta pessoa não pode ser redefinida por este estabelecimento.');
        }

        return redirect()
            ->route('pdv-operators.show', $perfil)
            ->with('sucesso', 'senha redefinida.')
            ->with('senha_temporaria', $temporaria);
    }

    /**
     * Política operacional do estabelecimento.
     */
    public function updateSettings(Request $request, TenantContext $context, OperatorSessionAudit $auditoria)
    {
        $tenantId = $context->id();
        $this->exigirAdministracao($request, $tenantId);

        $dados = $request->validate([
            'screen_lock_minutes' => ['nullable', 'integer', 'min:1', 'max:720'],
        ], [
            'screen_lock_minutes.min' => 'use pelo menos 1 minuto, ou deixe vazio para desligar.',
            'screen_lock_minutes.max' => 'use no máximo 720 minutos.',
        ]);

        $minutos = $dados['screen_lock_minutes'] ?? null;

        $politica = PdvTenantSetting::withoutGlobalScopes()->where('tenant_id', $tenantId)->first()
            ?? new PdvTenantSetting(['id' => (string) Str::ulid(), 'tenant_id' => $tenantId]);

        $anterior = $politica->screen_lock_minutes;
        $politica->screen_lock_minutes = $minutos;
        $politica->save();

        $auditoria->politica($tenantId, $request->user(), [
            'screen_lock_minutes' => $minutos,
            'previous_screen_lock_minutes' => $anterior,
        ]);

        return back()->with('sucesso', $minutos === null
            ? 'bloqueio de tela desligado.'
            : "bloqueio de tela definido em {$minutos} min.");
    }

    /**
     * @throws OperatorSessionFailed
     */
    private function exigirConsulta(Request $request, string $tenantId): void
    {
        if (! $this->delegacao->podeConsultar($request->user(), $tenantId)) {
            abort(403);
        }
    }

    private function exigirAdministracao(Request $request, string $tenantId): void
    {
        if (! $this->delegacao->podeAdministrar($request->user(), $tenantId)) {
            abort(403);
        }
    }

    private function perfil(string $id, string $tenantId): PdvOperatorProfile
    {
        $perfil = PdvOperatorProfile::consultaIsolada($tenantId)
            ->with('user')
            ->whereKey($id)
            ->first();

        // Perfil de outro estabelecimento e id inexistente dão a MESMA resposta:
        // uma tela administrativa não deveria confirmar a existência de um
        // operador de outra loja.
        if (! $perfil instanceof PdvOperatorProfile) {
            abort(404);
        }

        return $perfil;
    }

    private function pessoa(string $id): User
    {
        $pessoa = User::withTrashed()->whereKey($id)->first();

        if ($pessoa === null) {
            throw ValidationException::withMessages(['user_id' => 'pessoa não encontrada.']);
        }

        return $pessoa;
    }

    /**
     * @return Collection<int, Branch>
     */
    private function filiais(string $tenantId)
    {
        return Branch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
    }

    /**
     * Pessoas do estabelecimento que ainda não são operadoras.
     *
     * Só quem já tem vínculo ativo: a tela de caixa não é lugar de convidar
     * alguém para o estabelecimento.
     *
     * @return Collection<int, User>
     */
    private function pessoasSemPerfil(string $tenantId)
    {
        $comPerfil = PdvOperatorProfile::consultaIsolada($tenantId)->pluck('user_id')->all();

        return User::query()
            ->whereHas('memberships', fn ($query) => $query
                ->where('tenant_id', $tenantId)
                ->where('status', TenantUser::STATUS_ACTIVE))
            ->where('status', User::STATUS_ACTIVE)
            ->when($comPerfil !== [], fn ($query) => $query->whereKeyNot($comPerfil))
            ->orderBy('name')
            ->get();
    }

    private function sessaoAtiva(string $tenantId, string $userId): ?PdvOperatorSession
    {
        $sessao = PdvOperatorSession::consultaIsolada($tenantId)
            ->where('user_id', $userId)
            ->where('status', PdvOperatorSession::STATUS_ACTIVE)
            ->with('terminal')
            ->latest('authenticated_at')
            ->first();

        return $sessao instanceof PdvOperatorSession && $sessao->vigente() ? $sessao : null;
    }

    /**
     * @param  array<int, string>  $userIds
     * @return array<string, PdvOperatorSession>
     */
    private function sessoesAtivasPor(string $tenantId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return PdvOperatorSession::consultaIsolada($tenantId)
            ->whereIn('user_id', $userIds)
            ->where('status', PdvOperatorSession::STATUS_ACTIVE)
            ->where('expires_at', '>', now())
            ->with('terminal')
            ->get()
            ->keyBy('user_id')
            ->all();
    }

    /**
     * @param  array<int, string>  $userIds
     * @return array<string, int>
     */
    private function filiaisPorOperador(string $tenantId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return PdvOperatorBranchAssignment::consultaIsolada($tenantId)
            ->whereIn('user_id', $userIds)
            ->where('status', PdvOperatorBranchAssignment::STATUS_ACTIVE)
            ->get()
            ->groupBy('user_id')
            // Closure explícita em vez do proxy `->map->count()`: o proxy
            // confunde a análise estática sobre o tipo do item.
            ->map(fn ($atribuicoes) => $atribuicoes->count())
            ->all();
    }

    /**
     * @return list<string>
     */
    private function acoesAuditadas(): array
    {
        return [
            OperatorSessionAudit::OPERATOR_CREATED,
            OperatorSessionAudit::OPERATOR_LINKED,
            OperatorSessionAudit::OPERATOR_UPDATED,
            OperatorSessionAudit::OPERATOR_USERNAME_CHANGED,
            OperatorSessionAudit::OPERATOR_ACTIVATED,
            OperatorSessionAudit::OPERATOR_DEACTIVATED,
            OperatorSessionAudit::OPERATOR_PASSWORD_RESET,
            OperatorSessionAudit::BRANCH_ACCESS_GRANTED,
            OperatorSessionAudit::BRANCH_ACCESS_REVOKED,
            OperatorSessionAudit::SESSION_STARTED,
            OperatorSessionAudit::SESSION_REVOKED,
            OperatorSessionAudit::LOGOUT,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function breadcrumbs(): array
    {
        return [
            ['label' => 'dashboard', 'url' => route('dashboard')],
            ['label' => 'operadores de pdv', 'url' => route('pdv-operators.index')],
        ];
    }
}
