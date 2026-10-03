<?php

namespace App\Modules\Tenancy\Application;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Orquestra ONB-01B sem mudar o contexto do estabelecimento em uso. */
class CriarClienteLucraOne
{
    public function __construct(private ProvisionarEstabelecimento $provisionamento) {}

    /**
     * @param  array<string, mixed>  $dados  Payload validado por StoreOnboardingRequest.
     * @return array{tenant: Tenant, administrador: User, identidadeNova: bool}
     */
    public function criar(User $operador, array $dados): array
    {
        // Bootstrap da autoridade local é uma operação de plataforma, como
        // em ONB-01A. Não usa autoridade do tenant de origem para delegar em
        // outro, nem aceita papéis arbitrários. E2 continua no CRUD de equipe.
        Gate::forUser($operador)->authorize('create', Tenant::class);

        return DB::transaction(function () use ($operador, $dados) {
            $administrador = User::withTrashed()->where('email', $dados['administrator_email'])->lockForUpdate()->first();
            $identidadeNova = $administrador === null;

            // Mesma elegibilidade de UserController: jamais restaurar ou
            // reativar identidade global como efeito de um novo vínculo (E3).
            if ($administrador && ($administrador->trashed() || ! $administrador->isActive())) {
                throw ValidationException::withMessages([
                    'administrator_email' => 'Este e-mail pertence a uma conta indisponível para vínculo. Escolha uma pessoa com conta ativa.',
                ]);
            }

            if ($administrador?->is($operador) && empty($dados['keep_platform_access'])) {
                throw ValidationException::withMessages([
                    'keep_platform_access' => 'Você escolheu sua própria conta como administrador. Para assumir essa função, mantenha seu acesso ou escolha outro administrador.',
                ]);
            }

            $tenant = Tenant::create([
                ...Arr::only($dados, ['name', 'slug', 'status', 'plan', 'timezone', 'locale', 'currency']),
                'id' => (string) Str::ulid(),
                'active' => in_array($dados['status'], ['TRIAL', 'ACTIVE'], true),
            ]);
            $this->provisionamento->provisionarMatriz($tenant);

            if ($identidadeNova) {
                $administrador = User::create([
                    'id' => (string) Str::ulid(),
                    'name' => $dados['administrator_name'], 'email' => $dados['administrator_email'],
                    'password' => $dados['password'], 'status' => User::STATUS_ACTIVE,
                    'email_verified_at' => now(),
                ]);
            }

            $this->provisionamento->atribuirAdministrador($tenant, $administrador);

            if (! empty($dados['configure_company'])) {
                Company::create([
                    ...Arr::only($dados['company'], [
                        'legal_name', 'trade_name', 'document', 'state_registration',
                        'municipal_registration', 'email', 'phone', 'status',
                    ]),
                    'id' => (string) Str::ulid(), 'tenant_id' => $tenant->id,
                ]);
            }

            if (! empty($dados['keep_platform_access']) && ! $administrador->is($operador)) {
                // Papel existente usado no fluxo anterior: necessário para
                // configurar empresa, catálogo e equipe, sem nova autoridade.
                $this->provisionamento->atribuirAdministrador($tenant, $operador);
            }

            return compact('tenant', 'administrador', 'identidadeNova');
        });
    }
}
