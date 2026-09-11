<?php

namespace Tests\Feature\Security;

use App\Modules\Authorization\Application\TenantAuthority;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Concerns\MontaCenariosDeAutorizacao;
use Tests\TestCase;

/**
 * SEC-04 · autoridade efetiva por estabelecimento explícito.
 *
 * Controles do primitive compartilhado pela contenção de delegação do E1 e
 * pela dominância de autoridade do E2. Os casos cobrem o que as rotas não
 * alcançam: vínculo não ativo, conta inativa, linhas gravadas com tenant
 * divergente e ids que não pertencem ao estabelecimento.
 *
 * O primitive filtra; recusar id inválido é responsabilidade de quem o usa.
 */
#[Group('sec-04')]
#[Group('sec-04-e2')]
class TenantAuthoritySecurityTest extends TestCase
{
    use MontaCenariosDeAutorizacao, RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private TenantAuthority $autoridade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->active()->create();
        $this->tenantB = Tenant::factory()->active()->create();
        $this->provisionarPapeisPadrao();

        $this->autoridade = app(TenantAuthority::class);
    }

    public function test_autoridade_do_ator_e_a_uniao_dos_seus_papeis_sem_duplicatas(): void
    {
        $catalogo = $this->papelPersonalizado($this->tenantA, 'catalogo', ['manage-products', 'view-products']);
        $estoque = $this->papelPersonalizado($this->tenantA, 'estoque', ['manage-inventory', 'view-products']);
        $pessoa = User::factory()->forTenant($this->tenantA)->create();
        $pessoa->assignRole($catalogo, $this->tenantA->id);
        $pessoa->assignRole($estoque, $this->tenantA->id);

        $this->assertSame(
            $this->idsDasPermissoes($this->tenantA, ['manage-products', 'view-products', 'manage-inventory']),
            $this->autoridade->autoridadeDoAtor($pessoa, $this->tenantA->id)
        );
    }

    /**
     * Vínculo suspenso: a pessoa não exerce autoridade, mas os papéis continuam
     * lá. É essa autoridade latente que medirá o alvo no E2.
     */
    public function test_vinculo_suspenso_zera_a_autoridade_do_ator_mas_preserva_a_latente(): void
    {
        $catalogo = $this->papelPersonalizado($this->tenantA, 'catalogo', ['manage-products']);
        $pessoa = User::factory()->forTenant($this->tenantA, TenantUser::STATUS_SUSPENDED)->create();
        $pessoa->assignRole($catalogo, $this->tenantA->id);

        $this->assertSame([], $this->autoridade->autoridadeDoAtor($pessoa, $this->tenantA->id));
        $this->assertSame([$catalogo->id], $this->autoridade->papeisDoUsuario($pessoa, $this->tenantA->id));
        $this->assertSame(
            $this->idsDasPermissoes($this->tenantA, ['manage-products']),
            $this->autoridade->autoridadeDosPapeis([$catalogo->id], $this->tenantA->id)
        );
    }

    public function test_conta_inativa_zera_a_autoridade_do_ator(): void
    {
        $catalogo = $this->papelPersonalizado($this->tenantA, 'catalogo', ['manage-products']);
        $pessoa = User::factory()->inactive()->forTenant($this->tenantA)->create();
        $pessoa->assignRole($catalogo, $this->tenantA->id);

        $this->assertSame(
            TenantUser::STATUS_ACTIVE,
            $pessoa->membershipFor($this->tenantA->id)->status,
            'pré-condição: o vínculo está ativo; só a conta está inativa'
        );
        $this->assertSame([$catalogo->id], $this->autoridade->papeisDoUsuario($pessoa, $this->tenantA->id));
        $this->assertSame([], $this->autoridade->autoridadeDoAtor($pessoa, $this->tenantA->id));
    }

    /**
     * Linhas gravadas com tenant divergente não contam, e o TenantContext ativo
     * não muda o estabelecimento consultado.
     */
    public function test_autoridade_fica_isolada_no_tenant_informado(): void
    {
        $catalogoA = $this->papelPersonalizado($this->tenantA, 'catalogo', ['manage-products']);
        $vendasB = $this->papelPersonalizado($this->tenantB, 'vendas', ['manage-sales']);
        $pessoa = User::factory()->forTenant($this->tenantA)->create();
        $pessoa->joinTenant($this->tenantB->id, TenantUser::STATUS_ACTIVE);
        $pessoa->assignRole($catalogoA, $this->tenantA->id);
        $pessoa->assignRole($vendasB, $this->tenantB->id);
        $agora = now();

        // papel de B vinculado à pessoa com a linha marcada como de A
        DB::table('user_role')->insert([
            'user_id' => $pessoa->id, 'role_id' => $vendasB->id, 'tenant_id' => $this->tenantA->id,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        // permissão de B gravada no papel de A com a linha de A
        DB::table('role_permission')->insert([
            'role_id' => $catalogoA->id, 'permission_id' => $this->permissao($this->tenantB, 'manage-sales')->id,
            'tenant_id' => $this->tenantA->id, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        // permissão de A gravada no papel de A com a linha marcada como de B
        DB::table('role_permission')->insert([
            'role_id' => $catalogoA->id, 'permission_id' => $this->permissao($this->tenantA, 'manage-customers')->id,
            'tenant_id' => $this->tenantB->id, 'created_at' => $agora, 'updated_at' => $agora,
        ]);

        app(TenantContext::class)->set($this->tenantB->id);

        $this->assertSame([$catalogoA->id], $this->autoridade->papeisDoUsuario($pessoa, $this->tenantA->id));
        $this->assertSame(
            $this->idsDasPermissoes($this->tenantA, ['manage-products']),
            $this->autoridade->autoridadeDoAtor($pessoa, $this->tenantA->id)
        );
        $this->assertSame(
            $this->idsDasPermissoes($this->tenantB, ['manage-sales']),
            $this->autoridade->autoridadeDoAtor($pessoa, $this->tenantB->id)
        );
    }

    public function test_papeis_desconhecidos_ou_de_outro_tenant_sao_filtrados(): void
    {
        $catalogoA = $this->papelPersonalizado($this->tenantA, 'catalogo', ['manage-products']);
        $vendasB = $this->papelPersonalizado($this->tenantB, 'vendas', ['manage-sales']);
        $entrada = [$catalogoA->id, $vendasB->id, '01ZZZZZZZZZZZZZZZZZZZZZZZZ', $catalogoA->id];

        $this->assertSame([$catalogoA->id], $this->autoridade->papeisDoTenant($entrada, $this->tenantA->id));
        $this->assertSame(
            $this->idsDasPermissoes($this->tenantA, ['manage-products']),
            $this->autoridade->autoridadeDosPapeis($entrada, $this->tenantA->id)
        );
    }

    /**
     * @return list<string>
     */
    private function idsDasPermissoes(Tenant $tenant, array $nomes): array
    {
        $ids = array_values(array_unique(array_map(fn (string $nome) => $this->permissao($tenant, $nome)->id, $nomes)));
        sort($ids, SORT_STRING);

        return $ids;
    }
}
