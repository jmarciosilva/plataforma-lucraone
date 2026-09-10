<?php

namespace Database\Factories;

use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'status' => User::STATUS_ACTIVE,
            'is_platform_admin' => false,
            'last_login_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Associa a pessoa a um estabelecimento.
     *
     * Forma preferida de expressar "usuário deste tenant" desde o F1.8.
     */
    public function forTenant(Tenant|string $tenant, string $status = TenantUser::STATUS_ACTIVE): static
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $this->afterCreating(
            fn (User $user) => $user->joinTenant($tenantId, $status)
        );
    }

    /**
     * Compatibilidade: `create(['tenant_id' => $id])` continua significando
     * "usuário deste estabelecimento" — só que agora isso é um vínculo em
     * tenant_user, não uma coluna em users.
     *
     * Traduzir aqui mantém as ~94 chamadas existentes válidas sem espalhar
     * conhecimento do schema antigo pelos testes. Em código novo, prefira
     * `forTenant()`, que é explícito.
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        $tenantId = $attributes['tenant_id'] ?? null;
        unset($attributes['tenant_id']);

        $resultado = parent::create($attributes, $parent);

        if ($tenantId !== null) {
            $usuarios = $resultado instanceof Collection ? $resultado : collect([$resultado]);

            foreach ($usuarios as $usuario) {
                $usuario->joinTenant($tenantId);
            }
        }

        return $resultado;
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Operador da plataforma, independente de qualquer papel de tenant.
     */
    public function platformAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_platform_admin' => true,
        ]);
    }

    /**
     * Conta desativada globalmente — não entra em estabelecimento nenhum.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => User::STATUS_INACTIVE,
        ]);
    }

    /**
     * Convidado: a conta existe, mas o vínculo ainda não foi aceito.
     * O status de convite vive no vínculo, não na identidade.
     */
    public function invited(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Vínculo suspenso neste estabelecimento.
     */
    public function suspendedIn(Tenant|string $tenant): static
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $this->afterCreating(
            fn (User $user) => $user->joinTenant($tenantId, TenantUser::STATUS_SUSPENDED)
        );
    }

    public function forCurrentTenant(string $tenantId): static
    {
        return $this->forTenant($tenantId);
    }

    public function neverLoggedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_login_at' => null,
        ]);
    }
}
