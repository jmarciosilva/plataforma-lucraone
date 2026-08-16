<?php

namespace Tests\Feature\Admin;

use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\TenantResolver;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * F3.2 — Authentication (Login/Logout)
 *
 * Autenticação do painel por sessão (guard web), incluindo a escolha do
 * estabelecimento quando a pessoa tem mais de um vínculo ativo.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $casaAlta;

    private Tenant $padaria;

    private User $gerente;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('maria@casaalta.com|127.0.0.1');

        $this->casaAlta = Tenant::factory()->create(['name' => 'Casa Alta']);
        $this->padaria = Tenant::factory()->create(['name' => 'Padaria Central']);

        // Vínculo com um único estabelecimento
        $this->gerente = User::factory()->create([
            'email' => 'maria@casaalta.com',
            'password' => Hash::make('senha-correta'),
        ]);

        $this->gerente->joinTenant($this->casaAlta->id);
    }

    public function test_login_valido_leva_ao_dashboard(): void
    {
        $resposta = $this->post('/login', [
            'email' => 'maria@casaalta.com',
            'password' => 'senha-correta',
        ]);

        $resposta->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->gerente);

        // Vínculo único: o estabelecimento é definido sem passar pelo seletor
        $this->assertEquals(
            $this->casaAlta->id,
            session(TenantResolver::SESSAO_TENANT)
        );
    }

    public function test_senha_errada_nao_autentica(): void
    {
        $resposta = $this->from('/login')->post('/login', [
            'email' => 'maria@casaalta.com',
            'password' => 'senha-errada',
        ]);

        $resposta->assertRedirect('/login');
        $resposta->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_mensagem_de_erro_nao_revela_se_o_email_existe(): void
    {
        $mensagemGenerica = 'e-mail ou senha incorretos.';

        // E-mail que não existe
        $this->from('/login')
            ->post('/login', [
                'email' => 'ninguem@lugar.com',
                'password' => 'qualquer',
            ])
            ->assertSessionHasErrors(['email' => $mensagemGenerica]);

        // E-mail que existe, senha errada — mesma mensagem, sem pistas
        $this->from('/login')
            ->post('/login', [
                'email' => 'maria@casaalta.com',
                'password' => 'senha-errada',
            ])
            ->assertSessionHasErrors(['email' => $mensagemGenerica]);
    }

    public function test_conta_inativa_nao_entra(): void
    {
        $this->gerente->update(['status' => User::STATUS_INACTIVE]);

        $resposta = $this->from('/login')->post('/login', [
            'email' => 'maria@casaalta.com',
            'password' => 'senha-correta',
        ]);

        $resposta->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_vinculo_suspenso_nao_da_acesso(): void
    {
        $this->gerente->joinTenant($this->casaAlta->id, TenantUser::STATUS_SUSPENDED);

        $resposta = $this->from('/login')->post('/login', [
            'email' => 'maria@casaalta.com',
            'password' => 'senha-correta',
        ]);

        $resposta->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_varios_vinculos_caem_no_seletor(): void
    {
        $this->gerente->joinTenant($this->padaria->id);

        $resposta = $this->post('/login', [
            'email' => 'maria@casaalta.com',
            'password' => 'senha-correta',
        ]);

        $resposta->assertRedirect(route('estabelecimentos.escolher'));
        $this->assertAuthenticatedAs($this->gerente);

        // Nada foi escolhido ainda
        $this->assertNull(session(TenantResolver::SESSAO_TENANT));
    }

    public function test_escolher_estabelecimento_libera_o_painel(): void
    {
        $this->gerente->joinTenant($this->padaria->id);

        $this->actingAs($this->gerente)
            ->post(route('estabelecimentos.definir'), [
                'tenant_id' => $this->padaria->id,
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertEquals(
            $this->padaria->id,
            session(TenantResolver::SESSAO_TENANT)
        );
    }

    public function test_nao_e_possivel_escolher_estabelecimento_sem_vinculo(): void
    {
        // A gerente só tem vínculo com a Casa Alta
        $this->actingAs($this->gerente)
            ->from(route('estabelecimentos.escolher'))
            ->post(route('estabelecimentos.definir'), [
                'tenant_id' => $this->padaria->id,
            ])
            ->assertRedirect(route('estabelecimentos.escolher'));

        $this->assertNotEquals(
            $this->padaria->id,
            session(TenantResolver::SESSAO_TENANT)
        );
    }

    public function test_logout_encerra_a_sessao(): void
    {
        // Entra pelo fluxo real, para que a sessão seja a de verdade
        $this->post('/login', [
            'email' => 'maria@casaalta.com',
            'password' => 'senha-correta',
        ]);

        $this->assertAuthenticatedAs($this->gerente);

        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();

        // A sessão foi invalidada de fato: o painel volta a exigir login
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_visitante_em_rota_protegida_vai_para_o_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_sessao_persiste_entre_requisicoes(): void
    {
        $this->post('/login', [
            'email' => 'maria@casaalta.com',
            'password' => 'senha-correta',
        ]);

        $this->get('/dashboard')->assertOk();
        $this->get('/dashboard')->assertOk();

        $this->assertAuthenticatedAs($this->gerente);
    }

    public function test_login_registra_o_ultimo_acesso(): void
    {
        $this->gerente->forceFill(['last_login_at' => null])->save();

        $this->post('/login', [
            'email' => 'maria@casaalta.com',
            'password' => 'senha-correta',
        ]);

        $this->assertNotNull($this->gerente->fresh()->last_login_at);
    }

    public function test_rate_limiting_bloqueia_apos_tentativas_repetidas(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')->post('/login', [
                'email' => 'maria@casaalta.com',
                'password' => 'errada',
            ]);
        }

        // Na sexta tentativa o freio entra, mesmo com a senha certa
        $resposta = $this->from('/login')->post('/login', [
            'email' => 'maria@casaalta.com',
            'password' => 'senha-correta',
        ]);

        $resposta->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'muitas tentativas',
            $resposta->getSession()->get('errors')->first('email')
        );
        $this->assertGuest();
    }

    public function test_usuario_autenticado_nao_ve_a_tela_de_login(): void
    {
        $this->actingAs($this->gerente)
            ->get('/login')
            ->assertRedirect(route('dashboard'));
    }
}
