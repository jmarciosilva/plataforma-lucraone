<?php

namespace Tests\Feature\Security;

use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('onb-01b')]
class OnboardingSecurityTest extends TestCase
{
    use RefreshDatabase;

    public static function entradas(): array
    {
        return ['wizard' => ['GET', '/tenants/create'], 'confirmacao' => ['POST', '/tenants/onboarding'], 'consulta identidade' => ['GET', '/tenants/onboarding/administrator?email=alvo@cliente.test']];
    }

    #[DataProvider('entradas')]
    public function test_admin_local_nao_acessa_onboarding(string $metodo, string $url): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = User::factory()->forTenant($tenant)->create();
        $provisionamento = app(ProvisionarEstabelecimento::class);
        $provisionamento->provisionarMatriz($tenant);
        $provisionamento->atribuirAdministrador($tenant, $admin);
        $this->actingAs($admin)->call($metodo, $url)->assertForbidden();
        $this->assertDatabaseCount('tenants', 1);
    }

    public function test_consulta_identidade_e_apenas_informativa_e_exclusiva_da_plataforma(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $operador = User::factory()->platformAdmin()->forTenant($tenant)->create();
        $pessoa = User::factory()->create(['email' => 'alvo@cliente.test', 'name' => 'Pessoa Existente']);
        $antes = $pessoa->fresh()->getRawOriginal();
        $this->actingAs($operador)->getJson('/tenants/onboarding/administrator?email=alvo@cliente.test')
            ->assertOk()->assertExactJson(['exists' => true, 'available' => true, 'name' => 'Pessoa Existente']);
        $this->assertTrue($antes === $pessoa->fresh()->getRawOriginal());
        $this->assertDatabaseMissing('tenant_user', ['user_id' => $pessoa->id]);
    }

    public function test_consulta_nova_identidade_nao_expoe_dados_tecnicos(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $operador = User::factory()->platformAdmin()->forTenant($tenant)->create();
        $this->actingAs($operador)->getJson('/tenants/onboarding/administrator?email=nova@cliente.test')
            ->assertOk()->assertExactJson(['exists' => false, 'available' => true, 'name' => null]);
    }

    public function test_payload_nao_define_autoridade_global_nem_papeis_de_outro_tenant(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $operador = User::factory()->platformAdmin()->forTenant($tenant)->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($tenant);
        $papel = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->actingAs($operador)->post('/tenants/onboarding', [
            'name' => 'Cliente Seguro', 'status' => 'TRIAL', 'plan' => 'free', 'timezone' => 'UTC', 'locale' => 'pt-BR', 'currency' => 'BRL',
            'administrator_name' => 'Admin Cliente', 'administrator_email' => 'seguro@cliente.test',
            'password' => 'senha-segura-123', 'password_confirmation' => 'senha-segura-123',
            'is_platform_admin' => true, 'tenant_id' => $tenant->id, 'roles' => [$papel->id], 'account_status' => 'INACTIVE',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $pessoa = User::where('email', 'seguro@cliente.test')->firstOrFail();
        $novo = Tenant::where('slug', 'cliente-seguro')->firstOrFail();
        $this->assertFalse($pessoa->isPlatformAdmin());
        $this->assertSame('ACTIVE', $pessoa->status);
        $this->assertTrue($pessoa->hasRole('admin', $novo->id));
        $this->assertFalse($pessoa->canAccessTenant($tenant->id));
        $this->assertDatabaseMissing('user_role', ['user_id' => $pessoa->id, 'role_id' => $papel->id]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_sem_vinculo_nao_e_possivel_usar_atalho_para_entrar_no_cliente(): void
    {
        $origem = Tenant::factory()->active()->create();
        $alvo = Tenant::factory()->active()->create();
        $operador = User::factory()->platformAdmin()->forTenant($origem)->create();
        $this->actingAs($operador)->post(route('estabelecimentos.definir'), ['tenant_id' => $alvo->id, 'destino' => 'companies.create'])
            ->assertSessionHas('erro');
        $this->assertNotSame($alvo->id, session('tenant_ativo'));
    }

    public function test_sucesso_tambem_exige_autoridade_de_plataforma(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = User::factory()->forTenant($tenant)->create();
        $this->actingAs($admin)->get('/tenants/'.$tenant->id.'/onboarding')->assertForbidden();
    }
}
