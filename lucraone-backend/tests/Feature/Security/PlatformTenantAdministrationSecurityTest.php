<?php

namespace Tests\Feature\Security;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Concerns\MontaCenariosDeAutorizacao;
use Tests\TestCase;

/**
 * SEC-04 · X1 — administração da plataforma por admin de estabelecimento.
 *
 * Baseline de caracterização: os testes descrevem o comportamento SEGURO.
 * Enquanto o SEC-04 não for corrigido, os que exercitam o vetor falham — a
 * falha é a prova da vulnerabilidade, não um defeito do teste.
 *
 * O ROADMAP fixa 403 para /tenants: gerenciar estabelecimentos é operação da
 * plataforma, e nenhum papel de estabelecimento deve alcançá-la.
 */
#[Group('sec-04')]
#[Group('sec-04-x1')]
class PlatformTenantAdministrationSecurityTest extends TestCase
{
    use MontaCenariosDeAutorizacao, RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $adminA;

    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->active()->create(['name' => 'Loja A', 'slug' => 'loja-a']);
        $this->tenantB = Tenant::factory()->active()->create([
            'name' => 'Loja B',
            'slug' => 'loja-b',
            'plan' => 'free',
        ]);

        $this->provisionarPapeisPadrao();

        // Papel admin real do seed — hoje inclui create-role. Sem vínculo com B.
        $this->adminA = $this->membroComPapel($this->tenantA, 'admin');
        $this->membroComPapel($this->tenantB, 'admin');

        // A autoridade de plataforma não recebe papel nem permissão de tenant.
        $this->platformAdmin = User::factory()
            ->platformAdmin()
            ->forTenant($this->tenantA)
            ->create();
    }

    public function test_admin_de_estabelecimento_nao_lista_estabelecimentos_da_plataforma(): void
    {
        $this->actingAs($this->adminA)
            ->get(route('tenants.index'))
            ->assertForbidden();
    }

    public function test_admin_de_estabelecimento_nao_abre_outro_estabelecimento(): void
    {
        $this->actingAs($this->adminA)
            ->get(route('tenants.show', $this->tenantB))
            ->assertForbidden();
    }

    public function test_admin_de_estabelecimento_nao_abre_edicao_de_outro_estabelecimento(): void
    {
        $this->actingAs($this->adminA)
            ->get(route('tenants.edit', $this->tenantB))
            ->assertForbidden();
    }

    public function test_admin_de_estabelecimento_nao_altera_outro_estabelecimento(): void
    {
        $resposta = $this->actingAs($this->adminA)
            ->put(route('tenants.update', $this->tenantB), [
                ...$this->dadosDeEstabelecimento('Loja B Renomeada', 'SUSPENDED'),
                'slug' => 'loja-b-renomeada',
            ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenantB->id,
            'name' => 'Loja B',
            'slug' => 'loja-b',
            'status' => 'ACTIVE',
            'plan' => 'free',
            'active' => true,
        ]);
        $resposta->assertForbidden();
    }

    public function test_admin_de_estabelecimento_nao_arquiva_outro_estabelecimento(): void
    {
        $resposta = $this->actingAs($this->adminA)
            ->delete(route('tenants.destroy', $this->tenantB));

        $this->assertNotSoftDeleted('tenants', ['id' => $this->tenantB->id]);
        $resposta->assertForbidden();
    }

    public function test_admin_de_estabelecimento_nao_restaura_outro_estabelecimento(): void
    {
        $this->tenantB->delete();

        $resposta = $this->actingAs($this->adminA)
            ->post(route('tenants.restore', $this->tenantB->id));

        $this->assertSoftDeleted('tenants', ['id' => $this->tenantB->id]);
        $resposta->assertForbidden();
    }

    public function test_admin_de_estabelecimento_nao_cadastra_estabelecimento(): void
    {
        $resposta = $this->actingAs($this->adminA)
            ->post(route('tenants.store'), $this->dadosDeEstabelecimento('Loja Criada Por Admin Local'));

        $this->assertDatabaseMissing('tenants', ['slug' => 'loja-criada-por-admin-local']);
        $this->assertSame(
            1,
            $this->adminA->memberships()->count(),
            'o admin local não pode ganhar vínculo administrativo num estabelecimento novo'
        );
        $resposta->assertForbidden();
    }

    /**
     * Rota, método e se a operação mira o outro estabelecimento.
     */
    public static function operacoesDaPlataforma(): array
    {
        return [
            'listar' => ['get', 'tenants.index', false],
            'formulário de cadastro' => ['get', 'tenants.create', false],
            'cadastrar' => ['post', 'tenants.store', false],
            'abrir' => ['get', 'tenants.show', true],
            'formulário de edição' => ['get', 'tenants.edit', true],
            'alterar' => ['put', 'tenants.update', true],
            'arquivar' => ['delete', 'tenants.destroy', true],
            'restaurar' => ['post', 'tenants.restore', true],
        ];
    }

    /**
     * Protege contra a volta do padrão "create-role == superadmin".
     */
    #[DataProvider('operacoesDaPlataforma')]
    public function test_create_role_sozinha_nao_concede_administracao_da_plataforma(
        string $metodo,
        string $rota,
        bool $miraOutroEstabelecimento
    ): void {
        $papel = $this->papelPersonalizado($this->tenantA, 'criador-de-papeis', ['create-role']);
        $usuario = User::factory()->forTenant($this->tenantA)->create();
        $usuario->assignRole($papel, $this->tenantA->id);

        if ($rota === 'tenants.restore') {
            $this->tenantB->delete();
        }

        $url = $miraOutroEstabelecimento ? route($rota, $this->tenantB->id) : route($rota);
        $dados = $metodo === 'get' ? [] : $this->dadosDeEstabelecimento('Cadastrado Pelo Criador De Papeis');

        $this->actingAs($usuario)
            ->call(strtoupper($metodo), $url, $dados)
            ->assertForbidden();
    }

    public function test_platform_admin_sem_create_role_lista_estabelecimentos(): void
    {
        $this->assertFalse($this->platformAdmin->hasPermission('create-role', $this->tenantA->id));

        $this->actingAs($this->platformAdmin)
            ->get(route('tenants.index'))
            ->assertOk()
            ->assertSee('Loja B');
    }

    public function test_platform_admin_visualiza_estabelecimento(): void
    {
        $this->actingAs($this->platformAdmin)
            ->get(route('tenants.show', $this->tenantB))
            ->assertOk()
            ->assertSee('Loja B');
    }

    public function test_platform_admin_cria_estabelecimento(): void
    {
        $resposta = $this->actingAs($this->platformAdmin)
            ->post(route('tenants.store'), $this->dadosDeEstabelecimento('Loja Criada Pela Plataforma'));

        $criado = Tenant::where('slug', 'loja-criada-pela-plataforma')->firstOrFail();

        $resposta->assertRedirect(route('tenants.show', $criado));
        $this->assertDatabaseHas('tenants', [
            'id' => $criado->id,
            'name' => 'Loja Criada Pela Plataforma',
        ]);
    }

    public function test_platform_admin_altera_estabelecimento(): void
    {
        $resposta = $this->actingAs($this->platformAdmin)
            ->put(route('tenants.update', $this->tenantB), [
                ...$this->dadosDeEstabelecimento('Loja B Alterada Pela Plataforma', 'SUSPENDED'),
                'slug' => 'loja-b-alterada-pela-plataforma',
            ]);

        $resposta->assertRedirect(route('tenants.show', $this->tenantB));
        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenantB->id,
            'name' => 'Loja B Alterada Pela Plataforma',
            'status' => 'SUSPENDED',
            'active' => false,
        ]);
    }

    public function test_platform_admin_arquiva_estabelecimento(): void
    {
        $resposta = $this->actingAs($this->platformAdmin)
            ->delete(route('tenants.destroy', $this->tenantB));

        $resposta->assertRedirect(route('tenants.index', ['trashed' => 'with']));
        $this->assertSoftDeleted('tenants', ['id' => $this->tenantB->id]);
    }

    public function test_platform_admin_restaura_estabelecimento(): void
    {
        $this->tenantB->delete();

        $resposta = $this->actingAs($this->platformAdmin)
            ->post(route('tenants.restore', $this->tenantB->id));

        $resposta->assertRedirect(route('tenants.show', $this->tenantB));
        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenantB->id,
            'deleted_at' => null,
        ]);
    }

    private function dadosDeEstabelecimento(string $nome, string $status = 'ACTIVE'): array
    {
        return [
            'name' => $nome,
            'status' => $status,
            'plan' => 'standard',
            'timezone' => 'America/Sao_Paulo',
            'locale' => 'pt-BR',
            'currency' => 'BRL',
        ];
    }
}
