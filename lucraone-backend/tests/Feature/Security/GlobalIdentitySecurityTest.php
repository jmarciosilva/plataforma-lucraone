<?php

namespace Tests\Feature\Security;

use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
