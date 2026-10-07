<?php

namespace Tests\Feature\Security;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * SEC-01 — personas compartilhadas pelos testes de autorização da API.
 *
 * As personas vêm da matriz real do estabelecimento (StandardRoleMatrix, via
 * ProvisionarEstabelecimento), não de papéis inventados para o teste: é
 * justamente essa matriz que a API precisa passar a respeitar. `viewer` tem as
 * permissões `view-*` e nenhuma `manage-*`, então serve de prova viva de que
 * leitura continua liberada e escrita passa a ser barrada.
 */
abstract class ApiAuthorizationTestCase extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Tenant $outroTenant;

    protected Company $company;

    protected Company $outraCompany;

    /** Papel `manager`: tem as permissões `manage-*` dos módulos de negócio. */
    protected User $gerente;

    /** Papel `viewer`: só `view-*`. */
    protected User $leitor;

    /**
     * Usuário cujo único direito é `create-role`.
     *
     * O SEC-04 removeu o coringa que fazia dessa permissão um passe livre
     * (`6c770dc`). Esta persona existe para que o SEC-01 não o reintroduza por
     * tabela.
     */
    protected User $apenasCreateRole;

    protected string $tokenGerente;

    protected string $tokenLeitor;

    protected string $tokenCreateRole;

    protected function setUp(): void
    {
        parent::setUp();

        $provisionador = app(ProvisionarEstabelecimento::class);

        $this->tenant = Tenant::factory()->active()->create();
        $this->outroTenant = Tenant::factory()->active()->create();
        $provisionador->provisionarMatriz($this->tenant);
        $provisionador->provisionarMatriz($this->outroTenant);

        $this->company = Company::factory()->forCurrentTenant($this->tenant->id)->active()->create();
        $this->outraCompany = Company::factory()->forCurrentTenant($this->outroTenant->id)->active()->create();

        $this->gerente = $this->usuarioComPapel('manager');
        $this->leitor = $this->usuarioComPapel('viewer');
        $this->apenasCreateRole = $this->usuarioApenasComCreateRole();

        $this->tokenGerente = $this->gerente->createToken('test')->plainTextToken;
        $this->tokenLeitor = $this->leitor->createToken('test')->plainTextToken;
        $this->tokenCreateRole = $this->apenasCreateRole->createToken('test')->plainTextToken;
    }

    protected function usuarioComPapel(string $papel): User
    {
        $usuario = User::factory()->forTenant($this->tenant)->create();
        $usuario->assignRole($papel, $this->tenant->id);

        return $usuario;
    }

    private function usuarioApenasComCreateRole(): User
    {
        $papel = Role::factory()->forTenant($this->tenant->id)->create([
            'id' => (string) Str::ulid(),
            'name' => 'sec01-apenas-create-role',
        ]);

        $permissao = Permission::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('name', 'create-role')
            ->firstOrFail();

        $papel->grantPermission($permissao);

        $usuario = User::factory()->forTenant($this->tenant)->create();
        $usuario->assignRole($papel, $this->tenant->id);

        return $usuario;
    }

    /** Requisição autenticada como `manager` no estabelecimento do teste. */
    protected function comoGerente(): self
    {
        return $this->autenticado($this->tokenGerente);
    }

    /** Requisição autenticada como `viewer` no estabelecimento do teste. */
    protected function comoLeitor(): self
    {
        return $this->autenticado($this->tokenLeitor);
    }

    /** Requisição autenticada como portador apenas de `create-role`. */
    protected function comoCreateRole(): self
    {
        return $this->autenticado($this->tokenCreateRole);
    }

    private function autenticado(string $token): self
    {
        return $this->withToken($token)->withHeader('X-Tenant-ID', $this->tenant->id);
    }

    /**
     * Afirma que a resposta é 403 de autorização, e não 401, 404 ou 422.
     *
     * Um 404 significaria que o TenantScope escondeu a entidade — proteção
     * real, mas de outra natureza. O SEC-01 exige que a recusa seja de
     * permissão, então o teste precisa distinguir as duas.
     */
    protected function assertNegadoPorAutorizacao(TestResponse $resposta): void
    {
        $resposta->assertForbidden();
    }
}
