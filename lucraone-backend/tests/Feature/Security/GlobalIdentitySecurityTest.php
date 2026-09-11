<?php

namespace Tests\Feature\Security;

use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Concerns\MontaCenariosDeAutorizacao;
use Tests\TestCase;

/**
 * SEC-04 · E3 — administração local de identidade global.
 *
 * Baseline de caracterização: os testes descrevem o comportamento SEGURO e
 * falham enquanto o vetor existir.
 *
 * Desde a F1.8 a pessoa é uma identidade global com vínculos em vários
 * estabelecimentos. O requisito do ROADMAP: autoridade local de um tenant não
 * pode, por consequência indireta, conceder acesso ou comprometer os vínculos
 * de uma identidade em outros tenants. Os testes cobrem só o que é
 * inequivocamente perigoso; a UX de administração de identidade ainda não foi
 * decidida.
 *
 * Também ficam aqui, e devem passar desde já, a guarda contra promoção a
 * Platform Admin por payload e os controles positivos: a correção não pode
 * bloquear a gestão de vínculo e papéis, nem os dados globais de quem só
 * existe neste estabelecimento.
 *
 * Nos cenários novos a pessoa-alvo tem o papel viewer em A, que o manager
 * domina. Assim o resultado não depende da futura contenção de papéis do E2.
 */
#[Group('sec-04')]
#[Group('sec-04-e3')]
class GlobalIdentitySecurityTest extends TestCase
{
    use MontaCenariosDeAutorizacao, RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $atacante;

    private User $vitima;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->active()->create();
        $this->tenantB = Tenant::factory()->active()->create();
        $this->provisionarPapeisPadrao();

        // manage-users só no Tenant A, sem vínculo com B
        $this->atacante = $this->membroComPapel($this->tenantA, 'manager');

        // A vítima opera em A e administra B
        $this->vitima = $this->membroComPapel($this->tenantA, 'user', [
            'name' => 'Pessoa Com Dois Vinculos',
            'email' => 'pessoa.dois.vinculos@example.test',
        ]);
        $this->vitima->joinTenant($this->tenantB->id, TenantUser::STATUS_ACTIVE);
        $this->vitima->assignRole($this->papel($this->tenantB, 'admin'), $this->tenantB->id);
    }

    public function test_permissao_local_nao_redefine_senha_de_identidade_com_outros_vinculos(): void
    {
        $hashAntes = $this->vitima->fresh()->password;

        $resposta = $this->actingAs($this->atacante)
            ->post(route('users.reset-password', $this->vitima));

        // Comparação sem expor valores na saída do teste
        $this->assertTrue(
            $this->vitima->fresh()->password === $hashAntes,
            'a senha global não pode ser redefinida por autoridade local de outro estabelecimento'
        );
        $resposta->assertSessionMissing('senha_temporaria');
    }

    public function test_permissao_local_nao_altera_email_de_identidade_com_outros_vinculos(): void
    {
        $this->actingAs($this->atacante)
            ->put(route('users.update', $this->vitima), $this->dadosDaVitima([
                'email' => 'email.trocado@example.test',
            ]));

        $this->assertSame('pessoa.dois.vinculos@example.test', $this->vitima->fresh()->email);
    }

    public function test_permissao_local_nao_desativa_identidade_com_outros_vinculos(): void
    {
        $this->actingAs($this->atacante)
            ->put(route('users.update', $this->vitima), $this->dadosDaVitima([
                'account_status' => User::STATUS_INACTIVE,
            ]));

        $vitima = $this->vitima->fresh();
        $this->assertSame(User::STATUS_ACTIVE, $vitima->status);
        $this->assertTrue(
            $vitima->canAccessTenant($this->tenantB->id),
            'a autoridade local de A não pode derrubar o acesso da pessoa em B'
        );
    }

    public function test_cadastro_com_email_existente_nao_renomeia_identidade_global(): void
    {
        $existente = User::factory()->forTenant($this->tenantB)->create([
            'name' => 'Nome Original Da Pessoa',
            'email' => 'pessoa.existente@example.test',
        ]);

        $this->actingAs($this->atacante)
            ->post(route('users.store'), $this->dadosDeCadastro('pessoa.existente@example.test', 'Nome Imposto Por Outro Estabelecimento'));

        $this->assertSame('Nome Original Da Pessoa', $existente->fresh()->name);
    }

    public function test_cadastro_com_email_existente_nao_reativa_identidade_desativada(): void
    {
        $existente = User::factory()->inactive()->forTenant($this->tenantB)->create([
            'email' => 'pessoa.desativada@example.test',
        ]);

        $this->actingAs($this->atacante)
            ->post(route('users.store'), $this->dadosDeCadastro('pessoa.desativada@example.test', 'Pessoa Desativada'));

        $this->assertSame(User::STATUS_INACTIVE, $existente->fresh()->status);
    }

    public function test_cadastro_com_email_existente_nao_troca_credencial_da_identidade(): void
    {
        $existente = User::factory()->forTenant($this->tenantB)->create([
            'email' => 'pessoa.credencial@example.test',
        ]);
        $hashAntes = $existente->fresh()->password;

        $this->actingAs($this->atacante)
            ->post(route('users.store'), $this->dadosDeCadastro('pessoa.credencial@example.test', 'Pessoa Credencial'));

        $this->assertTrue(
            $existente->fresh()->password === $hashAntes,
            'cadastrar um e-mail existente não pode trocar a senha da identidade'
        );
    }

    // ------------------------------------------------ Platform Admin como alvo

    /**
     * O Platform Admin precisa de vínculo ativo para entrar no painel. Quem tem
     * manage-users nesse estabelecimento não pode, por isso, tomar a conta da
     * plataforma — mesmo sendo o único vínculo dela.
     */
    public function test_permissao_local_nao_redefine_senha_de_platform_admin(): void
    {
        $platformAdmin = $this->platformAdminComVinculoUnicoEmA();
        $globalAntes = $this->retratoGlobal($platformAdmin);
        $hashAntes = $this->hashDaSenha($platformAdmin);

        $resposta = $this->actingAs($this->atacante)
            ->post(route('users.reset-password', $platformAdmin));

        $this->assertTrue(
            $this->hashDaSenha($platformAdmin) === $hashAntes,
            'a senha do Platform Admin não pode ser redefinida por autoridade local'
        );
        $this->assertSame($globalAntes, $this->retratoGlobal($platformAdmin));
        $resposta->assertSessionMissing('senha_temporaria');
    }

    public function test_permissao_local_nao_altera_email_de_platform_admin(): void
    {
        $platformAdmin = $this->platformAdminComVinculoUnicoEmA();
        $globalAntes = $this->retratoGlobal($platformAdmin);

        $resposta = $this->actingAs($this->atacante)
            ->put(route('users.update', $platformAdmin), $this->dadosDeAtualizacao($platformAdmin, [
                'email' => 'email.da.plataforma.trocado@example.test',
            ]));

        $this->assertSame($globalAntes, $this->retratoGlobal($platformAdmin));
        $resposta->assertSessionMissing('sucesso');
    }

    public function test_permissao_local_nao_desativa_conta_de_platform_admin(): void
    {
        $platformAdmin = $this->platformAdminComVinculoUnicoEmA();
        $globalAntes = $this->retratoGlobal($platformAdmin);

        $resposta = $this->actingAs($this->atacante)
            ->put(route('users.update', $platformAdmin), $this->dadosDeAtualizacao($platformAdmin, [
                'account_status' => User::STATUS_INACTIVE,
            ]));

        $this->assertSame($globalAntes, $this->retratoGlobal($platformAdmin));
        $this->assertTrue($platformAdmin->fresh()->canAccessTenant($this->tenantA->id));
        $resposta->assertSessionMissing('sucesso');
    }

    /**
     * Com um único vínculo, arquivar hoje apaga (soft delete) a identidade
     * global. O que se protege é a identidade: o destino do vínculo local fica
     * para a correção decidir.
     */
    public function test_permissao_local_nao_arquiva_platform_admin(): void
    {
        $platformAdmin = $this->platformAdminComVinculoUnicoEmA();
        $globalAntes = $this->retratoGlobal($platformAdmin);

        $this->actingAs($this->atacante)
            ->delete(route('users.destroy', $platformAdmin));

        $this->assertNotSoftDeleted('users', ['id' => $platformAdmin->id]);
        $this->assertSame($globalAntes, $this->retratoGlobal($platformAdmin));
    }

    // ------------------------------------- Identidade com vínculo em outro tenant

    /**
     * A rota de reset não é o único caminho: o update comum aceita senha nova.
     */
    public function test_permissao_local_nao_troca_senha_pelo_update_de_identidade_com_outros_vinculos(): void
    {
        $pessoa = $this->identidadeComVinculoEmB('pessoa.senha.update@example.test');
        $globalAntes = $this->retratoGlobal($pessoa);
        $hashAntes = $this->hashDaSenha($pessoa);
        $vinculosAntes = $this->vinculosEPapeis($pessoa);

        $resposta = $this->actingAs($this->atacante)
            ->put(route('users.update', $pessoa), $this->dadosDeAtualizacao($pessoa, [
                'password' => 'senha-imposta-pelo-update',
                'password_confirmation' => 'senha-imposta-pelo-update',
            ]));

        $this->assertTrue(
            $this->hashDaSenha($pessoa) === $hashAntes,
            'a senha global não pode ser trocada pelo update de outro estabelecimento'
        );
        $this->assertSame($globalAntes, $this->retratoGlobal($pessoa));
        $this->assertSame($vinculosAntes, $this->vinculosEPapeis($pessoa));
        $resposta->assertSessionMissing('sucesso');
    }

    /**
     * users.name é global: renomear em A renomeia a pessoa em B.
     */
    public function test_permissao_local_nao_renomeia_pelo_update_identidade_com_outros_vinculos(): void
    {
        $pessoa = $this->identidadeComVinculoEmB('pessoa.nome.update@example.test', [
            'name' => 'Nome Original Global',
        ]);
        $hashAntes = $this->hashDaSenha($pessoa);
        $vinculosAntes = $this->vinculosEPapeis($pessoa);

        $resposta = $this->actingAs($this->atacante)
            ->put(route('users.update', $pessoa), $this->dadosDeAtualizacao($pessoa, [
                'name' => 'Nome Imposto Pelo Update De A',
            ]));

        $this->assertSame('Nome Original Global', $pessoa->fresh()->name);
        $this->assertTrue($this->hashDaSenha($pessoa) === $hashAntes);
        $this->assertSame($vinculosAntes, $this->vinculosEPapeis($pessoa));
        $resposta->assertSessionMissing('sucesso');
    }

    public static function statusDeVinculoNaoAtivo(): array
    {
        return [
            'convidado' => [TenantUser::STATUS_INVITED],
            'inativo' => [TenantUser::STATUS_INACTIVE],
            'suspenso' => [TenantUser::STATUS_SUSPENDED],
        ];
    }

    /**
     * A proteção não pode depender de o outro vínculo estar ativo: um vínculo
     * convidado, inativo ou suspenso pode voltar a valer com a senha já trocada.
     */
    #[DataProvider('statusDeVinculoNaoAtivo')]
    public function test_vinculo_nao_ativo_em_outro_tenant_tambem_protege_a_senha(string $statusEmB): void
    {
        $pessoa = $this->identidadeComVinculoEmB('pessoa.vinculo.'.strtolower($statusEmB).'@example.test', [], $statusEmB);
        $globalAntes = $this->retratoGlobal($pessoa);
        $hashAntes = $this->hashDaSenha($pessoa);
        $vinculosAntes = $this->vinculosEPapeis($pessoa);

        $resposta = $this->actingAs($this->atacante)
            ->post(route('users.reset-password', $pessoa));

        $this->assertTrue(
            $this->hashDaSenha($pessoa) === $hashAntes,
            "o vínculo {$statusEmB} em outro estabelecimento também protege a senha global"
        );
        $this->assertSame($globalAntes, $this->retratoGlobal($pessoa));
        $this->assertSame($vinculosAntes, $this->vinculosEPapeis($pessoa));
        $resposta->assertSessionMissing('senha_temporaria');
    }

    // ----------------------------------------- Cadastro com identidade existente

    /**
     * Conta desativada globalmente não é reativada nem vinculada por autoridade
     * local. A operação inteira é recusada: sem vínculo ou papel parcial em A.
     */
    public function test_cadastro_com_email_de_identidade_inativa_nao_vincula_nem_altera_a_identidade(): void
    {
        $existente = User::factory()->inactive()->forTenant($this->tenantB)->create([
            'name' => 'Pessoa Inativa Original',
            'email' => 'pessoa.inativa.sem.vinculo.em.a@example.test',
        ]);
        $globalAntes = $this->retratoGlobal($existente);
        $hashAntes = $this->hashDaSenha($existente);
        $vinculosAntes = $this->vinculosEPapeis($existente);

        $resposta = $this->actingAs($this->atacante)
            ->post(route('users.store'), [
                ...$this->dadosDeCadastro('pessoa.inativa.sem.vinculo.em.a@example.test', 'Nome Imposto Na Reativacao'),
                'roles' => [$this->papel($this->tenantA, 'viewer')->id],
            ]);

        $this->assertSame($globalAntes, $this->retratoGlobal($existente));
        $this->assertTrue($this->hashDaSenha($existente) === $hashAntes);
        $this->assertSame(
            $vinculosAntes,
            $this->vinculosEPapeis($existente),
            'a recusa não pode deixar vínculo ou papel parcial no estabelecimento atual'
        );
        $resposta->assertSessionMissing('sucesso');
    }

    /**
     * Identidade arquivada pelo único estabelecimento em que existia — o
     * arquivamento real do painel é o soft delete do model.
     */
    public function test_cadastro_com_email_de_identidade_arquivada_nao_restaura_nem_vincula(): void
    {
        $existente = User::factory()->forTenant($this->tenantB)->create([
            'name' => 'Pessoa Arquivada Original',
            'email' => 'pessoa.arquivada@example.test',
        ]);
        $existente->delete();

        $globalAntes = $this->retratoGlobal($existente);
        $hashAntes = $this->hashDaSenha($existente);
        $vinculosAntes = $this->vinculosEPapeis($existente);

        $resposta = $this->actingAs($this->atacante)
            ->post(route('users.store'), [
                ...$this->dadosDeCadastro('pessoa.arquivada@example.test', 'Nome Imposto Na Restauracao'),
                'roles' => [$this->papel($this->tenantA, 'viewer')->id],
            ]);

        $this->assertSoftDeleted('users', ['id' => $existente->id]);
        $this->assertSame($globalAntes, $this->retratoGlobal($existente));
        $this->assertTrue($this->hashDaSenha($existente) === $hashAntes);
        $this->assertSame(
            $vinculosAntes,
            $this->vinculosEPapeis($existente),
            'a recusa não pode deixar vínculo ou papel parcial no estabelecimento atual'
        );
        $resposta->assertSessionMissing('sucesso');
    }

    // ------------------------------------------ Regressão: is_platform_admin

    /**
     * Proteção já existente, congelada: o cadastro acontece, mas o marcador de
     * plataforma enviado no payload é ignorado.
     */
    public function test_payload_nao_promove_platform_admin_no_cadastro(): void
    {
        $this->actingAs($this->atacante)
            ->post(route('users.store'), [
                ...$this->dadosDeCadastro('tentativa.plataforma.cadastro@example.test', 'Tentativa Plataforma'),
                'roles' => [$this->papel($this->tenantA, 'viewer')->id],
                'is_platform_admin' => true,
            ])
            ->assertSessionHas('sucesso');

        $criada = User::where('email', 'tentativa.plataforma.cadastro@example.test')->firstOrFail();

        $this->assertTrue($criada->canAccessTenant($this->tenantA->id), 'controle: o cadastro precisa ter acontecido');
        $this->assertFalse($criada->isPlatformAdmin());
        $this->assertDatabaseHas('users', ['id' => $criada->id, 'is_platform_admin' => false]);
    }

    public function test_payload_nao_promove_platform_admin_na_edicao(): void
    {
        $pessoa = $this->identidadeExclusivaDeA('pessoa.exclusiva.payload@example.test');

        $this->actingAs($this->atacante)
            ->put(route('users.update', $pessoa), $this->dadosDeAtualizacao($pessoa, [
                'name' => 'Nome Alterado Com Payload De Plataforma',
                'is_platform_admin' => true,
            ]))
            ->assertSessionHas('sucesso');

        $pessoa = $pessoa->fresh();

        $this->assertSame('Nome Alterado Com Payload De Plataforma', $pessoa->name, 'controle: a edição precisa ter acontecido');
        $this->assertFalse($pessoa->isPlatformAdmin());
        $this->assertDatabaseHas('users', ['id' => $pessoa->id, 'is_platform_admin' => false]);
    }

    // ------------------------------------------------------ Controles positivos

    /**
     * Quem só existe neste estabelecimento e não é Platform Admin continua com
     * os dados globais administráveis localmente.
     */
    public function test_identidade_exclusiva_do_tenant_continua_com_dados_globais_administraveis(): void
    {
        $pessoa = $this->identidadeExclusivaDeA('pessoa.exclusiva@example.test');

        $this->actingAs($this->atacante)
            ->put(route('users.update', $pessoa), $this->dadosDeAtualizacao($pessoa, [
                'name' => 'Nome Exclusivo Alterado',
                'email' => 'pessoa.exclusiva.alterada@example.test',
                'account_status' => User::STATUS_INACTIVE,
                'password' => 'senha-nova-exclusiva',
                'password_confirmation' => 'senha-nova-exclusiva',
            ]))
            ->assertSessionHas('sucesso');

        $pessoa = $pessoa->fresh();

        $this->assertSame('Nome Exclusivo Alterado', $pessoa->name);
        $this->assertSame('pessoa.exclusiva.alterada@example.test', $pessoa->email);
        $this->assertSame(User::STATUS_INACTIVE, $pessoa->status);
        $this->assertTrue(Hash::check('senha-nova-exclusiva', $pessoa->password));
        $this->assertFalse($pessoa->isPlatformAdmin());
    }

    public function test_identidade_exclusiva_do_tenant_continua_com_reset_de_senha(): void
    {
        $pessoa = $this->identidadeExclusivaDeA('pessoa.exclusiva.reset@example.test');
        $hashAntes = $this->hashDaSenha($pessoa);

        $this->actingAs($this->atacante)
            ->post(route('users.reset-password', $pessoa))
            ->assertSessionHas('senha_temporaria');

        $this->assertFalse($this->hashDaSenha($pessoa) === $hashAntes, 'a senha da identidade exclusiva deve ser redefinida');
    }

    /**
     * E3 protege a identidade global, não o vínculo: o status em A continua
     * administrável mesmo com a pessoa presente em B.
     */
    public function test_vinculo_local_de_identidade_com_outros_vinculos_continua_administravel(): void
    {
        $pessoa = $this->identidadeComVinculoEmB('pessoa.vinculo.local@example.test');
        $globalAntes = $this->retratoGlobal($pessoa);
        $hashAntes = $this->hashDaSenha($pessoa);

        $this->actingAs($this->atacante)
            ->put(route('users.update', $pessoa), $this->dadosDeAtualizacao($pessoa, [
                'status' => TenantUser::STATUS_SUSPENDED,
            ]))
            ->assertSessionHas('sucesso');

        $pessoa = $pessoa->fresh();

        $this->assertSame(TenantUser::STATUS_SUSPENDED, $pessoa->membershipFor($this->tenantA->id)->status);
        $this->assertTrue($pessoa->canAccessTenant($this->tenantB->id), 'o vínculo em B não muda');
        $this->assertSame($globalAntes, $this->retratoGlobal($pessoa));
        $this->assertTrue($this->hashDaSenha($pessoa) === $hashAntes);
    }

    /**
     * Papel atribuído é delegável pelo manager (só view-products), para não
     * depender da contenção de papéis do E2.
     */
    public function test_papeis_locais_de_identidade_com_outros_vinculos_continuam_administraveis(): void
    {
        $pessoa = $this->identidadeComVinculoEmB('pessoa.papeis.locais@example.test');
        $pessoa->assignRole($this->papel($this->tenantB, 'admin'), $this->tenantB->id);
        $conferente = $this->papelPersonalizado($this->tenantA, 'conferente', ['view-products']);
        $globalAntes = $this->retratoGlobal($pessoa);

        $this->actingAs($this->atacante)
            ->put(route('users.update', $pessoa), $this->dadosDeAtualizacao($pessoa, [
                'roles' => [$this->papel($this->tenantA, 'viewer')->id, $conferente->id],
            ]))
            ->assertSessionHas('sucesso');

        $pessoa = $pessoa->fresh();

        $this->assertTrue($pessoa->hasRole('conferente', $this->tenantA->id));
        $this->assertTrue($pessoa->hasRole('viewer', $this->tenantA->id));
        $this->assertTrue($pessoa->hasRole('admin', $this->tenantB->id), 'os papéis em B não mudam');
        $this->assertSame($globalAntes, $this->retratoGlobal($pessoa));
    }

    // ------------------------------------------------------------------ Apoio

    /**
     * Platform Admin cujo único vínculo é o Tenant A, sem papel nele.
     */
    private function platformAdminComVinculoUnicoEmA(): User
    {
        return User::factory()
            ->platformAdmin()
            ->forTenant($this->tenantA)
            ->create([
                'name' => 'Operacao Da Plataforma',
                'email' => 'operacao.plataforma@example.test',
            ]);
    }

    /**
     * Pessoa só com vínculo em A, papel viewer, sem marcador de plataforma.
     */
    private function identidadeExclusivaDeA(string $email, array $atributos = []): User
    {
        return $this->membroComPapel($this->tenantA, 'viewer', ['email' => $email, ...$atributos]);
    }

    /**
     * Pessoa viewer em A e com vínculo em B no status informado, sem papel em B.
     */
    private function identidadeComVinculoEmB(string $email, array $atributos = [], string $statusEmB = TenantUser::STATUS_ACTIVE): User
    {
        $pessoa = $this->identidadeExclusivaDeA($email, $atributos);
        $pessoa->joinTenant($this->tenantB->id, $statusEmB);

        return $pessoa;
    }

    /**
     * Payload de edição que repete o estado atual da pessoa em A.
     */
    private function dadosDeAtualizacao(User $pessoa, array $sobrescrever): array
    {
        return [
            'name' => $pessoa->name,
            'email' => $pessoa->email,
            'account_status' => $pessoa->status,
            'status' => $pessoa->membershipFor($this->tenantA->id)->status,
            'roles' => $pessoa->rolesForTenant($this->tenantA->id)->pluck('roles.id')->all(),
            ...$sobrescrever,
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

    private function dadosDaVitima(array $sobrescrever): array
    {
        return [
            'name' => 'Pessoa Com Dois Vinculos',
            'email' => 'pessoa.dois.vinculos@example.test',
            'account_status' => User::STATUS_ACTIVE,
            'status' => TenantUser::STATUS_ACTIVE,
            'roles' => [$this->papel($this->tenantA, 'user')->id],
            ...$sobrescrever,
        ];
    }

    private function dadosDeCadastro(string $email, string $nome): array
    {
        return [
            'name' => $nome,
            'email' => $email,
            'password' => 'senha-imposta-123',
            'password_confirmation' => 'senha-imposta-123',
            'status' => TenantUser::STATUS_ACTIVE,
        ];
    }
}
