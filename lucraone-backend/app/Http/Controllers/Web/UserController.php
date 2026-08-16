<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

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

        $user = DB::transaction(function () use ($request, $tenantId) {
            $user = User::withTrashed()
                ->where('email', $request->validated('email'))
                ->first();

            if ($user) {
                if ($user->trashed()) {
                    $user->restore();
                }
            } else {
                $user = new User([
                    'id' => (string) Str::ulid(),
                    'email' => $request->validated('email'),
                    'password' => $request->validated('password'),
                    'email_verified_at' => now(),
                ]);
            }

            $user->forceFill([
                'name' => $request->validated('name'),
                'status' => User::STATUS_ACTIVE,
            ])->save();

            $user->joinTenant($tenantId, $request->validated('status'));
            $user->syncRoles($request->validated('roles', []), $tenantId);

            return $user;
        });

        return redirect()
            ->route('users.show', $user)
            ->with('sucesso', 'usuário criado.');
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

        DB::transaction(function () use ($user, $tenantId, $dados) {
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
            $user->syncRoles($dados['roles'] ?? [], $tenantId);
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

        DB::transaction(function () use ($user, $tenantId) {
            $user->syncRoles([], $tenantId);

            // Identidade global: se a pessoa também trabalha em outro tenant,
            // remover só o vínculo atual evita derrubar acessos legítimos.
            if ($user->memberships()->count() > 1) {
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

        $user->restore();
        $user->joinTenant($tenantId, TenantUser::STATUS_ACTIVE);

        return redirect()
            ->route('users.show', $user)
            ->with('sucesso', 'usuário restaurado.');
    }

    public function resetPassword(string $user, TenantContext $context)
    {
        $tenantId = $context->id();
        $user = $this->encontrarUsuarioNoTenant($user, $tenantId);

        Gate::authorize('update', $user);

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
