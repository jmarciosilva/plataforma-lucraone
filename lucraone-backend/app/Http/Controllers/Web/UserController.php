<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Authorization\Application\UserAdministrationDenied;
use App\Modules\Authorization\Application\UserAdministrationGuard;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    private const VINCULO_STATUS = [
        TenantUser::STATUS_ACTIVE => 'active',
        TenantUser::STATUS_INVITED => 'invited',
        TenantUser::STATUS_INACTIVE => 'inactive',
        TenantUser::STATUS_SUSPENDED => 'suspended',
    ];

    private const CONTA_STATUS = [
        User::STATUS_ACTIVE => 'active',
        User::STATUS_INACTIVE => 'inactive',
    ];

    /**
     * Recusas sobre a identidade global. Não dizem o motivo — outro vínculo,
     * Platform Admin, conta inativa ou arquivada — para não revelar a este
     * estabelecimento o estado da conta.
     */
    private const IDENTIDADE_PROTEGIDA = 'os dados da conta desta pessoa não podem ser alterados por este estabelecimento.';

    private const CONTA_INDISPONIVEL = 'este e-mail pertence a uma conta que não pode ser vinculada por este estabelecimento.';

    public function __construct(
        private UserAdministrationGuard $administracao
    ) {}

    public function index(Request $request, TenantContext $context)
    {
        Gate::authorize('viewAny', User::class);

        $tenantId = $context->id();
        $users = $this->usersDoTenant($tenantId)
            ->when($request->input('trashed') === 'with', fn ($query) => $query->withTrashed())
            ->when($request->input('trashed') === 'only', fn ($query) => $query->onlyTrashed())
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('tenant_user.status', $request->string('status')->toString()));

        $sort = $request->input('sort', 'created_desc');
        match ($sort) {
            'name' => $users->orderBy('users.name'),
            'email' => $users->orderBy('users.email'),
            'status' => $users->orderBy('tenant_user.status')->orderBy('users.name'),
            'created_asc' => $users->orderBy('users.created_at'),
            default => $users->orderByDesc('users.created_at'),
        };

        return view('users.index', [
            'users' => $users->paginate(20)->withQueryString(),
            'tenantNome' => $context->tenant()->name,
            'tenantId' => $tenantId,
            'breadcrumbs' => $this->breadcrumbs(),
            'statusOptions' => self::VINCULO_STATUS,
            'sortOptions' => [
                'created_desc' => 'mais recentes',
                'created_asc' => 'mais antigos',
                'name' => 'nome',
                'email' => 'email',
                'status' => 'status',
            ],
            'trashedOptions' => [
                '' => 'ativos',
                'with' => 'ativos e arquivados',
                'only' => 'somente arquivados',
            ],
        ]);
    }

    public function create(TenantContext $context)
    {
        Gate::authorize('create', User::class);

        return view('users.create', [
            'user' => new User(['status' => User::STATUS_ACTIVE]),
            'membershipStatus' => TenantUser::STATUS_ACTIVE,
            'selectedRoles' => [],
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => 'novo']],
            ...$this->formOptions($context->id()),
        ]);
    }

    public function store(StoreUserRequest $request, TenantContext $context)
    {
        $tenantId = $context->id();
        $papeis = $request->validated('roles', []);

        $cadastrar = function () use ($request, $tenantId, $papeis) {
            $user = User::withTrashed()
                ->where('email', $request->validated('email'))
                ->first();

            // Conta existente só ganha vínculo e papéis aqui: nome e senha
            // continuam os dela. Reativar ou restaurar é decisão sobre a
            // identidade global, então a recusa vem antes de qualquer gravação.
            if ($user && ($user->trashed() || ! $user->isActive())) {
                throw ValidationException::withMessages(['email' => self::CONTA_INDISPONIVEL]);
            }

            // E2: a autoridade sobre os papéis pedidos também é conferida antes
            // de qualquer gravação, sem deixar identidade, vínculo ou papel parciais.
            $this->administracao->garantirCadastro($request->user(), $tenantId, $papeis);

            $identidadeNova = $user === null;

            if ($identidadeNova) {
                $user = new User([
                    'id' => (string) Str::ulid(),
                    'email' => $request->validated('email'),
                    'password' => $request->validated('password'),
                    'email_verified_at' => now(),
                ]);

                $user->forceFill([
                    'name' => $request->validated('name'),
                    'status' => User::STATUS_ACTIVE,
                ])->save();
            }

            $user->joinTenant($tenantId, $request->validated('status'));
            $user->syncRoles($papeis, $tenantId);

            return [$user, $identidadeNova];
        };

        try {
            [$user, $identidadeNova] = DB::transaction($cadastrar);
        } catch (UserAdministrationDenied $recusa) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('erro', $recusa->getMessage());
        }

        return redirect()
            ->route('users.show', $user)
            ->with('sucesso', $identidadeNova ? 'usuário criado.' : 'usuário vinculado. os dados da conta existente foram mantidos.');
    }

    public function show(string $user, TenantContext $context)
    {
        $tenantId = $context->id();
        $user = $this->encontrarUsuarioNoTenant($user, $tenantId, withTrashed: true);

        Gate::authorize('view', $user);

        return view('users.show', [
            'user' => $user,
            'membership' => $user->membershipFor($tenantId),
            'roles' => $user->rolesForTenant($tenantId)->orderBy('name')->get(),
            'latestActions' => AuditLog::forUser($user->id)
                ->where('tenant_id', $tenantId)
                ->latest()
                ->limit(5)
                ->get(),
            'tenantNome' => $context->tenant()->name,
            'tenantId' => $tenantId,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $user->name]],
        ]);
    }

    public function edit(string $user, TenantContext $context)
    {
        $tenantId = $context->id();
        $user = $this->encontrarUsuarioNoTenant($user, $tenantId, withTrashed: true);

        Gate::authorize('update', $user);

        return view('users.edit', [
            'user' => $user,
            'membershipStatus' => $user->membershipFor($tenantId)?->status,
            'selectedRoles' => $user->rolesForTenant($tenantId)->pluck('roles.id')->all(),
            'identidadeGlobalProtegida' => Gate::denies('manageGlobalIdentity', [$user, $tenantId]),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $user->name, 'url' => route('users.show', $user)], ['label' => 'editar']],
            ...$this->formOptions($tenantId),
        ]);
    }

    public function update(UpdateUserRequest $request, string $user, TenantContext $context)
    {
        $tenantId = $context->id();
        $user = $this->encontrarUsuarioNoTenant($user, $tenantId);
        $dados = $request->validated();

        if ($this->atingeProprioAcesso($request, $user, $dados['account_status'], $dados['status'])) {
            return back()->withInput()->with('erro', 'não é possível remover seu próprio acesso administrativo.');
        }

        $this->recusarAlteracaoDaIdentidadeGlobal($user, $tenantId, $dados);

        $papeis = $dados['roles'] ?? [];

        // E2: autoridade sobre a pessoa e sobre os papéis pedidos, antes de
        // qualquer gravação de dados, vínculo ou papéis.
        try {
            $this->administracao->garantirEdicao($request->user(), $user, $tenantId, $papeis);
        } catch (UserAdministrationDenied $recusa) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('erro', $recusa->getMessage());
        }

        DB::transaction(function () use ($user, $tenantId, $dados, $papeis) {
            $payload = [
                'name' => $dados['name'],
                'email' => $dados['email'],
                'status' => $dados['account_status'],
            ];

            if (! empty($dados['password'])) {
                $payload['password'] = $dados['password'];
            }

            $user->update($payload);
            $user->joinTenant($tenantId, $dados['status']);
            $user->syncRoles($papeis, $tenantId);
        });

        return redirect()
            ->route('users.show', $user)
            ->with('sucesso', 'usuário atualizado.');
    }

    public function destroy(Request $request, string $user, TenantContext $context)
    {
        $tenantId = $context->id();
        $user = $this->encontrarUsuarioNoTenant($user, $tenantId);

        Gate::authorize('delete', $user);

        if ($request->user()->is($user)) {
            return back()->with('erro', 'não é possível arquivar seu próprio usuário.');
        }

        // E2: arquivar retira todos os papéis e o acesso da pessoa.
        try {
            $this->administracao->garantirAdministracao($request->user(), $user, $tenantId);
        } catch (UserAdministrationDenied $recusa) {
            return back()->with('erro', $recusa->getMessage());
        }

        DB::transaction(function () use ($user, $tenantId) {
            $user->syncRoles([], $tenantId);

            // Identidade global: se a pessoa também existe em outro tenant, ou
            // é Platform Admin, remover só o vínculo atual evita derrubar
            // acessos que este estabelecimento não administra.
            if (! $user->pertenceExclusivamenteAo($tenantId)) {
                $user->joinTenant($tenantId, TenantUser::STATUS_INACTIVE);

                return;
            }

            $user->delete();
        });

        return redirect()
            ->route('users.index', ['trashed' => 'with'])
            ->with('sucesso', 'usuário arquivado.');
    }

    public function restore(string $user, TenantContext $context)
    {
        $tenantId = $context->id();
        $user = $this->encontrarUsuarioNoTenant($user, $tenantId, onlyTrashed: true);

        Gate::authorize('restore', $user);
        Gate::authorize('manageGlobalIdentity', [$user, $tenantId]);

        $user->restore();
        $user->joinTenant($tenantId, TenantUser::STATUS_ACTIVE);

        return redirect()
            ->route('users.show', $user)
            ->with('sucesso', 'usuário restaurado.');
    }

    public function resetPassword(Request $request, string $user, TenantContext $context)
    {
        $tenantId = $context->id();
        $user = $this->encontrarUsuarioNoTenant($user, $tenantId);

        Gate::authorize('manageGlobalIdentity', [$user, $tenantId]);

        // E2: a senha temporária é exibida a quem redefine, então redefinir a
        // senha de quem tem mais autoridade seria assumir essa autoridade.
        try {
            $this->administracao->garantirAdministracao($request->user(), $user, $tenantId);
        } catch (UserAdministrationDenied $recusa) {
            return back()->with('erro', $recusa->getMessage());
        }

        $senhaTemporaria = Str::random(12);
        $user->update(['password' => $senhaTemporaria]);

        return redirect()
            ->route('users.show', $user)
            ->with('sucesso', 'senha redefinida.')
            ->with('senha_temporaria', $senhaTemporaria);
    }

    private function usersDoTenant(string $tenantId): Builder
    {
        return User::query()
            ->select('users.*')
            ->join('tenant_user', 'tenant_user.user_id', '=', 'users.id')
            ->where('tenant_user.tenant_id', $tenantId)
            ->with(['memberships' => fn ($query) => $query->where('tenant_id', $tenantId)]);
    }

    private function encontrarUsuarioNoTenant(string $userId, string $tenantId, bool $withTrashed = false, bool $onlyTrashed = false): User
    {
        $query = User::query();

        if ($onlyTrashed) {
            $query->onlyTrashed();
        } elseif ($withTrashed) {
            $query->withTrashed();
        }

        return $query
            ->whereKey($userId)
            ->whereHas('memberships', fn ($query) => $query->where('tenant_id', $tenantId))
            ->firstOrFail();
    }

    private function atingeProprioAcesso(Request $request, User $user, string $accountStatus, string $membershipStatus): bool
    {
        return $request->user()->is($user)
            && (
                $accountStatus !== User::STATUS_ACTIVE
                || $membershipStatus !== TenantUser::STATUS_ACTIVE
            );
    }

    /**
     * Nome, e-mail, status da conta e senha são da identidade global.
     *
     * O formulário sempre reenvia nome, e-mail e status: repetir o valor atual
     * não é alteração, e a gestão de vínculo e papéis segue livre. Mudar um
     * deles exige administrar a identidade, e a recusa é explícita para não
     * parecer que a alteração foi salva.
     */
    private function recusarAlteracaoDaIdentidadeGlobal(User $user, string $tenantId, array $dados): void
    {
        $alterados = array_filter([
            'name' => $dados['name'] !== $user->name,
            'email' => $dados['email'] !== $user->email,
            'account_status' => $dados['account_status'] !== $user->status,
            'password' => ! empty($dados['password']),
        ]);

        if ($alterados === [] || Gate::allows('manageGlobalIdentity', [$user, $tenantId])) {
            return;
        }

        throw ValidationException::withMessages(
            array_map(fn () => self::IDENTIDADE_PROTEGIDA, $alterados)
        );
    }

    private function breadcrumbs(): array
    {
        return [
            ['label' => 'dashboard', 'url' => route('dashboard')],
            ['label' => 'usuários', 'url' => route('users.index')],
        ];
    }

    private function formOptions(string $tenantId): array
    {
        return [
            'statusOptions' => self::VINCULO_STATUS,
            'accountStatusOptions' => self::CONTA_STATUS,
            'roles' => Role::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(),
        ];
    }
}
