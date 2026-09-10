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
}
