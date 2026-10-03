<?php

namespace Tests\Feature\Admin;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Authorization\Domain\StandardRoleMatrix;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Application\TenantResolver;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('onb-01b')]
class OnboardingGuiadoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $origem;

    private User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->origem = Tenant::factory()->active()->create();
        $this->operador = User::factory()->platformAdmin()->forTenant($this->origem)->create();
        $this->actingAs($this->operador);
    }

    public function test_wizard_tem_quatro_passos_sem_gravar_nada(): void
    {
        $antes = $this->contagens();
        $this->get(route('tenants.create'))->assertOk()
            ->assertSee('Novo cliente')->assertSee('1 — Estabelecimento')
            ->assertSee('2 — Administrador')->assertSee('3 — Configuração inicial')
            ->assertSee('4 — Revisão')->assertSee('Criar cliente')
            ->assertSee('Manter meu acesso a este estabelecimento após a criação?')
            ->assertDontSee('criar tenant');
        $this->assertSame($antes, $this->contagens());
    }

    public function test_novo_administrador_tem_senha_hash_e_matriz_completa_no_novo_estabelecimento(): void
    {
        $this->post('/tenants/onboarding', $this->dados())->assertSessionHasNoErrors()->assertRedirect();
        $tenant = Tenant::where('slug', 'cliente-guiado')->firstOrFail();
        $admin = User::where('email', 'admin@cliente.test')->firstOrFail();
        $this->assertTrue(Hash::check('senha-inicial-123', $admin->password));
        $this->assertTrue($admin->canAccessTenant($tenant->id));
        $this->assertTrue($admin->hasRole('admin', $tenant->id));
        $this->assertFalse($admin->isPlatformAdmin());
        $this->assertFalse($admin->canAccessTenant($this->origem->id));
        $this->assertDatabaseCount('companies', 0);
        $roles = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();
        $this->assertCount(4, $roles);
        $this->assertSame(28, Permission::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        foreach (StandardRoleMatrix::papeis() as $nome => $definicao) {
            $esperadas = $definicao['permissoes'];
            sort($esperadas);
            $this->assertSame($esperadas, $roles->firstWhere('name', $nome)->permissionsForTenant()->orderBy('name')->pluck('name')->all());
        }
        $this->assertCount(26, $roles->firstWhere('name', 'admin')->permissionsForTenant()->get());
    }

    public function test_identidade_existente_recebe_apenas_vinculo_e_papel_sem_alteracoes_globais(): void
    {
        $pessoa = User::factory()->forTenant($this->origem)->create(['email' => 'existente@cliente.test']);
        $antes = $pessoa->fresh()->getRawOriginal();
        $this->post('/tenants/onboarding', $this->dados([
            'administrator_email' => $pessoa->email,
            'administrator_name' => 'Nome imposto',
            'password' => 'senha-imposta-123',
            'password_confirmation' => 'senha-imposta-123',
            'account_status' => 'INACTIVE',
            'is_platform_admin' => true,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($antes === $pessoa->fresh()->getRawOriginal(), 'nenhum atributo global, inclusive senha, pode mudar');
        $tenant = Tenant::where('slug', 'cliente-guiado')->firstOrFail();
        $this->assertTrue($pessoa->fresh()->hasRole('admin', $tenant->id));
        $this->assertTrue($pessoa->fresh()->canAccessTenant($this->origem->id));
        $this->assertSame(2, User::count());
    }

    public function test_identidade_existente_nao_exige_nova_senha_nem_nome(): void
    {
        $pessoa = User::factory()->create(['email' => 'existente@cliente.test']);
        $dados = $this->dados(['administrator_email' => $pessoa->email]);
        unset($dados['administrator_name'], $dados['password'], $dados['password_confirmation']);
        $this->post('/tenants/onboarding', $dados)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($pessoa->fresh()->hasRole('admin', Tenant::where('slug', 'cliente-guiado')->firstOrFail()->id));
    }

    public static function estadosIndisponiveis(): array
    {
        return ['inativa' => [false], 'arquivada' => [true]];
    }

    #[DataProvider('estadosIndisponiveis')]
    public function test_conta_indisponivel_e_recusada_sem_estado_parcial(bool $arquivada): void
    {
        $pessoa = User::factory()->create(['email' => 'indisponivel@cliente.test', 'status' => $arquivada ? 'ACTIVE' : 'INACTIVE']);
        if ($arquivada) {
            $pessoa->delete();
        }
        $antes = $this->contagens();
        $global = $pessoa->fresh()->getRawOriginal();
        $this->from(route('tenants.create'))->post('/tenants/onboarding', $this->dados(['administrator_email' => $pessoa->email]))
            ->assertRedirect(route('tenants.create'))->assertSessionHasErrors('administrator_email');
        $this->assertSame($antes, $this->contagens());
        $this->assertTrue($global === $pessoa->fresh()->getRawOriginal());
    }

    public function test_company_opcional_e_criada_somente_no_novo_estabelecimento(): void
    {
        $this->post('/tenants/onboarding', $this->dados([
            'configure_company' => '1',
            'company' => ['legal_name' => 'Empresa Guiada Ltda', 'document' => '123', 'status' => 'ACTIVE', 'tenant_id' => $this->origem->id],
        ]))->assertSessionHasNoErrors();
        $tenant = Tenant::where('slug', 'cliente-guiado')->firstOrFail();
        $this->assertDatabaseHas('companies', ['tenant_id' => $tenant->id, 'legal_name' => 'Empresa Guiada Ltda', 'status' => 'ACTIVE']);
        $this->assertDatabaseMissing('companies', ['tenant_id' => $this->origem->id]);
    }

    public function test_company_desmarcada_nao_valida_nem_grava_campos_residuais(): void
    {
        $this->post('/tenants/onboarding', $this->dados(['configure_company' => '0', 'company' => ['email' => 'invalido']]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('companies', 0);
    }

    public function test_default_nao_deixa_vinculo_nem_papel_do_operador(): void
    {
        $dados = $this->dados();
        unset($dados['keep_platform_access']);
        $this->post('/tenants/onboarding', $dados)->assertSessionHasNoErrors();
        $tenant = Tenant::where('slug', 'cliente-guiado')->firstOrFail();
        $this->assertDatabaseMissing('tenant_user', ['tenant_id' => $tenant->id, 'user_id' => $this->operador->id]);
        $this->assertDatabaseMissing('user_role', ['tenant_id' => $tenant->id, 'user_id' => $this->operador->id]);
        $this->assertTrue($this->operador->fresh()->isPlatformAdmin());
    }

    public function test_escolha_sim_concede_acesso_local_explicito_sem_mudar_autoridade_global(): void
    {
        $this->post('/tenants/onboarding', $this->dados(['keep_platform_access' => '1']))->assertSessionHasNoErrors();
        $tenant = Tenant::where('slug', 'cliente-guiado')->firstOrFail();
        $this->assertTrue($this->operador->fresh()->canAccessTenant($tenant->id));
        $this->assertTrue($this->operador->fresh()->hasRole('admin', $tenant->id));
        $this->assertTrue($this->operador->fresh()->isPlatformAdmin());
        $this->assertSame(2, DB::table('tenant_user')->where('tenant_id', $tenant->id)->count());
    }

    public function test_operador_nao_pode_ser_admin_do_cliente_e_ao_mesmo_tempo_recusar_acesso(): void
    {
        $antes = $this->contagens();
        $this->post('/tenants/onboarding', $this->dados(['administrator_email' => $this->operador->email]))
            ->assertSessionHasErrors('keep_platform_access');
        $this->assertSame($antes, $this->contagens());
    }

    public static function camposInvalidos(): array
    {
        return [
            'nome' => ['name', ''], 'slug' => ['slug', 'slug invalido'],
            'status' => ['status', 'ARCHIVED'], 'plano' => ['plan', 'premium'],
            'fuso' => ['timezone', 'Mars/Phobos'], 'idioma' => ['locale', 'es-ES'],
            'moeda' => ['currency', 'EUR'], 'nome admin' => ['administrator_name', ''],
            'email admin' => ['administrator_email', 'invalido'], 'senha curta' => ['password', '123'],
            'confirmacao' => ['password_confirmation', 'diferente'],
            'escolha acesso' => ['keep_platform_access', 'talvez'],
            'escolha empresa' => ['configure_company', 'talvez'],
        ];
    }

    #[DataProvider('camposInvalidos')]
    public function test_validacao_nao_deixa_entidades_parciais(string $campo, string $valor): void
    {
        $antes = $this->contagens();
        $erro = $campo === 'password_confirmation' ? 'password' : $campo;
        $this->from(route('tenants.create'))->post('/tenants/onboarding', $this->dados([$campo => $valor]))
            ->assertRedirect(route('tenants.create'))->assertSessionHasErrors($erro);
        $this->assertSame($antes, $this->contagens());
        $this->assertNull(session('_old_input.password'));
        $this->assertNull(session('_old_input.password_confirmation'));
    }

    public function test_slug_duplicado_e_recusado(): void
    {
        Tenant::factory()->create(['slug' => 'cliente-guiado']);
        $antes = $this->contagens();
        $this->post('/tenants/onboarding', $this->dados())->assertSessionHasErrors('slug');
        $this->assertSame($antes, $this->contagens());
    }

    public function test_company_invalida_recusa_toda_a_criacao_preservando_dados_nao_secretos(): void
    {
        $antes = $this->contagens();
        $this->from(route('tenants.create'))->post('/tenants/onboarding', $this->dados([
            'configure_company' => '1', 'company' => ['legal_name' => '', 'email' => 'invalido', 'status' => 'BAD'],
        ]))->assertSessionHasErrors(['company.legal_name', 'company.email', 'company.status']);
        $this->assertSame($antes, $this->contagens());
        $this->assertSame('Cliente Guiado', session('_old_input.name'));
        $this->assertNull(session('_old_input.password'));
        $this->get(route('tenants.create'))->assertOk()->assertSee('Cliente Guiado')->assertSee('Configuração inicial');
    }

    public function test_falha_durante_provisionamento_desfaz_matriz_e_estabelecimento(): void
    {
        $antes = $this->contagens();
        $real = app(ProvisionarEstabelecimento::class);
        $this->mock(ProvisionarEstabelecimento::class, function ($mock) use ($real) {
            $mock->shouldReceive('provisionarMatriz')->once()->andReturnUsing(function (Tenant $tenant) use ($real) {
                $real->provisionarMatriz($tenant);
                throw new \RuntimeException('Falha de provisionamento');
            });
        });
        $this->withoutExceptionHandling();
        try {
            $this->post('/tenants/onboarding', $this->dados());
            $this->fail('a falha precisa propagar e desfazer a transação');
        } catch (\RuntimeException $e) {
            $this->assertSame('Falha de provisionamento', $e->getMessage());
        }
        $this->assertSame($antes, $this->contagens());
    }

    public function test_falha_na_company_desfaz_usuario_vinculos_e_papeis_ja_criados(): void
    {
        $antes = $this->contagens();
        // Restrição real de banco após provisionar usuário e matriz, sem mock do serviço.
        DB::unprepared("CREATE TRIGGER recusar_company BEFORE INSERT ON companies BEGIN SELECT RAISE(ABORT, 'company recusada'); END");
        $this->withoutExceptionHandling();
        try {
            $this->post('/tenants/onboarding', $this->dados([
                'configure_company' => '1', 'company' => ['legal_name' => 'Empresa', 'status' => 'ACTIVE'],
            ]));
            $this->fail('a restrição precisa desfazer toda a transação');
        } catch (QueryException $e) {
            $this->assertStringContainsString('company recusada', $e->getMessage());
        }
        $this->assertSame($antes, $this->contagens());
    }

    public function test_sucesso_orienta_sem_senha_e_sem_atalhos_para_o_estabelecimento_errado(): void
    {
        $resposta = $this->post('/tenants/onboarding', $this->dados())->assertRedirect();
        $this->get($resposta->headers->get('Location'))->assertOk()
            ->assertSee('Cliente criado com sucesso')->assertSee('Cliente Guiado')
            ->assertSee('admin@cliente.test')->assertSee('Empresa pendente')
            ->assertSee('Próximos passos')->assertSee('administrador do cliente')
            ->assertSee(route('tenants.index'), false)
            ->assertDontSee('senha-inicial-123')->assertDontSee('href="#"', false)
            ->assertDontSee('name="tenant_id"', false);
    }

    public function test_atalhos_de_sucesso_selecionam_o_novo_estabelecimento_com_rotas_reais(): void
    {
        $resposta = $this->post('/tenants/onboarding', $this->dados(['keep_platform_access' => '1']))->assertRedirect();
        $tenant = Tenant::where('slug', 'cliente-guiado')->firstOrFail();
        $this->get($resposta->headers->get('Location'))->assertOk()
            ->assertSee(route('estabelecimentos.definir'), false)->assertSee('Entrar no estabelecimento')
            ->assertSee('Configurar empresa')->assertSee('Cadastrar primeira categoria')
            ->assertSee('Cadastrar primeiro produto')->assertSee('Cadastrar equipe')
            ->assertDontSee('href="#"', false);
        foreach (['dashboard', 'companies.create', 'catalog.categories.create', 'catalog.products.create', 'users.create'] as $destino) {
            $this->post(route('estabelecimentos.definir'), ['tenant_id' => $tenant->id, 'destino' => $destino])
                ->assertRedirect(route($destino));
            $this->assertSame($tenant->id, session(TenantResolver::SESSAO_TENANT));
        }
    }

    public function test_seletor_recusa_destino_arbitrario(): void
    {
        $this->post(route('estabelecimentos.definir'), ['tenant_id' => $this->origem->id, 'destino' => 'https://externo.test'])
            ->assertSessionHasErrors('destino');
    }

    private function dados(array $extras = []): array
    {
        return [
            'name' => 'Cliente Guiado', 'slug' => 'cliente-guiado', 'status' => 'TRIAL', 'plan' => 'free',
            'timezone' => 'America/Sao_Paulo', 'locale' => 'pt-BR', 'currency' => 'BRL',
            'administrator_name' => 'Maria Cliente', 'administrator_email' => 'admin@cliente.test',
            'password' => 'senha-inicial-123', 'password_confirmation' => 'senha-inicial-123',
            'configure_company' => '0', 'keep_platform_access' => '0', ...$extras,
        ];
    }

    private function contagens(): array
    {
        return collect(['tenants', 'users', 'tenant_user', 'roles', 'permissions', 'role_permission', 'user_role', 'companies'])
            ->mapWithKeys(fn ($tabela) => [$tabela => DB::table($tabela)->count()])->all();
    }
}
