<?php

namespace Tests\Feature\Pdv;

use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Application\OperatorSessionAudit;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Pdv\Domain\Models\PdvOperatorSession;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;

/**
 * Fase 5 — administração humana das atribuições de operador.
 *
 * Caminho de PESSOA, não de máquina: PAT humano com `business:write` e
 * estabelecimento escolhido em `X-Tenant-ID`, validado contra o vínculo. As
 * duas autoridades exigidas — sobre a filial e sobre a pessoa — são verificadas
 * separadamente aqui, porque elas recusam cenários diferentes.
 */
#[Group('pdv-operator')]
class PdvOperatorAdministrationTest extends PdvOperatorTestCase
{
    private User $administrador;

    private string $tokenHumano;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrador = User::factory()->create();
        app(ProvisionarEstabelecimento::class)->atribuirAdministrador($this->tenant, $this->administrador);
        $this->administrador = $this->administrador->fresh();
        $this->tokenHumano = $this->administrador->createToken('auth_token', ['business:read', 'business:write'])->plainTextToken;
    }

    public function test_administrador_concede_acesso_e_o_operador_passa_a_entrar(): void
    {
        $novo = $this->pessoaDaLoja();

        $resposta = $this->admin('POST', "admin/branches/{$this->branch->id}/operators", ['user_id' => $novo->id]);

        $resposta->assertCreated()
            ->assertJsonPath('data.status', PdvOperatorBranchAssignment::STATUS_ACTIVE)
            ->assertJsonPath('data.operator_id', $novo->id)
            ->assertJsonPath('data.branch_id', $this->branch->id)
            ->assertJsonPath('data.granted_by', $this->administrador->id);

        $this->assertSame('no-store, private', $resposta->headers->get('Cache-Control'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => OperatorSessionAudit::BRANCH_ACCESS_GRANTED,
            'user_id' => $this->administrador->id,
        ]);

        // Com permissão e atribuição, o caixa aceita a pessoa.
        $this->concederPermissaoDePdv($novo);
        $this->login(payload: ['email' => $novo->email, 'password' => self::SENHA])->assertCreated();
    }

    public function test_conceder_duas_vezes_e_idempotente(): void
    {
        $novo = $this->pessoaDaLoja();
        $rota = "admin/branches/{$this->branch->id}/operators";

        $this->admin('POST', $rota, ['user_id' => $novo->id])->assertCreated();
        $this->admin('POST', $rota, ['user_id' => $novo->id])->assertOk();

        $this->assertSame(1, PdvOperatorBranchAssignment::withoutGlobalScopes()->where('user_id', $novo->id)->count());
        $this->assertSame(1, AuditLog::withoutGlobalScopes()->where('action', OperatorSessionAudit::BRANCH_ACCESS_GRANTED)->count());
    }

    public function test_revogacao_encerra_a_sessao_em_andamento(): void
    {
        $credencial = $this->abrirSessao();

        $this->admin('DELETE', "admin/branches/{$this->branch->id}/operators/{$this->operador->id}")
            ->assertNoContent();

        $atribuicao = PdvOperatorBranchAssignment::withoutGlobalScopes()->where('user_id', $this->operador->id)->sole();
        $this->assertSame(PdvOperatorBranchAssignment::STATUS_REVOKED, $atribuicao->status);
        $this->assertNotNull($atribuicao->revoked_at);

        // A sessão é encerrada na MESMA transação da revogação — não fica
        // ativa esperando a próxima requisição do caixa.
        $this->assertSame(PdvOperatorSession::STATUS_REVOKED, PdvOperatorSession::withoutGlobalScopes()->sole()->status);
        $this->consultarSessao($credencial)->assertStatus(401);

        $this->assertDatabaseHas('audit_logs', [
            'action' => OperatorSessionAudit::BRANCH_ACCESS_REVOKED,
            'user_id' => $this->administrador->id,
        ]);
    }

    public function test_revogacao_impede_novo_login(): void
    {
        $this->admin('DELETE', "admin/branches/{$this->branch->id}/operators/{$this->operador->id}")
            ->assertNoContent();

        $this->login()->assertStatus(401);
    }

    public function test_revogar_duas_vezes_e_idempotente(): void
    {
        $rota = "admin/branches/{$this->branch->id}/operators/{$this->operador->id}";

        $this->admin('DELETE', $rota)->assertNoContent();
        $this->admin('DELETE', $rota)->assertNoContent();

        $this->assertSame(1, AuditLog::withoutGlobalScopes()->where('action', OperatorSessionAudit::BRANCH_ACCESS_REVOKED)->count());
    }

    public function test_conceder_de_novo_depois_de_revogar_reativa_a_mesma_linha(): void
    {
        $rota = "admin/branches/{$this->branch->id}/operators";
        $antes = PdvOperatorBranchAssignment::withoutGlobalScopes()->where('user_id', $this->operador->id)->sole();

        $this->admin('DELETE', $rota.'/'.$this->operador->id)->assertNoContent();
        $this->admin('POST', $rota, ['user_id' => $this->operador->id])->assertOk();

        $depois = PdvOperatorBranchAssignment::withoutGlobalScopes()->where('user_id', $this->operador->id)->sole();

        $this->assertSame($antes->id, $depois->id);
        $this->assertSame(PdvOperatorBranchAssignment::STATUS_ACTIVE, $depois->status);
        $this->assertNull($depois->revoked_at);
        $this->login()->assertCreated();
    }

    public function test_listagem_da_filial_mostra_os_operadores(): void
    {
        $resposta = $this->admin('GET', "admin/branches/{$this->branch->id}/operators");

        $resposta->assertOk()
            ->assertJsonPath('data.branch_id', $this->branch->id)
            ->assertJsonPath('data.operators.0.operator_id', $this->operador->id)
            ->assertJsonPath('data.operators.0.operator.email', $this->operador->email);

        $this->assertStringNotContainsString('credential', $resposta->getContent());
        $this->assertStringNotContainsString('password', $resposta->getContent());
    }

    public function test_consulta_das_filiais_de_um_operador(): void
    {
        $resposta = $this->admin('GET', "admin/operators/{$this->operador->id}/branches");

        $resposta->assertOk()
            ->assertJsonPath('data.operator_id', $this->operador->id)
            ->assertJsonPath('data.branches.0.branch_id', $this->branch->id)
            ->assertJsonPath('data.branches.0.branch.code', $this->branch->code);
    }

    public function test_sem_manage_branches_nao_concede(): void
    {
        $gerenteFraco = $this->pessoaDaLoja();
        $token = $gerenteFraco->createToken('auth_token', ['business:read', 'business:write'])->plainTextToken;

        $resposta = $this->admin(
            'POST',
            "admin/branches/{$this->branch->id}/operators",
            ['user_id' => $this->operador->id],
            $token,
        );

        $this->assertErroPdv($resposta, 403, 'forbidden');
    }

    public function test_token_humano_sem_escrita_nao_administra(): void
    {
        $token = $this->administrador->createToken('auth_token', ['business:read'])->plainTextToken;

        $resposta = $this->admin('GET', "admin/branches/{$this->branch->id}/operators", token: $token);

        $this->assertErroPdv($resposta, 403, 'forbidden');
    }

    public function test_credencial_de_maquina_nao_administra_atribuicoes(): void
    {
        $this->novaRequisicao();

        $resposta = $this->withServerVariables(['REMOTE_ADDR' => self::IP_CLIENTE])
            ->withToken($this->maquina)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/v1/pdv/admin/branches/{$this->branch->id}/operators", ['user_id' => $this->operador->id]);

        $this->assertErroPdv($resposta, 403, 'forbidden');
    }

    public function test_sem_tenant_resolvido_nao_administra(): void
    {
        // Duas lojas, nenhuma escolhida: o resolver não tem como decidir.
        $outra = $this->outraLoja();
        $this->administrador->joinTenant($outra->id, TenantUser::STATUS_ACTIVE);

        $this->novaRequisicao();
        $resposta = $this->withToken($this->tokenHumano)
            ->getJson("/api/v1/pdv/admin/branches/{$this->branch->id}/operators");

        $this->assertErroPdv($resposta, 403, 'forbidden');
    }

    public function test_filial_de_outro_estabelecimento_e_recusada_sem_revelar_existencia(): void
    {
        $outra = $this->outraLoja();
        $filialAlheia = Branch::factory()->create(['tenant_id' => $outra->id, 'status' => 'ACTIVE']);

        $recusaDeFilialAlheia = $this->admin('GET', "admin/branches/{$filialAlheia->id}/operators");
        $recusaDeFilialInexistente = $this->admin('GET', 'admin/branches/'.Str::ulid().'/operators');

        foreach ([$recusaDeFilialAlheia, $recusaDeFilialInexistente] as $resposta) {
            $this->assertErroPdv($resposta, 403, 'forbidden');
        }

        $semId = fn (array $erro) => array_diff_key($erro, ['request_id' => null]);
        $this->assertSame(
            $semId($recusaDeFilialAlheia->json('error')),
            $semId($recusaDeFilialInexistente->json('error')),
        );
    }

    public function test_nao_concede_a_pessoa_de_outro_estabelecimento(): void
    {
        $outra = $this->outraLoja();
        $forasteiro = User::factory()->create();
        $forasteiro->joinTenant($outra->id, TenantUser::STATUS_ACTIVE);

        $resposta = $this->admin('POST', "admin/branches/{$this->branch->id}/operators", ['user_id' => $forasteiro->id]);

        $this->assertErroPdv($resposta, 403, 'forbidden');
        $this->assertSame(0, PdvOperatorBranchAssignment::withoutGlobalScopes()->where('user_id', $forasteiro->id)->count());
    }

    public function test_nao_concede_a_pessoa_com_vinculo_suspenso(): void
    {
        $suspenso = $this->pessoaDaLoja();
        $suspenso->joinTenant($this->tenant->id, TenantUser::STATUS_SUSPENDED);

        $resposta = $this->admin('POST', "admin/branches/{$this->branch->id}/operators", ['user_id' => $suspenso->id]);

        $this->assertErroPdv($resposta, 403, 'forbidden');
    }

    /**
     * Contenção de delegação (SEC-04 · E2): quem não domina a autoridade da
     * pessoa não administra o acesso dela. Um gerente com manage-branches não
     * concede acesso de caixa ao administrador da loja.
     */
    public function test_nao_concede_a_quem_tem_autoridade_maior(): void
    {
        $gerente = $this->pessoaDaLoja();
        $papel = $this->papelComPermissoes('gerente-de-filiais', ['manage-branches', 'view-branches']);
        $gerente->assignRole($papel, $this->tenant->id);
        $token = $gerente->createToken('auth_token', ['business:read', 'business:write'])->plainTextToken;

        $resposta = $this->admin(
            'POST',
            "admin/branches/{$this->branch->id}/operators",
            ['user_id' => $this->administrador->id],
            $token,
        );

        $this->assertErroPdv($resposta, 403, 'forbidden');
        $this->assertSame(0, PdvOperatorBranchAssignment::withoutGlobalScopes()->where('user_id', $this->administrador->id)->count());
    }

    public function test_gerente_concede_a_quem_esta_dentro_da_autoridade_dele(): void
    {
        $gerente = $this->pessoaDaLoja();
        $gerente->assignRole($this->papelComPermissoes('gerente-de-filiais', ['manage-branches', 'view-branches']), $this->tenant->id);
        $token = $gerente->createToken('auth_token', ['business:read', 'business:write'])->plainTextToken;

        $semPapel = $this->pessoaDaLoja();

        $this->admin('POST', "admin/branches/{$this->branch->id}/operators", ['user_id' => $semPapel->id], $token)
            ->assertCreated();
    }

    public function test_filial_inativa_nao_recebe_atribuicao(): void
    {
        $this->branch->update(['status' => 'INACTIVE']);

        $resposta = $this->admin('POST', "admin/branches/{$this->branch->id}/operators", ['user_id' => $this->pessoaDaLoja()->id]);

        $this->assertErroPdv($resposta, 403, 'forbidden');
    }

    public function test_campo_desconhecido_no_corpo_e_recusado(): void
    {
        $resposta = $this->admin('POST', "admin/branches/{$this->branch->id}/operators", [
            'user_id' => $this->operador->id,
            'status' => 'ACTIVE',
        ]);

        $this->assertErroPdv($resposta, 422, 'validation_error');
    }

    /**
     * Requisição administrativa humana.
     */
    private function admin(string $metodo, string $rota, array $payload = [], ?string $token = null): TestResponse
    {
        $this->novaRequisicao();

        return $this->withServerVariables(['REMOTE_ADDR' => self::IP_CLIENTE])
            ->withToken($token ?? $this->tokenHumano)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->json($metodo, '/api/v1/pdv/'.$rota, $payload);
    }

    /**
     * Pessoa com vínculo ativo na loja e nenhum papel.
     */
    private function pessoaDaLoja(): User
    {
        $pessoa = User::factory()->create(['password' => self::SENHA]);
        $pessoa->joinTenant($this->tenant->id, TenantUser::STATUS_ACTIVE);

        return $pessoa->fresh();
    }

    /**
     * @param  list<string>  $permissoes
     */
    private function papelComPermissoes(string $nome, array $permissoes): Role
    {
        $papel = Role::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => $nome],
            ['id' => (string) Str::ulid(), 'description' => $nome]
        );

        foreach ($permissoes as $permissao) {
            $papel->grantPermission(Permission::withoutGlobalScopes()
                ->where('tenant_id', $this->tenant->id)
                ->where('name', $permissao)
                ->firstOrFail());
        }

        return $papel;
    }

    private function outraLoja(): Tenant
    {
        $tenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($tenant);

        return $tenant->fresh();
    }
}
