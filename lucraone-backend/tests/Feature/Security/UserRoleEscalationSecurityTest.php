<?php

namespace Tests\Feature\Security;

use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Concerns\MontaCenariosDeAutorizacao;
use Tests\TestCase;

/**
 * SEC-04 · E2 — escalada por manage-users.
 *
 * Baseline de caracterização: os testes descrevem o comportamento SEGURO e
 * falham enquanto o vetor existir.
 *
 * Usa o papel manager real do seed, que recebe manage-users. A expectativa é
 * sobre o resultado — o papel admin não chega a ninguém —, e não sobre a forma
 * da recusa, que a correção ainda vai definir.
 *
 * A caracterização ampliada cobre a regra inteira: quem tem manage-users não
 * administra alguém cuja autoridade no estabelecimento está acima da sua — nem
 * concedendo, nem retirando, nem suspendendo, arquivando ou tomando a conta
 * pela senha ou pelo e-mail —, não altera o próprio conjunto de papéis e não
 * deixa estado parcial quando é recusado. Os controles positivos precisam
 * continuar verdes depois da correção.
 *
 * O admin-alvo dos cenários de conta pertence só a este estabelecimento de
 * propósito: o E3 permite administrar identidade exclusiva, então a recusa
 * esperada vem da autoridade sobre o alvo, e não da identidade compartilhada.
 */
#[Group('sec-04')]
#[Group('sec-04-e2')]
class UserRoleEscalationSecurityTest extends TestCase
{
    use MontaCenariosDeAutorizacao, RefreshDatabase;

    private Tenant $tenant;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create();
        $this->provisionarPapeisPadrao();

        $this->manager = $this->membroComPapel($this->tenant, 'manager');
    }

    public function test_manage_users_nao_permite_autoatribuir_papel_admin(): void
    {
        $papelAdmin = $this->papel($this->tenant, 'admin');

        $this->actingAs($this->manager)
            ->put(route('users.update', $this->manager), [
                'name' => $this->manager->name,
                'email' => $this->manager->email,
                'account_status' => User::STATUS_ACTIVE,
                'status' => TenantUser::STATUS_ACTIVE,
                'roles' => [
                    $this->papel($this->tenant, 'manager')->id,
                    $papelAdmin->id,
                ],
            ]);

        $this->assertDatabaseMissing('user_role', [
            'user_id' => $this->manager->id,
            'role_id' => $papelAdmin->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $manager = $this->manager->fresh();
        $this->assertFalse($manager->hasPermission('create-role', $this->tenant->id));
        $this->assertFalse($manager->hasPermission('update-role', $this->tenant->id));
    }

    /**
     * Uma segunda conta controlada pelo manager já com o papel admin é a mesma
     * escalada por outro caminho.
     */
    public function test_manage_users_nao_cria_conta_com_papel_admin(): void
    {
        $this->actingAs($this->manager)
            ->post(route('users.store'), [
                'name' => 'Conta Paralela',
                'email' => 'conta.paralela@example.test',
                'password' => 'senha-de-teste-123',
                'password_confirmation' => 'senha-de-teste-123',
                'status' => TenantUser::STATUS_ACTIVE,
                'roles' => [$this->papel($this->tenant, 'admin')->id],
            ]);

        $criada = User::where('email', 'conta.paralela@example.test')->first();

        $this->assertFalse(
            $criada !== null && $criada->hasRole('admin', $this->tenant->id),
            'a conta criada por quem só tem manage-users não pode nascer com o papel admin'
        );
    }

    /**
     * Bloquear só a autoelevação deixaria aberto elevar uma pessoa controlada
     * pelo atacante.
     */
    public function test_manage_users_nao_atribui_papel_admin_a_outra_pessoa(): void
    {
        $papelAdmin = $this->papel($this->tenant, 'admin');
        $comum = $this->membroComPapel($this->tenant, 'user');

        $this->actingAs($this->manager)
            ->put(route('users.update', $comum), [
                'name' => $comum->name,
                'email' => $comum->email,
                'account_status' => User::STATUS_ACTIVE,
                'status' => TenantUser::STATUS_ACTIVE,
                'roles' => [$papelAdmin->id],
            ]);

        $this->assertDatabaseMissing('user_role', [
            'user_id' => $comum->id,
            'role_id' => $papelAdmin->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertFalse($comum->fresh()->hasPermission('create-role', $this->tenant->id));
    }

    // ------------------------------------------------ Tomada de conta de admin

    /**
     * A senha temporária é exibida a quem redefiniu: redefinir a senha de quem
     * tem mais autoridade é assumir essa autoridade.
     */
    public function test_manage_users_nao_redefine_senha_de_admin_exclusivo(): void
    {
        $admin = $this->adminExclusivo('admin.reset@example.test');
        $this->assertAlvoAcimaDoManager($admin);
        $hashAntes = $this->hashDaSenha($admin);
        $vinculosAntes = $this->vinculosEPapeis($admin);
        $vinculosDoManagerAntes = $this->vinculosEPapeis($this->manager);

        $resposta = $this->actingAs($this->manager)
            ->post(route('users.reset-password', $admin));

        $this->assertTrue(
            $this->hashDaSenha($admin) === $hashAntes,
            'quem tem manage-users não pode redefinir a senha de quem tem autoridade acima da sua'
        );
        $resposta->assertSessionMissing('senha_temporaria');
        $this->assertSame($vinculosAntes, $this->vinculosEPapeis($admin));
        $this->assertSame($vinculosDoManagerAntes, $this->vinculosEPapeis($this->manager));
        $this->assertFalse($this->manager->fresh()->hasPermission('update-role', $this->tenant->id));
    }

    /**
     * A rota de reset não é o único caminho: o update comum aceita senha nova.
     */
    public function test_manage_users_nao_troca_senha_de_admin_exclusivo_pelo_update(): void
    {
        $admin = $this->adminExclusivo('admin.senha@example.test');
        $this->assertAlvoAcimaDoManager($admin);
        $hashAntes = $this->hashDaSenha($admin);
        $globalAntes = $this->retratoGlobal($admin);
        $vinculosAntes = $this->vinculosEPapeis($admin);

        $resposta = $this->actingAs($this->manager)
            ->put(route('users.update', $admin), $this->dadosDeAtualizacao($admin, [
                'password' => 'senha-imposta-ao-admin',
                'password_confirmation' => 'senha-imposta-ao-admin',
            ]));

        $this->assertTrue(
            $this->hashDaSenha($admin) === $hashAntes,
            'a senha do admin não pode ser trocada por quem não domina a autoridade dele'
        );
        $this->assertSame($globalAntes, $this->retratoGlobal($admin));
        $this->assertSame($vinculosAntes, $this->vinculosEPapeis($admin));
        $resposta->assertSessionMissing('sucesso');
    }

    /**
     * Com o e-mail trocado, a recuperação de acesso passa a chegar a quem trocou.
     */
    public function test_manage_users_nao_altera_email_de_admin_exclusivo(): void
    {
        $admin = $this->adminExclusivo('admin.email@example.test');
        $this->assertAlvoAcimaDoManager($admin);
        $hashAntes = $this->hashDaSenha($admin);
        $vinculosAntes = $this->vinculosEPapeis($admin);

        $resposta = $this->actingAs($this->manager)
            ->put(route('users.update', $admin), $this->dadosDeAtualizacao($admin, [
                'email' => 'email.imposto.ao.admin@example.test',
            ]));

        $this->assertSame(
            'admin.email@example.test',
            $admin->fresh()->email,
            'o e-mail do admin não pode ser trocado por quem não domina a autoridade dele'
        );
        $this->assertTrue($this->hashDaSenha($admin) === $hashAntes);
        $this->assertSame($vinculosAntes, $this->vinculosEPapeis($admin));
        $resposta->assertSessionMissing('sucesso');
    }

    // ------------------------------------------ Retirada de poder e sabotagem

    public function test_manage_users_nao_retira_papel_admin_de_outra_pessoa(): void
    {
        $admin = $this->adminExclusivo('admin.retirada@example.test');
        $this->assertAlvoAcimaDoManager($admin);
        $globalAntes = $this->retratoGlobal($admin);
        $vinculosAntes = $this->vinculosEPapeis($admin);

        $resposta = $this->actingAs($this->manager)
            ->put(route('users.update', $admin), $this->dadosDeAtualizacao($admin, [
                'roles' => [],
            ]));

        $this->assertTrue(
            $admin->fresh()->hasRole('admin', $this->tenant->id),
            'quem não domina a autoridade do admin não pode retirá-la'
        );
        $this->assertSame($vinculosAntes, $this->vinculosEPapeis($admin));
        $this->assertSame($globalAntes, $this->retratoGlobal($admin));
        $resposta->assertSessionMissing('sucesso');
    }

    /**
     * Arquivar retira todos os papéis e, para identidade exclusiva, apaga a conta.
     */
    public function test_manage_users_nao_arquiva_admin(): void
    {
        $admin = $this->adminExclusivo('admin.arquivo@example.test');
        $this->assertAlvoAcimaDoManager($admin);
        $vinculosAntes = $this->vinculosEPapeis($admin);

        $resposta = $this->actingAs($this->manager)
            ->delete(route('users.destroy', $admin));

        $this->assertNotSoftDeleted('users', ['id' => $admin->id]);
        $this->assertSame(
            $vinculosAntes,
            $this->vinculosEPapeis($admin),
            'o vínculo ativo e o papel admin permanecem'
        );
        $this->assertTrue($admin->fresh()->canAccessTenant($this->tenant->id));
        $resposta->assertSessionMissing('sucesso');
    }

    public static function statusQueTiramOAcesso(): array
    {
        return [
            'suspenso' => [TenantUser::STATUS_SUSPENDED],
            'inativo' => [TenantUser::STATUS_INACTIVE],
            'convidado' => [TenantUser::STATUS_INVITED],
        ];
    }

    /**
     * Sem mexer nos papéis, o status do vínculo tira o admin do estabelecimento.
     */
    #[DataProvider('statusQueTiramOAcesso')]
    public function test_manage_users_nao_tira_o_acesso_de_admin_pelo_status_do_vinculo(string $status): void
    {
        $admin = $this->adminExclusivo('admin.vinculo.'.strtolower($status).'@example.test');
        $this->assertAlvoAcimaDoManager($admin);
        $vinculosAntes = $this->vinculosEPapeis($admin);

        $resposta = $this->actingAs($this->manager)
            ->put(route('users.update', $admin), $this->dadosDeAtualizacao($admin, [
                'status' => $status,
            ]));

        $this->assertSame(
            TenantUser::STATUS_ACTIVE,
            $admin->fresh()->membershipFor($this->tenant->id)->status,
            "quem não domina a autoridade do admin não pode deixar o vínculo dele {$status}"
        );
        $this->assertSame($vinculosAntes, $this->vinculosEPapeis($admin));
        $this->assertTrue($admin->fresh()->canAccessTenant($this->tenant->id));
        $resposta->assertSessionMissing('sucesso');
    }

    // ------------------------------------------------------ Papel personalizado

    /**
     * A regra vem da autoridade, e não do nome admin.
     */
    public function test_manage_users_nao_atribui_papel_personalizado_privilegiado_a_outra_pessoa(): void
    {
        $privilegiado = $this->papelPrivilegiado();
        $pessoa = $this->membroComPapel($this->tenant, 'user');
        $vinculosAntes = $this->vinculosEPapeis($pessoa);

        $resposta = $this->actingAs($this->manager)
            ->put(route('users.update', $pessoa), $this->dadosDeAtualizacao($pessoa, [
                'roles' => [$this->papel($this->tenant, 'user')->id, $privilegiado->id],
            ]));

        $this->assertSame(
            $vinculosAntes,
            $this->vinculosEPapeis($pessoa),
            'o papel com autoridade acima da do manager não pode ser atribuído'
        );
        $this->assertFalse($pessoa->fresh()->hasPermission('manage-automations', $this->tenant->id));
        $resposta->assertSessionMissing('sucesso');
    }

    public function test_manage_users_nao_autoatribui_papel_personalizado_privilegiado(): void
    {
        $privilegiado = $this->papelPrivilegiado();
        $vinculosAntes = $this->vinculosEPapeis($this->manager);

        $resposta = $this->actingAs($this->manager)
            ->put(route('users.update', $this->manager), $this->dadosDeAtualizacao($this->manager, [
                'roles' => [$this->papel($this->tenant, 'manager')->id, $privilegiado->id],
            ]));

        $this->assertSame($vinculosAntes, $this->vinculosEPapeis($this->manager));
        $this->assertFalse($this->manager->fresh()->hasPermission('manage-automations', $this->tenant->id));
        $resposta->assertSessionMissing('sucesso');
    }

    // ------------------------------------------- Próprio conjunto de papéis

    /**
     * O próprio conjunto de papéis não muda nem para menos: seria lockout de
     * quem executa.
     */
    public function test_manage_users_nao_retira_o_proprio_papel(): void
    {
        $vinculosAntes = $this->vinculosEPapeis($this->manager);

        $resposta = $this->actingAs($this->manager)
            ->put(route('users.update', $this->manager), $this->dadosDeAtualizacao($this->manager, [
                'roles' => [],
            ]));

        $this->assertSame(
            $vinculosAntes,
            $this->vinculosEPapeis($this->manager),
            'o manager não pode alterar o próprio conjunto de papéis'
        );
        $this->assertTrue($this->manager->fresh()->hasPermission('manage-users', $this->tenant->id));
        $resposta->assertSessionMissing('sucesso');
    }

    /**
     * O viewer está dentro da autoridade do manager: a recusa aqui vem da
     * autoedição, e não da dominância.
     */
    public function test_manage_users_nao_acrescenta_a_si_mesmo_papel_que_domina(): void
    {
        $viewer = $this->papel($this->tenant, 'viewer');
        $this->assertDomina($this->manager, $viewer);
        $vinculosAntes = $this->vinculosEPapeis($this->manager);

        $resposta = $this->actingAs($this->manager)
            ->put(route('users.update', $this->manager), $this->dadosDeAtualizacao($this->manager, [
                'roles' => [$this->papel($this->tenant, 'manager')->id, $viewer->id],
            ]));

        $this->assertSame(
            $vinculosAntes,
            $this->vinculosEPapeis($this->manager),
            'o manager não pode alterar o próprio conjunto de papéis, nem com papel que domina'
        );
        $resposta->assertSessionMissing('sucesso');
    }

    // -------------------------------------------------------------- Atomicidade

    public function test_atribuicao_com_papel_permitido_e_papel_proibido_nao_grava_nenhum(): void
    {
        $viewer = $this->papel($this->tenant, 'viewer');
        $this->assertDomina($this->manager, $viewer);
        $pessoa = User::factory()->forTenant($this->tenant)->create();

        $resposta = $this->actingAs($this->manager)
            ->put(route('users.update', $pessoa), $this->dadosDeAtualizacao($pessoa, [
                'roles' => [$viewer->id, $this->papel($this->tenant, 'admin')->id],
            ]));

        $this->assertSame(
            [],
            $this->idsDosPapeis($pessoa),
            'nem o viewer, permitido sozinho, pode ser gravado junto com o admin recusado'
        );
        $resposta->assertSessionMissing('sucesso');
    }

    public function test_cadastro_recusado_nao_deixa_identidade_vinculo_nem_papel(): void
    {
        $contagensAntes = $this->contagens();

        $resposta = $this->actingAs($this->manager)
            ->post(route('users.store'), $this->dadosDeCadastro('cadastro.parcial@example.test', 'Cadastro Parcial', [
                $this->papel($this->tenant, 'viewer')->id,
                $this->papel($this->tenant, 'admin')->id,
            ]));

        $this->assertFalse(
            User::withTrashed()->where('email', 'cadastro.parcial@example.test')->exists(),
            'a recusa não pode deixar a identidade criada'
        );
        $this->assertSame($contagensAntes, $this->contagens(), 'nem vínculo nem papel parcial');
        $resposta->assertSessionMissing('sucesso');
    }

    /**
     * Identidade ativa de outro estabelecimento: a recusa não pode deixar vínculo
     * ou papel aqui, nem mexer na identidade global.
     */
    public function test_vinculo_recusado_de_identidade_existente_nao_deixa_estado_parcial(): void
    {
        $outroTenant = Tenant::factory()->active()->create();
        $existente = User::factory()->forTenant($outroTenant)->create([
            'name' => 'Pessoa De Outro Estabelecimento',
            'email' => 'pessoa.outro.tenant@example.test',
        ]);
        $globalAntes = $this->retratoGlobal($existente);
        $hashAntes = $this->hashDaSenha($existente);
        $vinculosAntes = $this->vinculosEPapeis($existente);

        $resposta = $this->actingAs($this->manager)
            ->post(route('users.store'), $this->dadosDeCadastro('pessoa.outro.tenant@example.test', 'Nome Imposto', [
                $this->papel($this->tenant, 'admin')->id,
            ]));

        $this->assertSame(
            $vinculosAntes,
            $this->vinculosEPapeis($existente),
            'a recusa não pode deixar vínculo ou papel neste estabelecimento'
        );
        $this->assertSame($globalAntes, $this->retratoGlobal($existente));
        $this->assertTrue($this->hashDaSenha($existente) === $hashAntes);
        $resposta->assertSessionMissing('sucesso');
    }

    public function test_edicao_recusada_por_autoridade_nao_persiste_nenhum_campo(): void
    {
        $admin = $this->adminExclusivo('admin.atomico@example.test');
        $this->assertAlvoAcimaDoManager($admin);
        $globalAntes = $this->retratoGlobal($admin);
        $hashAntes = $this->hashDaSenha($admin);
        $vinculosAntes = $this->vinculosEPapeis($admin);

        $resposta = $this->actingAs($this->manager)
            ->put(route('users.update', $admin), $this->dadosDeAtualizacao($admin, [
                'name' => 'Nome Imposto Ao Admin',
                'email' => 'admin.atomico.trocado@example.test',
                'status' => TenantUser::STATUS_SUSPENDED,
                'password' => 'senha-imposta-ao-admin',
                'password_confirmation' => 'senha-imposta-ao-admin',
                'roles' => [],
            ]));

        $this->assertSame($globalAntes, $this->retratoGlobal($admin), 'nome e e-mail não podem ser gravados');
        $this->assertTrue($this->hashDaSenha($admin) === $hashAntes, 'a senha não pode ser gravada');
        $this->assertSame($vinculosAntes, $this->vinculosEPapeis($admin), 'vínculo e papéis não podem ser gravados');
        $resposta->assertSessionMissing('sucesso');
    }

    // ------------------------------------------------------ Controles positivos

    public static function papeisPadraoDominadosPeloAdmin(): array
    {
        return [
            'manager' => ['manager'],
            'user' => ['user'],
        ];
    }

    #[DataProvider('papeisPadraoDominadosPeloAdmin')]
    public function test_admin_cria_usuario_com_papel_padrao_que_domina(string $nomeDoPapel): void
    {
        $admin = $this->membroComPapel($this->tenant, 'admin');
        $papel = $this->papel($this->tenant, $nomeDoPapel);
        $this->assertDomina($admin, $papel);
        $email = "admin.cria.{$nomeDoPapel}@example.test";

        $this->actingAs($admin)
            ->post(route('users.store'), $this->dadosDeCadastro($email, "Pessoa {$nomeDoPapel}", [$papel->id]))
            ->assertSessionHas('sucesso');

        $criada = User::where('email', $email)->firstOrFail();

        $this->assertTrue($criada->canAccessTenant($this->tenant->id));
        $this->assertSame([$papel->id], $this->idsDosPapeis($criada));
    }

    public function test_admin_troca_papel_user_por_manager(): void
    {
        $admin = $this->membroComPapel($this->tenant, 'admin');
        $pessoa = $this->membroComPapel($this->tenant, 'user');
        $papelManager = $this->papel($this->tenant, 'manager');
        $this->assertDomina($admin, $papelManager);
        $this->assertDomina($admin, $this->papel($this->tenant, 'user'));

        $this->actingAs($admin)
            ->put(route('users.update', $pessoa), $this->dadosDeAtualizacao($pessoa, [
                'roles' => [$papelManager->id],
            ]))
            ->assertSessionHas('sucesso');

        $this->assertSame([$papelManager->id], $this->idsDosPapeis($pessoa));
    }

    public static function papeisPadraoDominadosPeloManager(): array
    {
        return [
            'user' => ['user'],
            'viewer' => ['viewer'],
        ];
    }

    #[DataProvider('papeisPadraoDominadosPeloManager')]
    public function test_manager_atribui_a_outra_pessoa_papel_padrao_que_domina(string $nomeDoPapel): void
    {
        $papel = $this->papel($this->tenant, $nomeDoPapel);
        $this->assertDomina($this->manager, $papel);
        $pessoa = User::factory()->forTenant($this->tenant)->create();

        $this->actingAs($this->manager)
            ->put(route('users.update', $pessoa), $this->dadosDeAtualizacao($pessoa, [
                'roles' => [$papel->id],
            ]))
            ->assertSessionHas('sucesso');

        $this->assertSame([$papel->id], $this->idsDosPapeis($pessoa));
    }

    public function test_manager_retira_papel_viewer_de_outra_pessoa(): void
    {
        $pessoa = $this->membroComPapel($this->tenant, 'viewer');
        $this->assertDomina($this->manager, $this->papel($this->tenant, 'viewer'));

        $this->actingAs($this->manager)
            ->put(route('users.update', $pessoa), $this->dadosDeAtualizacao($pessoa, [
                'roles' => [],
            ]))
            ->assertSessionHas('sucesso');

        $this->assertSame([], $this->idsDosPapeis($pessoa));
    }

    public function test_manager_edita_vinculo_de_viewer_sem_mudar_papeis(): void
    {
        $pessoa = $this->membroComPapel($this->tenant, 'viewer');
        $papeisAntes = $this->idsDosPapeis($pessoa);

        $this->actingAs($this->manager)
            ->put(route('users.update', $pessoa), $this->dadosDeAtualizacao($pessoa, [
                'status' => TenantUser::STATUS_SUSPENDED,
            ]))
            ->assertSessionHas('sucesso');

        $this->assertSame(TenantUser::STATUS_SUSPENDED, $pessoa->fresh()->membershipFor($this->tenant->id)->status);
        $this->assertSame($papeisAntes, $this->idsDosPapeis($pessoa));
    }

    /**
     * O formulário de edição sempre reenvia os papéis: reenviar o mesmo
     * conjunto não é alteração dos próprios papéis.
     */
    public function test_manager_edita_a_si_mesmo_reenviando_os_mesmos_papeis(): void
    {
        $papeisAntes = $this->idsDosPapeis($this->manager);

        $this->actingAs($this->manager)
            ->put(route('users.update', $this->manager), $this->dadosDeAtualizacao($this->manager, [
                'name' => 'Manager Com Nome Atualizado',
            ]))
            ->assertSessionHas('sucesso');

        $this->assertSame('Manager Com Nome Atualizado', $this->manager->fresh()->name);
        $this->assertSame($papeisAntes, $this->idsDosPapeis($this->manager));
    }

    /**
     * A autoridade do ator é a união das permissões que ele tem no
     * estabelecimento, e não a de um papel principal.
     */
    public function test_autoridade_do_ator_e_a_uniao_dos_seus_papeis(): void
    {
        $automacao = $this->papelPersonalizado($this->tenant, 'automacao', ['manage-automations']);
        $ator = $this->membroComPapel($this->tenant, 'manager');
        $ator->assignRole($automacao, $this->tenant->id);
        $desejado = $this->papelPersonalizado($this->tenant, 'operador-de-automacao', ['manage-automations', 'manage-products']);

        // Pré-condições: nenhum papel do ator domina o desejado sozinho.
        $this->assertNotContains('manage-automations', $this->nomesDasPermissoes($this->papel($this->tenant, 'manager')));
        $this->assertNotContains('manage-products', $this->nomesDasPermissoes($automacao));
        $this->assertDomina($ator, $desejado);
        $pessoa = User::factory()->forTenant($this->tenant)->create();

        $this->actingAs($ator)
            ->put(route('users.update', $pessoa), $this->dadosDeAtualizacao($pessoa, [
                'roles' => [$desejado->id],
            ]))
            ->assertSessionHas('sucesso');

        $this->assertSame([$desejado->id], $this->idsDosPapeis($pessoa));
    }

    public function test_manager_edita_alvo_com_varios_papeis_dominados(): void
    {
        $papelUser = $this->papel($this->tenant, 'user');
        $viewer = $this->papel($this->tenant, 'viewer');
        $pessoa = $this->membroComPapel($this->tenant, 'user');
        $pessoa->assignRole($viewer, $this->tenant->id);
        $this->assertDomina($this->manager, $papelUser);
        $this->assertDomina($this->manager, $viewer);

        $this->actingAs($this->manager)
            ->put(route('users.update', $pessoa), $this->dadosDeAtualizacao($pessoa, [
                'roles' => [$papelUser->id],
            ]))
            ->assertSessionHas('sucesso');

        $this->assertSame([$papelUser->id], $this->idsDosPapeis($pessoa));
    }

    // ------------------------------------------------------------------ Apoio

    private function adminExclusivo(string $email): User
    {
        return $this->membroComPapel($this->tenant, 'admin', ['email' => $email]);
    }

    /**
     * Pré-condições dos cenários com admin-alvo: sem elas, a recusa futura
     * poderia vir de outro motivo.
     */
    private function assertAlvoAcimaDoManager(User $alvo): void
    {
        $tenantId = $this->tenant->id;

        $this->assertTrue($this->manager->hasPermission('manage-users', $tenantId), 'pré-condição: o manager tem manage-users');
        $this->assertFalse($this->manager->hasPermission('create-role', $tenantId), 'pré-condição: o manager não tem create-role');
        $this->assertFalse($this->manager->hasPermission('update-role', $tenantId), 'pré-condição: o manager não tem update-role');
        $this->assertTrue($alvo->hasRole('admin', $tenantId), 'pré-condição: o alvo é admin');
        $this->assertTrue($alvo->hasPermission('update-role', $tenantId), 'pré-condição: o alvo tem autoridade que o manager não tem');
        $this->assertTrue($alvo->isActive(), 'pré-condição: a conta do alvo está ativa');
        $this->assertTrue(
            $alvo->pertenceExclusivamenteAo($tenantId),
            'pré-condição: o alvo só existe neste estabelecimento, para a recusa não vir do E3'
        );
    }

    private function assertDomina(User $ator, Role $papel): void
    {
        $permissoes = $this->nomesDasPermissoes($papel);
        $foraDaAutoridade = array_values(array_filter(
            $permissoes,
            fn (string $nome) => ! $ator->hasPermission($nome, $this->tenant->id)
        ));

        $this->assertNotEmpty($permissoes, "pré-condição: o papel {$papel->name} tem permissões");
        $this->assertSame([], $foraDaAutoridade, "pré-condição: o ator domina o papel {$papel->name}");
    }

    /**
     * Papel com uma permissão que o manager não tem.
     */
    private function papelPrivilegiado(): Role
    {
        $papel = $this->papelPersonalizado($this->tenant, 'coordenador-privilegiado', ['view-automations', 'manage-automations']);

        $this->assertContains('manage-automations', $this->nomesDasPermissoes($papel));
        $this->assertFalse(
            $this->manager->hasPermission('manage-automations', $this->tenant->id),
            'pré-condição: o manager não tem manage-automations'
        );

        return $papel;
    }

    private function nomesDasPermissoes(Role $papel): array
    {
        return $papel->permissionsForTenant()->pluck('permissions.name')->all();
    }

    private function idsDosPapeis(User $pessoa): array
    {
        return DB::table('user_role')
            ->where('user_id', $pessoa->id)
            ->where('tenant_id', $this->tenant->id)
            ->orderBy('role_id')
            ->pluck('role_id')
            ->all();
    }

    /**
     * Payload de edição que repete o estado atual da pessoa neste estabelecimento.
     */
    private function dadosDeAtualizacao(User $pessoa, array $sobrescrever = []): array
    {
        return [
            'name' => $pessoa->name,
            'email' => $pessoa->email,
            'account_status' => $pessoa->status,
            'status' => $pessoa->membershipFor($this->tenant->id)->status,
            'roles' => $this->idsDosPapeis($pessoa),
            ...$sobrescrever,
        ];
    }

    private function dadosDeCadastro(string $email, string $nome, array $papeis): array
    {
        return [
            'name' => $nome,
            'email' => $email,
            'password' => 'senha-de-teste-123',
            'password_confirmation' => 'senha-de-teste-123',
            'status' => TenantUser::STATUS_ACTIVE,
            'roles' => $papeis,
        ];
    }

    /**
     * Atributos globais lidos direto do banco, inclusive de identidade arquivada.
     * A senha fica de fora para não expor hash na saída do teste.
     */
    private function retratoGlobal(User $pessoa): array
    {
        $linha = DB::table('users')->where('id', $pessoa->id)->first();

        return [
            'name' => $linha->name,
            'email' => $linha->email,
            'status' => $linha->status,
            'is_platform_admin' => (bool) $linha->is_platform_admin,
            'deleted_at' => $linha->deleted_at,
        ];
    }

    private function hashDaSenha(User $pessoa): string
    {
        return DB::table('users')->where('id', $pessoa->id)->value('password');
    }

    /**
     * Vínculos e papéis da pessoa em todos os estabelecimentos.
     */
    private function vinculosEPapeis(User $pessoa): array
    {
        return [
            'vinculos' => DB::table('tenant_user')
                ->where('user_id', $pessoa->id)
                ->orderBy('tenant_id')
                ->get(['tenant_id', 'status'])
                ->map(fn ($linha) => "{$linha->tenant_id}:{$linha->status}")
                ->all(),
            'papeis' => DB::table('user_role')
                ->where('user_id', $pessoa->id)
                ->orderBy('tenant_id')
                ->orderBy('role_id')
                ->get(['role_id', 'tenant_id'])
                ->map(fn ($linha) => "{$linha->role_id}@{$linha->tenant_id}")
                ->all(),
        ];
    }

    private function contagens(): array
    {
        return [
            'users' => DB::table('users')->count(),
            'tenant_user' => DB::table('tenant_user')->count(),
            'user_role' => DB::table('user_role')->count(),
        ];
    }
}
