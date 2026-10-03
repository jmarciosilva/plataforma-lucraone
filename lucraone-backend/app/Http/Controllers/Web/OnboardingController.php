<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOnboardingRequest;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\CriarClienteLucraOne;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Application\TenantResolver;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OnboardingController extends Controller
{
    public function store(StoreOnboardingRequest $request, CriarClienteLucraOne $onboarding, TenantContext $context)
    {
        $resultado = $onboarding->criar($request->user(), $request->validated());
        // O vínculo único de origem pode deixar de ser único ao manter acesso
        // ao cliente novo. Preserve a escolha atual para chegar à tela de sucesso.
        $request->session()->put(TenantResolver::SESSAO_TENANT, $context->id());

        return redirect()->route('tenants.onboarding.success', $resultado['tenant'])
            ->with('onboarding', [
                'tenant' => $resultado['tenant']->id,
                'administrador' => $resultado['administrador']->id,
                'identidadeNova' => $resultado['identidadeNova'],
            ]);
    }

    /** Consulta informativa somente para Platform Admin; nunca altera identidade. */
    public function administrator(Request $request)
    {
        Gate::authorize('create', Tenant::class);
        $dados = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $usuario = User::withTrashed()->where('email', $dados['email'])->first();

        return response()->json([
            'exists' => $usuario !== null,
            'available' => $usuario === null || (! $usuario->trashed() && $usuario->isActive()),
            'name' => $usuario && ! $usuario->trashed() && $usuario->isActive() ? $usuario->name : null,
        ])->header('Cache-Control', 'no-store');
    }

    public function success(Request $request, string $tenant, TenantContext $context)
    {
        Gate::authorize('create', Tenant::class);
        $cliente = Tenant::findOrFail($tenant);
        $resultado = $request->session()->get('onboarding');

        if (($resultado['tenant'] ?? null) !== $cliente->id) {
            return redirect()->route('tenants.show', $cliente);
        }

        $administrador = $cliente->users()->where('users.id', $resultado['administrador'])->firstOrFail();

        return view('tenants.onboarding-success', [
            'tenant' => $cliente,
            'administrador' => $administrador,
            'identidadeNova' => $resultado['identidadeNova'],
            // Contexto permanece o de origem; leitura explícita só do cliente criado.
            'empresaConfigurada' => $cliente->companies()->withoutGlobalScopes()->exists(),
            'podeEntrar' => $request->user()->canAccessTenant($cliente->id) && $cliente->isActive(),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [
                ['label' => 'clientes do LucraOne', 'url' => route('tenants.index')],
                ['label' => 'cliente criado'],
            ],
        ]);
    }
}
