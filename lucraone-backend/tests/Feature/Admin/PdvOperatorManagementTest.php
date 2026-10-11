<?php

namespace Tests\Feature\Admin;

use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Application\OperatorRegistry;
use App\Modules\Pdv\Application\OperatorSessionAudit;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Pdv\Domain\Models\PdvOperatorProfile;
use App\Modules\Pdv\Domain\Models\PdvOperatorSession;
use App\Modules\Pdv\Domain\Models\PdvTenantSetting;
use App\Modules\Pdv\Domain\OperatorPermission;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Application\IssueTerminalMachineCredential;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Área administrativa de Operadores de PDV.
 *
 * A tela existe para um lojista pequeno: ele cadastra o funcionário, escolhe um
 * nome de usuário curto, define a senha e marca as filiais. Os testes seguem
 * essa ordem, e o que eles protegem com mais cuidado é a fronteira entre o que
 * pertence ao CAIXA (username, situação, filiais) e o que pertence à IDENTIDADE
 * global da pessoa (nome, e-mail, senha) — porque a segunda pode ser
 * compartilhada com outro estabelecimento.
 *
 * Toda recusa é verificada no SERVIDOR. Esconder o botão não é autorização.
 */
#[Group('pdv-operator')]
#[Group('pdv-admin')]
class PdvOperatorManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Branch $centro;

    private Branch $bairro;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create(['name' => 'Mercado Bom Preço']);
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($this->tenant);

        $empresa = Company::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'ACTIVE']);
        $this->centro = Branch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $empresa->id,
            'name' => 'Loja Centro',
            'status' => 'ACTIVE',
        ]);
        $this->bairro = Branch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $empresa->id,
            'name' => 'Loja Bairro',
            'status' => 'ACTIVE',
        ]);

        $this->admin = User::factory()->create(['name' => 'Ana Admin']);
        app(ProvisionarEstabelecimento::class)->atribuirAdministrador($this->tenant, $this->admin);
        $this->admin = $this->admin->fresh();
    }

    // ----------------------------------------------------------------
    // Cadastro
    // ----------------------------------------------------------------

    public function test_cadastra_operador_com_username_e_senha(): void
    {
        $resposta = $this->actingAs($this->admin)->post(route('pdv-operators.store'), [
            'modo' => 'novo',
            'name' => 'José Márcio Ferreira da Silva',
            'email' => 'marcio@mercado.test',
            'password' => 'senha-inicial-123',
            'password_confirmation' => 'senha-inicial-123',
            'username' => 'marcio',
            'branch_ids' => [$this->centro->id],
        ]);

        $resposta->assertSessionHasNoErrors()->assertRedirect();

        $perfil = PdvOperatorProfile::withoutGlobalScopes()->sole();

        $this->assertSame('marcio', $perfil->username);
        $this->assertSame('marcio', $perfil->username_canonical);
        $this->assertSame(PdvOperatorProfile::STATUS_ACTIVE, $perfil->status);
        $this->assertSame($this->admin->id, $perfil->created_by);
        $this->assertSame($this->tenant->id, $perfil->tenant_id);

        $pessoa = $perfil->user;
        $this->assertSame('José Márcio Ferreira da Silva', $pessoa->name);
        $this->assertTrue(Hash::check('senha-inicial-123', $pessoa->password));
        $this->assertTrue($pessoa->canAccessTenant($this->tenant->id));

        // Permissão por papel real, e o papel nasce aqui.
        $this->assertTrue($pessoa->hasPermission(OperatorPermission::ACESSO, $this->tenant->id));
        $this->assertDatabaseHas('roles', ['tenant_id' => $this->tenant->id, 'name' => OperatorRegistry::PAPEL]);

        // Filial liberada pelo mesmo caminho da API administrativa.
        $this->assertDatabaseHas('pdv_operator_branch_assignments', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $pessoa->id,
            'branch_id' => $this->centro->id,
            'status' => PdvOperatorBranchAssignment::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => OperatorSessionAudit::OPERATOR_CREATED,
            'user_id' => $this->admin->id,
        ]);
    }

    /**
     * Operador sem e-mail é caso NORMAL: caixa de lanchonete não tem endereço
     * corporativo, e fabricar um seria pior que não ter.
     */
    public function test_cadastra_operador_sem_email(): void
    {
        $this->cadastrar(['username' => 'joao', 'email' => null])->assertSessionHasNoErrors();

        $perfil = PdvOperatorProfile::withoutGlobalScopes()->sole();

        $this->assertNull($perfil->user->email);
        $this->assertNull($perfil->user->email_verified_at);
        $this->assertDatabaseHas('users', ['id' => $perfil->user_id, 'email' => null]);
    }

    /**
     * Dois operadores sem e-mail convivem: o índice único aceita vários NULL.
     */
    public function test_dois_operadores_sem_email_convivem(): void
    {
        $this->cadastrar(['username' => 'joao', 'email' => null])->assertSessionHasNoErrors();
        $this->cadastrar(['username' => 'maria', 'email' => null])->assertSessionHasNoErrors();

        $this->assertSame(2, PdvOperatorProfile::withoutGlobalScopes()->count());
        $this->assertSame(2, User::whereNull('email')->count());
    }

    /**
     * O cadastro de operador não enfraquece o login web, que continua exigindo
     * e-mail: quem não tem e-mail simplesmente não entra pelo painel.
     *
     * A verificação é no guard, e não pela tela, porque é ali que a garantia
     * mora: `Auth::attempt` consulta `where email = ?`, e nenhum valor casa com
     * NULL — nem string vazia, nem null.
     */
    public function test_operador_sem_email_nao_entra_pelo_login_web(): void
    {
        $this->cadastrar(['username' => 'joao', 'email' => null])->assertSessionHasNoErrors();

        // `email` nulo casaria com `email IS NULL` no provider padrão, ou seja,
        // com TODOS os operadores sem e-mail. O provider de identidade recusa.
        $this->assertFalse(Auth::guard('web')->attempt(['email' => null, 'password' => 'senha-inicial-123']));
        $this->assertFalse(Auth::guard('web')->attempt(['email' => '', 'password' => 'senha-inicial-123']));

        // E a senha certa com e-mail certo de OUTRA pessoa continua entrando,
        // provando que a recusa é do valor vazio e não do mecanismo.
        $this->assertTrue(Auth::guard('web')->attempt([
            'email' => $this->admin->email,
            'password' => 'password',
        ]));
    }

    public function test_username_duplicado_no_mesmo_estabelecimento_e_recusado(): void
    {
        $this->cadastrar(['username' => 'marcio'])->assertSessionHasNoErrors();

        $this->cadastrar(['username' => 'marcio', 'email' => 'outro@mercado.test'])
            ->assertSessionHasErrors('username');

        $this->assertSame(1, PdvOperatorProfile::withoutGlobalScopes()->count());
    }

    /**
     * Grafias equivalentes colidem: é o que impede dois `marcio` na mesma loja
     * por causa de um ponto.
     */
    public function test_grafia_equivalente_conta_como_duplicada(): void
    {
        $this->cadastrar(['username' => 'jose.silva'])->assertSessionHasNoErrors();

        foreach (['josesilva', 'JOSE_SILVA', 'José Silva'] as $grafia) {
            $this->cadastrar(['username' => $grafia, 'email' => Str::uuid().'@mercado.test'])
                ->assertSessionHasErrors('username');
        }

        $this->assertSame(1, PdvOperatorProfile::withoutGlobalScopes()->count());
    }

    public function test_username_e_normalizado_para_minusculas(): void
    {
        $this->cadastrar(['username' => '  MÁRCIO  '])->assertSessionHasNoErrors();

        $this->assertSame('marcio', PdvOperatorProfile::withoutGlobalScopes()->sole()->username);
    }

    /**
     * O mesmo username em OUTRO estabelecimento é legítimo, e os dois convivem.
     */
    public function test_mesmo_username_em_estabelecimentos_diferentes_convive(): void
    {
        $this->cadastrar(['username' => 'marcio'])->assertSessionHasNoErrors();

        $outra = $this->outraLoja();

        $this->actingAs($outra['admin'])->post(route('pdv-operators.store'), [
            'modo' => 'novo',
            'name' => 'Marcio da Outra Loja',
            'password' => 'senha-inicial-123',
            'password_confirmation' => 'senha-inicial-123',
            'username' => 'marcio',
            'branch_ids' => [$outra['branch']->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, PdvOperatorProfile::withoutGlobalScopes()->where('username', 'marcio')->count());
    }

    public function test_username_invalido_e_recusado(): void
    {
        foreach (['ab', 'admin', 'jose@silva', str_repeat('x', 40)] as $invalido) {
            $this->cadastrar(['username' => $invalido, 'email' => Str::uuid().'@mercado.test'])
                ->assertSessionHasErrors('username');
        }

        $this->assertSame(0, PdvOperatorProfile::withoutGlobalScopes()->count());
    }

    // ----------------------------------------------------------------
    // Pessoa já existente
    // ----------------------------------------------------------------

    public function test_habilita_pessoa_existente_sem_mexer_na_identidade(): void
    {
        $gerente = User::factory()->create(['name' => 'Rita Gerente', 'password' => 'senha-dela-123']);
        $gerente->joinTenant($this->tenant->id, TenantUser::STATUS_ACTIVE);
        $antes = $gerente->fresh()->only(['name', 'email', 'password', 'status']);

        $this->actingAs($this->admin)->post(route('pdv-operators.store'), [
            'modo' => 'existente',
            'user_id' => $gerente->id,
            'username' => 'rita',
            'branch_ids' => [$this->centro->id, $this->bairro->id],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $perfil = PdvOperatorProfile::withoutGlobalScopes()->sole();

        $this->assertSame($gerente->id, $perfil->user_id);
        $this->assertSame('rita', $perfil->username);
        // Nada da identidade global foi tocado — nem a senha.
        $this->assertSame($antes, $gerente->fresh()->only(['name', 'email', 'password', 'status']));

        $this->assertDatabaseHas('audit_logs', [
            'action' => OperatorSessionAudit::OPERATOR_LINKED,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_vincula_a_mais_de_uma_filial(): void
    {
        $this->cadastrar(['username' => 'marcio', 'branch_ids' => [$this->centro->id, $this->bairro->id]])
            ->assertSessionHasNoErrors();

        $perfil = PdvOperatorProfile::withoutGlobalScopes()->sole();

        $this->assertSame(2, PdvOperatorBranchAssignment::withoutGlobalScopes()
            ->where('user_id', $perfil->user_id)
            ->where('status', PdvOperatorBranchAssignment::STATUS_ACTIVE)
            ->count());
    }

    public function test_filial_de_outro_estabelecimento_e_recusada(): void
    {
        $outra = $this->outraLoja();

        $this->cadastrar(['username' => 'marcio', 'branch_ids' => [$outra['branch']->id]])
            ->assertSessionHasErrors('branch_ids.0');

        $this->assertSame(0, PdvOperatorProfile::withoutGlobalScopes()->count());
    }

    public function test_sem_filial_nenhuma_e_recusado(): void
    {
        $this->cadastrar(['username' => 'marcio', 'branch_ids' => []])
            ->assertSessionHasErrors('branch_ids');
    }

    public function test_pessoa_de_outro_estabelecimento_nao_e_habilitada(): void
    {
        $outra = $this->outraLoja();
        $forasteiro = User::factory()->create();
        $forasteiro->joinTenant($outra['tenant']->id, TenantUser::STATUS_ACTIVE);

        $this->actingAs($this->admin)->post(route('pdv-operators.store'), [
            'modo' => 'existente',
            'user_id' => $forasteiro->id,
            'username' => 'forasteiro',
            'branch_ids' => [$this->centro->id],
        ])->assertSessionHasErrors('user_id');

        $this->assertSame(0, PdvOperatorProfile::withoutGlobalScopes()->count());
        // E, principalmente: a tela de caixa não trouxe ninguém para dentro do
        // estabelecimento.
        $this->assertFalse($forasteiro->fresh()->canAccessTenant($this->tenant->id));
    }

    public function test_conta_inativa_nao_e_habilitada(): void
    {
        $inativo = User::factory()->create(['status' => User::STATUS_INACTIVE]);
        $inativo->joinTenant($this->tenant->id, TenantUser::STATUS_ACTIVE);

        $this->actingAs($this->admin)->post(route('pdv-operators.store'), [
            'modo' => 'existente',
            'user_id' => $inativo->id,
            'username' => 'inativo',
            'branch_ids' => [$this->centro->id],
        ])->assertSessionHasErrors('user_id');
    }

    public function test_pessoa_ja_operadora_nao_e_habilitada_duas_vezes(): void
    {
        $this->cadastrar(['username' => 'marcio'])->assertSessionHasNoErrors();
        $perfil = PdvOperatorProfile::withoutGlobalScopes()->sole();

        $this->actingAs($this->admin)->post(route('pdv-operators.store'), [
            'modo' => 'existente',
            'user_id' => $perfil->user_id,
            'username' => 'marcio2',
            'branch_ids' => [$this->centro->id],
        ])->assertSessionHasErrors('user_id');
    }

    // ----------------------------------------------------------------
    // Edição, desativação e revogação
    // ----------------------------------------------------------------

    public function test_altera_username_e_registra_o_anterior(): void
    {
        $perfil = $this->operadorCadastrado('marcio');

        $this->actingAs($this->admin)->put(route('pdv-operators.update', $perfil), [
            'name' => $perfil->user->name,
            'email' => $perfil->user->email,
            'username' => 'marcio.silva',
            'status' => PdvOperatorProfile::STATUS_ACTIVE,
            'branch_ids' => [$this->centro->id],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('marcio.silva', $perfil->fresh()->username);
        $this->assertSame('marciosilva', $perfil->fresh()->username_canonical);

        $registro = AuditLog::withoutGlobalScopes()
            ->where('action', OperatorSessionAudit::OPERATOR_USERNAME_CHANGED)
            ->sole();

        $this->assertSame('marcio', $registro->changes['previous_username']);
        $this->assertSame($this->admin->id, $registro->user_id);
    }

    public function test_desativacao_encerra_o_caixa_aberto(): void
    {
        $perfil = $this->operadorCadastrado('marcio');
        $sessao = $this->abrirCaixa($perfil);

        $this->actingAs($this->admin)
            ->post(route('pdv-operators.toggle', $perfil))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(PdvOperatorProfile::STATUS_INACTIVE, $perfil->fresh()->status);
        $this->assertSame(PdvOperatorSession::STATUS_REVOKED, $sessao->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => OperatorSessionAudit::OPERATOR_DEACTIVATED,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_reativacao_volta_o_acesso_sem_ressuscitar_a_sessao(): void
    {
        $perfil = $this->operadorCadastrado('marcio');
        $sessao = $this->abrirCaixa($perfil);

        $this->actingAs($this->admin)->post(route('pdv-operators.toggle', $perfil));
        $this->actingAs($this->admin)->post(route('pdv-operators.toggle', $perfil));

        $this->assertSame(PdvOperatorProfile::STATUS_ACTIVE, $perfil->fresh()->status);
        // De REVOKED não se volta.
        $this->assertSame(PdvOperatorSession::STATUS_REVOKED, $sessao->fresh()->status);
    }

    public function test_revogacao_de_filial_pela_edicao_encerra_a_sessao(): void
    {
        $perfil = $this->operadorCadastrado('marcio', [$this->centro->id, $this->bairro->id]);
        $sessao = $this->abrirCaixa($perfil);

        $this->actingAs($this->admin)->put(route('pdv-operators.update', $perfil), [
            'name' => $perfil->user->name,
            'email' => $perfil->user->email,
            'username' => $perfil->username,
            'status' => PdvOperatorProfile::STATUS_ACTIVE,
            'branch_ids' => [$this->bairro->id],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pdv_operator_branch_assignments', [
            'user_id' => $perfil->user_id,
            'branch_id' => $this->centro->id,
            'status' => PdvOperatorBranchAssignment::STATUS_REVOKED,
        ]);
        $this->assertSame(PdvOperatorSession::STATUS_REVOKED, $sessao->fresh()->status);
    }

    // ----------------------------------------------------------------
    // Senha
    // ----------------------------------------------------------------

    public function test_redefine_senha_de_quem_pertence_so_a_este_estabelecimento(): void
    {
        $perfil = $this->operadorCadastrado('marcio');
        $antes = $perfil->user->password;

        $resposta = $this->actingAs($this->admin)
            ->post(route('pdv-operators.reset-password', $perfil))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $temporaria = session('senha_temporaria');

        $this->assertNotEmpty($temporaria);
        $this->assertNotSame($antes, $perfil->fresh()->user->password);
        $this->assertTrue(Hash::check($temporaria, $perfil->fresh()->user->password));

        // A senha temporária nunca entra em auditoria.
        $registro = AuditLog::withoutGlobalScopes()
            ->where('action', OperatorSessionAudit::OPERATOR_PASSWORD_RESET)
            ->sole();
        $this->assertStringNotContainsString($temporaria, json_encode($registro->toArray()));
        $this->assertSame($this->admin->id, $registro->user_id);
    }

    /**
     * Pessoa compartilhada com outro estabelecimento NÃO tem a senha global
     * redefinida por um administrador local: ele veria a senha temporária e com
     * ela entraria na outra loja.
     */
    public function test_nao_redefine_senha_de_pessoa_compartilhada_com_outro_estabelecimento(): void
    {
        $outra = $this->outraLoja();

        $contador = User::factory()->create(['password' => 'senha-dele-123']);
        $contador->joinTenant($this->tenant->id, TenantUser::STATUS_ACTIVE);
        $contador->joinTenant($outra['tenant']->id, TenantUser::STATUS_ACTIVE);

        $this->actingAs($this->admin)->post(route('pdv-operators.store'), [
            'modo' => 'existente',
            'user_id' => $contador->id,
            'username' => 'contador',
            'branch_ids' => [$this->centro->id],
        ])->assertSessionHasNoErrors();

        $perfil = PdvOperatorProfile::withoutGlobalScopes()->sole();
        $antes = $contador->fresh()->password;

        $this->actingAs($this->admin)
            ->post(route('pdv-operators.reset-password', $perfil))
            ->assertSessionHas('erro');

        $this->assertSame($antes, $contador->fresh()->password);
        $this->assertNull(session('senha_temporaria'));
        $this->assertDatabaseMissing('audit_logs', ['action' => OperatorSessionAudit::OPERATOR_PASSWORD_RESET]);
    }

    public function test_nao_altera_nome_de_pessoa_compartilhada(): void
    {
        $outra = $this->outraLoja();

        $contador = User::factory()->create(['name' => 'Carlos Contador']);
        $contador->joinTenant($this->tenant->id, TenantUser::STATUS_ACTIVE);
        $contador->joinTenant($outra['tenant']->id, TenantUser::STATUS_ACTIVE);

        $this->actingAs($this->admin)->post(route('pdv-operators.store'), [
            'modo' => 'existente',
            'user_id' => $contador->id,
            'username' => 'contador',
            'branch_ids' => [$this->centro->id],
        ])->assertSessionHasNoErrors();

        $perfil = PdvOperatorProfile::withoutGlobalScopes()->sole();

        $this->actingAs($this->admin)->put(route('pdv-operators.update', $perfil), [
            'name' => 'Nome Trocado',
            'email' => null,
            'username' => 'contador',
            'status' => PdvOperatorProfile::STATUS_ACTIVE,
            'branch_ids' => [$this->centro->id],
        ])->assertSessionHasErrors('name');

        $this->assertSame('Carlos Contador', $contador->fresh()->name);
    }

    // ----------------------------------------------------------------
    // Autorização
    // ----------------------------------------------------------------

    public function test_sem_permissao_administrativa_nao_acessa(): void
    {
        $caixa = User::factory()->create();
        $caixa->joinTenant($this->tenant->id, TenantUser::STATUS_ACTIVE);

        foreach ([
            ['get', route('pdv-operators.index')],
            ['get', route('pdv-operators.create')],
        ] as [$metodo, $url]) {
            $this->actingAs($caixa)->{$metodo}($url)->assertForbidden();
        }
    }

    /**
     * Quem só consulta vê a lista e NÃO escreve. A recusa é no servidor, não no
     * botão escondido.
     */
    public function test_quem_so_consulta_ve_a_lista_mas_nao_escreve(): void
    {
        $perfil = $this->operadorCadastrado('marcio');

        $leitor = User::factory()->create();
        $leitor->joinTenant($this->tenant->id, TenantUser::STATUS_ACTIVE);
        $leitor->assignRole($this->papelCom('consulta-filiais', ['view-branches']), $this->tenant->id);

        $this->actingAs($leitor)->get(route('pdv-operators.index'))->assertOk()->assertSee('marcio');
        $this->actingAs($leitor)->get(route('pdv-operators.show', $perfil))->assertOk();

        $this->actingAs($leitor)->get(route('pdv-operators.create'))->assertForbidden();
        $this->actingAs($leitor)->post(route('pdv-operators.toggle', $perfil))->assertSessionHas('erro');
        $this->assertSame(PdvOperatorProfile::STATUS_ACTIVE, $perfil->fresh()->status);
    }

    /**
     * Contenção de delegação: um gerente com manage-branches não mexe no acesso
     * de um administrador.
     */
    public function test_gerente_nao_administra_operador_com_autoridade_maior(): void
    {
        $this->actingAs($this->admin)->post(route('pdv-operators.store'), [
            'modo' => 'existente',
            'user_id' => $this->admin->id,
            'username' => 'ana',
            'branch_ids' => [$this->centro->id],
        ])->assertSessionHasNoErrors();

        $perfilDoAdmin = PdvOperatorProfile::withoutGlobalScopes()->sole();

        $gerente = User::factory()->create();
        $gerente->joinTenant($this->tenant->id, TenantUser::STATUS_ACTIVE);
        $gerente->assignRole($this->papelCom('gerente-filiais', ['manage-branches', 'view-branches']), $this->tenant->id);

        $this->actingAs($gerente)
            ->post(route('pdv-operators.toggle', $perfilDoAdmin))
            ->assertSessionHas('erro');

        $this->assertSame(PdvOperatorProfile::STATUS_ACTIVE, $perfilDoAdmin->fresh()->status);
    }

    public function test_perfil_de_outro_estabelecimento_nao_e_encontrado(): void
    {
        $outra = $this->outraLoja();

        $this->actingAs($outra['admin'])->post(route('pdv-operators.store'), [
            'modo' => 'novo',
            'name' => 'Alheio',
            'password' => 'senha-inicial-123',
            'password_confirmation' => 'senha-inicial-123',
            'username' => 'alheio',
            'branch_ids' => [$outra['branch']->id],
        ])->assertSessionHasNoErrors();

        $alheio = PdvOperatorProfile::withoutGlobalScopes()->where('tenant_id', $outra['tenant']->id)->sole();

        $this->actingAs($this->admin)->get(route('pdv-operators.show', $alheio))->assertNotFound();
        $this->actingAs($this->admin)->post(route('pdv-operators.toggle', $alheio))->assertNotFound();
    }

    // ----------------------------------------------------------------
    // Telas e política
    // ----------------------------------------------------------------

    public function test_listagem_mostra_estado_vazio_e_depois_o_operador(): void
    {
        $this->actingAs($this->admin)->get(route('pdv-operators.index'))
            ->assertOk()
            ->assertSee('nenhum operador cadastrado')
            ->assertSee('cadastrar o primeiro operador');

        $this->operadorCadastrado('marcio');

        $this->actingAs($this->admin)->get(route('pdv-operators.index'))
            ->assertOk()
            ->assertSee('marcio')
            ->assertDontSee('nenhum operador cadastrado');
    }

    public function test_menu_aparece_para_quem_pode_e_some_para_quem_nao_pode(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee('operadores de pdv');

        $caixa = User::factory()->create();
        $caixa->joinTenant($this->tenant->id, TenantUser::STATUS_ACTIVE);
        $this->actingAs($caixa)->get(route('dashboard'))->assertOk()->assertDontSee('operadores de pdv');
    }

    public function test_detalhe_mostra_filiais_sessao_e_historico(): void
    {
        $perfil = $this->operadorCadastrado('marcio', [$this->centro->id]);
        $this->abrirCaixa($perfil);

        $this->actingAs($this->admin)->get(route('pdv-operators.show', $perfil))
            ->assertOk()
            ->assertSee('marcio')
            ->assertSee('Loja Centro')
            ->assertSee('caixa aberto')
            ->assertSee('histórico')
            ->assertSee('branch access granted');
    }

    public function test_formulario_de_edicao_renderiza(): void
    {
        $perfil = $this->operadorCadastrado('marcio');

        $this->actingAs($this->admin)->get(route('pdv-operators.edit', $perfil))
            ->assertOk()
            ->assertSee('usuário do pdv')
            ->assertSee('filiais liberadas')
            ->assertSee('Loja Bairro');
    }

    public function test_define_e_desliga_o_bloqueio_de_tela(): void
    {
        $this->actingAs($this->admin)
            ->post(route('pdv-operators.settings'), ['screen_lock_minutes' => 15])
            ->assertSessionHasNoErrors();

        $this->assertSame(15, PdvTenantSetting::para($this->tenant->id)->bloqueioDeTela());

        $this->actingAs($this->admin)
            ->post(route('pdv-operators.settings'), ['screen_lock_minutes' => null])
            ->assertSessionHasNoErrors();

        $this->assertNull(PdvTenantSetting::para($this->tenant->id)->bloqueioDeTela());

        $this->assertSame(2, AuditLog::withoutGlobalScopes()
            ->where('action', OperatorSessionAudit::SETTINGS_UPDATED)
            ->count());
    }

    public function test_bloqueio_de_tela_recusa_valor_invalido(): void
    {
        $this->actingAs($this->admin)
            ->post(route('pdv-operators.settings'), ['screen_lock_minutes' => 0])
            ->assertSessionHasErrors('screen_lock_minutes');

        $this->actingAs($this->admin)
            ->post(route('pdv-operators.settings'), ['screen_lock_minutes' => 5000])
            ->assertSessionHasErrors('screen_lock_minutes');
    }

    /**
     * O papel `pdv-operator` nasce com `access-pdv` e NADA mais, e só existe
     * depois do primeiro operador — não é provisionado em todo estabelecimento.
     */
    public function test_papel_de_operador_nasce_sob_demanda_e_com_uma_permissao(): void
    {
        $this->assertDatabaseMissing('roles', ['tenant_id' => $this->tenant->id, 'name' => OperatorRegistry::PAPEL]);

        $this->cadastrar(['username' => 'marcio'])->assertSessionHasNoErrors();

        $papel = Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('name', OperatorRegistry::PAPEL)
            ->sole();

        $this->assertSame(
            [OperatorPermission::ACESSO],
            $papel->permissionsForTenant()->pluck('permissions.name')->all(),
        );

        // Criar o papel não autorizou mais ninguém.
        $this->assertSame(1, $papel->usersForTenant()->count());
    }

    // ----------------------------------------------------------------
    // Apoio
    // ----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $dados
     */
    private function cadastrar(array $dados = [])
    {
        return $this->actingAs($this->admin)->post(route('pdv-operators.store'), array_merge([
            'modo' => 'novo',
            'name' => 'Operador de Teste',
            'email' => 'operador'.Str::random(6).'@mercado.test',
            'password' => 'senha-inicial-123',
            'password_confirmation' => 'senha-inicial-123',
            'username' => 'operador',
            'branch_ids' => [$this->centro->id],
        ], $dados));
    }

    /**
     * @param  array<int, string>|null  $filiais
     */
    private function operadorCadastrado(string $username, ?array $filiais = null): PdvOperatorProfile
    {
        $this->cadastrar([
            'username' => $username,
            'branch_ids' => $filiais ?? [$this->centro->id],
        ])->assertSessionHasNoErrors();

        return PdvOperatorProfile::withoutGlobalScopes()
            ->where('username_canonical', str_replace(['.', '_', '-'], '', Str::lower($username)))
            ->sole();
    }

    /**
     * Abre um caixa de verdade para o operador, pelo serviço real.
     */
    private function abrirCaixa(PdvOperatorProfile $perfil): PdvOperatorSession
    {
        $terminal = Terminal::factory()->forBranch($this->centro)->create();
        $terminal->update(['installation_id' => (string) Str::uuid(), 'status' => Terminal::STATUS_ACTIVE]);
        app(IssueTerminalMachineCredential::class)->issue($terminal->fresh());

        $sessao = PdvOperatorSession::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $terminal->company_id,
            'branch_id' => $terminal->branch_id,
            'terminal_id' => $terminal->id,
            'installation_id' => $terminal->fresh()->installation_id,
            'user_id' => $perfil->user_id,
            'machine_token_id' => $terminal->fresh()->tokens()->sole()->id,
            'credential_hash' => hash('sha256', Str::random(64)),
            'status' => PdvOperatorSession::STATUS_ACTIVE,
            'authenticated_at' => now(),
            'last_revalidated_at' => now(),
            'expires_at' => now()->addHours(4),
        ]);

        return $sessao->fresh();
    }

    /**
     * @param  list<string>  $permissoes
     */
    private function papelCom(string $nome, array $permissoes): Role
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

    /**
     * @return array{tenant: Tenant, branch: Branch, admin: User}
     */
    private function outraLoja(): array
    {
        $tenant = Tenant::factory()->active()->create(['name' => 'Mercado Norte']);
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($tenant);

        $empresa = Company::factory()->create(['tenant_id' => $tenant->id, 'status' => 'ACTIVE']);
        $branch = Branch::factory()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $empresa->id,
            'status' => 'ACTIVE',
        ]);

        $admin = User::factory()->create();
        app(ProvisionarEstabelecimento::class)->atribuirAdministrador($tenant, $admin);

        return ['tenant' => $tenant, 'branch' => $branch, 'admin' => $admin->fresh()];
    }
}
