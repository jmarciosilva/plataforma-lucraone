<?php

namespace Tests\Feature\Security;

use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Concerns\MontaCenariosDeAutorizacao;
use Tests\TestCase;

/**
 * SEC-04 · E1 — escalada por update-role.
 *
 * Baseline de caracterização: os testes descrevem o comportamento SEGURO e
 * falham enquanto o vetor existir.
 *
 * A regra protegida não é "não conceder create-role": é "não delegar poder que
 * o ator não está autorizado a delegar". Por isso há um caso com permissão de
 * negócio, para que a correção não se limite ao nome de uma permissão.
 *
 * A caracterização ampliada cobre também a retirada de poder, o lockout do
 * próprio papel e um papel privilegiado com nome qualquer, para que a correção
 * não dependa do nome admin. Os dois controles positivos — delegação legítima e
 * edição do manager pelo admin — precisam continuar verdes depois dela.
 */
#[Group('sec-04')]
#[Group('sec-04-e1')]
class RoleDelegationSecurityTest extends TestCase
{
    use MontaCenariosDeAutorizacao, RefreshDatabase;

    private Tenant $tenant;

    private User $gestor;

    private Role $papelDoGestor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create();
        $this->provisionarPapeisPadrao();

        // No seed só o admin tem update-role. Um papel personalizado com essa
        // permissão, e nada mais, é o cenário em que o vetor se abre.
        $this->papelDoGestor = $this->papelPersonalizado($this->tenant, 'gestor-de-acesso', ['update-role']);
        $this->gestor = User::factory()->forTenant($this->tenant)->create();
        $this->gestor->assignRole($this->papelDoGestor, $this->tenant->id);
    }

    public function test_update_role_nao_concede_create_role_ao_proprio_papel(): void
    {
        $antes = $this->permissoesDoPapel($this->papelDoGestor);

        $resposta = $this->actingAs($this->gestor)
            ->post(route('roles.permissions.sync', $this->papelDoGestor), [
                'permissions' => [
                    $this->permissao($this->tenant, 'update-role')->id,
                    $this->permissao($this->tenant, 'create-role')->id,
                ],
            ]);

        $this->assertSame(
            $antes,
            $this->permissoesDoPapel($this->papelDoGestor),
            'o papel do gestor não pode ganhar create-role por sincronização'
        );
        $this->assertFalse($this->gestor->fresh()->hasPermission('create-role', $this->tenant->id));
        $resposta->assertSessionMissing('sucesso');
    }

    public function test_update_role_nao_concede_permissao_de_negocio_que_o_ator_nao_possui(): void
    {
        $manageProducts = $this->permissao($this->tenant, 'manage-products');

        $resposta = $this->actingAs($this->gestor)
            ->post(route('roles.permissions.sync', $this->papelDoGestor), [
                'permissions' => [
                    $this->permissao($this->tenant, 'update-role')->id,
                    $manageProducts->id,
                ],
            ]);

        $this->assertDatabaseMissing('role_permission', [
            'role_id' => $this->papelDoGestor->id,
            'permission_id' => $manageProducts->id,
        ]);
        $this->assertFalse($this->gestor->fresh()->hasPermission('manage-products', $this->tenant->id));
        $resposta->assertSessionMissing('sucesso');
    }

    /**
     * O ROADMAP pede proteção de papéis administrativos relevantes. O caso
     * cobre só o essencial: quem não detém o poder do admin não redefine o
     * papel admin. A regra final de edição entre administradores fica para a
     * implementação.
     */
    public function test_update_role_nao_altera_permissoes_do_papel_admin(): void
    {
        $papelAdmin = $this->papel($this->tenant, 'admin');
        $antes = $this->permissoesDoPapel($papelAdmin);

        $resposta = $this->actingAs($this->gestor)
            ->post(route('roles.permissions.sync', $papelAdmin), [
                'permissions' => [$this->permissao($this->tenant, 'view-roles')->id],
            ]);

        $this->assertSame(
            $antes,
            $this->permissoesDoPapel($papelAdmin),
            'as permissões do papel admin não podem ser substituídas por quem não detém esse poder'
        );
        $resposta->assertSessionMissing('sucesso');
    }

    /**
     * A contenção vale nos dois sentidos: retirar de um papel uma permissão que
     * o ator não possui também é exercer autoridade que ele não tem, e serve
     * para sabotar quem depende do papel. O payload mantém update-role, então a
     * única mudança pedida é a remoção.
     */
    public function test_update_role_nao_remove_de_outro_papel_permissao_que_o_ator_nao_possui(): void
    {
        $papelAlvo = $this->papelPersonalizado($this->tenant, 'gestor-de-catalogo', ['update-role', 'manage-products']);
        $membroDoAlvo = $this->membroComPapel($this->tenant, 'gestor-de-catalogo');
        $antesDoAlvo = $this->permissoesDoPapel($papelAlvo);
        $antesDoGestor = $this->permissoesDoPapel($this->papelDoGestor);

        $resposta = $this->actingAs($this->gestor)
            ->post(route('roles.permissions.sync', $papelAlvo), [
                'permissions' => [$this->permissao($this->tenant, 'update-role')->id],
            ]);

        $this->assertSame(
            $antesDoAlvo,
            $this->permissoesDoPapel($papelAlvo),
            'o gestor não pode retirar de outro papel manage-products, que não possui'
        );
        $this->assertTrue($membroDoAlvo->fresh()->hasPermission('manage-products', $this->tenant->id));
        $this->assertSame($antesDoGestor, $this->permissoesDoPapel($this->papelDoGestor));
        $resposta->assertSessionMissing('sucesso');
    }

    /**
     * Direção oposta à autoelevação: a sincronização não pode destruir a
     * autoridade que a executa. O ator possui tudo o que retira, então comparar
     * a diferença com as permissões dele não basta — o caso exige proteção do
     * próprio papel. Não é a regra do último administrador.
     */
    public function test_update_role_nao_remove_update_role_do_proprio_papel(): void
    {
        $papelDoCoordenador = $this->papelPersonalizado($this->tenant, 'coordenador-de-acesso', ['update-role', 'view-roles']);
        $coordenador = $this->membroComPapel($this->tenant, 'coordenador-de-acesso');
        $antes = $this->permissoesDoPapel($papelDoCoordenador);

        $resposta = $this->actingAs($coordenador)
            ->post(route('roles.permissions.sync', $papelDoCoordenador), [
                'permissions' => [$this->permissao($this->tenant, 'view-roles')->id],
            ]);

        $this->assertSame(
            $antes,
            $this->permissoesDoPapel($papelDoCoordenador),
            'o ator não pode retirar update-role do próprio papel'
        );
        $this->assertTrue($coordenador->fresh()->hasPermission('update-role', $this->tenant->id));
        $resposta->assertSessionMissing('sucesso');
    }

    /**
     * Contraparte do caso do admin sem depender do nome: um papel personalizado
     * com autoridade que o ator não possui também não pode ser redefinido. A
     * redefinição retira manage-products e manage-inventory e acrescenta
     * manage-sales — nenhuma delas pertence ao gestor.
     */
    public function test_update_role_nao_redefine_papel_privilegiado_personalizado(): void
    {
        $papelSupervisor = $this->papelPersonalizado(
            $this->tenant,
            'supervisor-operacional',
            ['update-role', 'manage-products', 'manage-inventory']
        );
        $antesDoSupervisor = $this->permissoesDoPapel($papelSupervisor);
        $antesDoGestor = $this->permissoesDoPapel($this->papelDoGestor);

        $resposta = $this->actingAs($this->gestor)
            ->post(route('roles.permissions.sync', $papelSupervisor), [
                'permissions' => [
                    $this->permissao($this->tenant, 'update-role')->id,
                    $this->permissao($this->tenant, 'manage-sales')->id,
                ],
            ]);

        $this->assertSame(
            $antesDoSupervisor,
            $this->permissoesDoPapel($papelSupervisor),
            'o papel supervisor-operacional não pode ser redefinido por quem não detém a autoridade dele'
        );
        $this->assertSame($antesDoGestor, $this->permissoesDoPapel($this->papelDoGestor));
        $resposta->assertSessionMissing('sucesso');
    }

    /**
     * Controle positivo: a contenção não pode bloquear toda gestão de papéis.
     * Quem possui manage-products a delega a outro papel.
     */
    public function test_update_role_delega_a_outro_papel_permissao_que_o_ator_possui(): void
    {
        $papelDoResponsavel = $this->papelPersonalizado($this->tenant, 'responsavel-pelo-catalogo', ['update-role', 'manage-products']);
        $responsavel = $this->membroComPapel($this->tenant, 'responsavel-pelo-catalogo');
        $papelAlvo = $this->papelPersonalizado($this->tenant, 'operador-de-catalogo', []);
        $manageProducts = $this->permissao($this->tenant, 'manage-products');
        $antesDoResponsavel = $this->permissoesDoPapel($papelDoResponsavel);

        $resposta = $this->actingAs($responsavel)
            ->post(route('roles.permissions.sync', $papelAlvo), [
                'permissions' => [$manageProducts->id],
            ]);

        $resposta->assertRedirect(route('roles.show', $papelAlvo))->assertSessionHas('sucesso');
        $this->assertSame(
            ["{$manageProducts->id}@{$this->tenant->id}"],
            $this->permissoesDoPapel($papelAlvo)
        );
        $this->assertSame($antesDoResponsavel, $this->permissoesDoPapel($papelDoResponsavel));
    }

    /**
     * Controle positivo sobre a matriz atual, que não é hierárquica: o admin não
     * possui manage-assigned-branches, presente no manager. Essa matriz é
     * decisão do E2 e não é resolvida aqui.
     *
     * O admin retira manage-companies do manager — alteração dentro da própria
     * autoridade. Como a sincronização substitui o conjunto inteiro, o payload
     * reenvia manage-assigned-branches sem alterá-la, e ela precisa continuar
     * no papel.
     */
    public function test_admin_edita_papel_manager_preservando_permissao_fora_da_sua_autoridade(): void
    {
        $admin = $this->membroComPapel($this->tenant, 'admin');
        $papelAdmin = $this->papel($this->tenant, 'admin');
        $papelManager = $this->papel($this->tenant, 'manager');
        $manageCompanies = $this->permissao($this->tenant, 'manage-companies');
        $manageAssignedBranches = $this->permissao($this->tenant, 'manage-assigned-branches');
        $antesDoManager = $this->permissoesDoPapel($papelManager);
        $antesDoAdmin = $this->permissoesDoPapel($papelAdmin);

        // Pré-condições: sem elas o cenário não testa o que promete.
        $this->assertFalse($admin->hasPermission('manage-assigned-branches', $this->tenant->id));
        $this->assertContains("{$manageAssignedBranches->id}@{$this->tenant->id}", $antesDoManager);

        $resposta = $this->actingAs($admin)
            ->post(route('roles.permissions.sync', $papelManager), [
                'permissions' => $papelManager->permissionsForTenant()
                    ->pluck('permissions.id')
                    ->reject(fn (string $id) => $id === $manageCompanies->id)
                    ->values()
                    ->all(),
            ]);

        $resposta->assertRedirect(route('roles.show', $papelManager))->assertSessionHas('sucesso');
        $this->assertSame(
            array_values(array_diff($antesDoManager, ["{$manageCompanies->id}@{$this->tenant->id}"])),
            $this->permissoesDoPapel($papelManager),
            'só manage-companies sai; manage-assigned-branches, fora da autoridade do admin, permanece'
        );
        $this->assertSame($antesDoAdmin, $this->permissoesDoPapel($papelAdmin));
    }
}
