<?php

namespace Tests\Feature\Platform;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Infrastructure\Console\PromoverPlatformAdminCommand;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Concerns\MontaCenariosDeAutorizacao;
use Tests\TestCase;

/**
 * ONB-01A · A — promoção controlada de Platform Admin.
 *
 * O marcador de Platform Admin não é mass assignable e nenhuma tela o escreve,
 * de propósito (SEC-04). Até aqui o único caminho era Tinker ou SQL no
 * servidor, o que deixa o bootstrap da plataforma sem rastro na aplicação.
 *
 * O comando é o caminho oficial. Duas propriedades são protegidas aqui:
 *
 *   - a cirurgia: ele grava `is_platform_admin` e **nada mais** — não cria
 *     conta, não mexe em senha, status, vínculo nem papel de estabelecimento;
 *   - a intenção: por padrão a concessão exige confirmação explícita, com
 *     nome e e-mail na tela, e recusar não grava nem registra nada.
 *
 * O caminho padrão (interativo) é o que a maioria dos casos exercita, porque é
 * o que um operador usa de fato. O `--force` tem testes próprios.
 */
#[Group('onb-01a')]
class PromoverPlatformAdminCommandTest extends TestCase
{
    use MontaCenariosDeAutorizacao, RefreshDatabase;

    /**
     * Data antiga e fixa para provar que uma recusa não grava nada: comparar
     * `updated_at` com "agora" passaria por acidente se a gravação caísse no
     * mesmo segundo.
     */
    private const CARIMBO_ANTIGO = '2020-01-01 00:00:00';

    private Tenant $tenant;

    private User $operador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create(['name' => 'Casa Alta']);
        $this->provisionarPapeisPadrao();

        $this->operador = $this->membroComPapel($this->tenant, 'admin', [
            'name' => 'Pessoa Operadora',
            'email' => 'operador@lucraone.test',
        ]);
    }

    /**
     * Caminho padrão: o comando pergunta, e aqui respondemos.
     *
     * A pergunta vem da constante do próprio comando — se o texto mudar, os
     * testes acompanham em vez de quebrar por cópia desatualizada.
     */
    private function promover(string $email, string $resposta = 'yes'): PendingCommand
    {
        return $this->artisan('plataforma:promover', ['email' => $email])
            ->expectsConfirmation(PromoverPlatformAdminCommand::PERGUNTA_DE_CONFIRMACAO, $resposta);
    }

    /**
     * Caminho não interativo, para automação.
     */
    private function promoverComForce(string $email): PendingCommand
    {
        return $this->artisan('plataforma:promover', ['email' => $email, '--force' => true]);
    }

    private function envelhecerCarimbo(User $usuario): void
    {
        DB::table('users')->where('id', $usuario->id)->update(['updated_at' => self::CARIMBO_ANTIGO]);
    }

    private function carimbo(User $usuario): string
    {
        return (string) DB::table('users')->where('id', $usuario->id)->value('updated_at');
    }

    // ----------------------------------------------------------------- cirurgia

    public function test_promove_usuario_existente_que_ainda_nao_e_platform_admin(): void
    {
        $this->assertFalse($this->operador->isPlatformAdmin(), 'pré-condição: a conta não deve nascer Platform Admin');

        $this->promover('operador@lucraone.test')->assertSuccessful();
    }

    public function test_is_platform_admin_fica_verdadeiro_apos_a_promocao(): void
    {
        $this->promover('operador@lucraone.test')->assertSuccessful();

        $this->assertTrue($this->operador->fresh()->isPlatformAdmin());

        $this->assertDatabaseHas('users', [
            'id' => $this->operador->id,
            'is_platform_admin' => true,
        ]);
    }

    public function test_nenhum_papel_de_estabelecimento_e_alterado(): void
    {
        $antes = DB::table('user_role')
            ->where('user_id', $this->operador->id)
            ->orderBy('role_id')
            ->get(['role_id', 'tenant_id'])
            ->map(fn ($linha) => "{$linha->role_id}@{$linha->tenant_id}")
            ->all();

        $this->assertNotEmpty($antes, 'pré-condição: a conta precisa ter papel no estabelecimento');

        $this->promover('operador@lucraone.test')->assertSuccessful();

        $depois = DB::table('user_role')
            ->where('user_id', $this->operador->id)
            ->orderBy('role_id')
            ->get(['role_id', 'tenant_id'])
            ->map(fn ($linha) => "{$linha->role_id}@{$linha->tenant_id}")
            ->all();

        $this->assertSame($antes, $depois);
        $this->assertTrue($this->operador->fresh()->hasRole('admin', $this->tenant->id));
    }

    public function test_nenhum_vinculo_com_estabelecimento_e_criado_ou_removido(): void
    {
        $antes = DB::table('tenant_user')
            ->where('user_id', $this->operador->id)
            ->orderBy('tenant_id')
            ->get(['tenant_id', 'status', 'joined_at'])
            ->map(fn ($linha) => "{$linha->tenant_id}:{$linha->status}:{$linha->joined_at}")
            ->all();

        $this->assertCount(1, $antes, 'pré-condição: um vínculo ativo');

        $this->promover('operador@lucraone.test')->assertSuccessful();

        $depois = DB::table('tenant_user')
            ->where('user_id', $this->operador->id)
            ->orderBy('tenant_id')
            ->get(['tenant_id', 'status', 'joined_at'])
            ->map(fn ($linha) => "{$linha->tenant_id}:{$linha->status}:{$linha->joined_at}")
            ->all();

        $this->assertSame($antes, $depois);
    }

    public function test_a_senha_nao_e_alterada(): void
    {
        $hashAntes = DB::table('users')->where('id', $this->operador->id)->value('password');

        $this->promover('operador@lucraone.test')->assertSuccessful();

        $this->assertSame(
            $hashAntes,
            DB::table('users')->where('id', $this->operador->id)->value('password')
        );
    }

    public function test_o_status_da_conta_nao_e_alterado(): void
    {
        $this->operador->forceFill(['status' => User::STATUS_INACTIVE])->save();

        $this->promover('operador@lucraone.test')->assertSuccessful();

        $recarregado = $this->operador->fresh();

        $this->assertSame(User::STATUS_INACTIVE, $recarregado->status);
        $this->assertTrue($recarregado->isPlatformAdmin());
    }

    public function test_e_mail_e_demais_atributos_da_identidade_nao_sao_alterados(): void
    {
        $antes = DB::table('users')->where('id', $this->operador->id)->first();

        $this->promover('operador@lucraone.test')->assertSuccessful();

        $depois = DB::table('users')->where('id', $this->operador->id)->first();

        foreach (['name', 'email', 'password', 'status', 'email_verified_at', 'remember_token', 'deleted_at'] as $coluna) {
            $this->assertEquals($antes->{$coluna}, $depois->{$coluna}, "a coluna {$coluna} não deveria mudar");
        }

        $this->assertEquals(0, $antes->is_platform_admin);
        $this->assertEquals(1, $depois->is_platform_admin);
    }

    // ------------------------------------------------------------- confirmação

    public function test_a_confirmacao_mostra_nome_e_e_mail_antes_de_conceder(): void
    {
        $this->promover('operador@lucraone.test')
            ->expectsOutputToContain('Pessoa Operadora')
            ->expectsOutputToContain('operador@lucraone.test')
            ->expectsOutputToContain('Platform Admin')
            ->assertSuccessful();
    }

    public function test_recusar_a_confirmacao_mantem_a_conta_sem_autoridade(): void
    {
        $this->promover('operador@lucraone.test', 'no')->assertSuccessful();

        $this->assertFalse($this->operador->fresh()->isPlatformAdmin());

        $this->assertDatabaseHas('users', [
            'id' => $this->operador->id,
            'is_platform_admin' => false,
        ]);

        $this->assertSame(0, DB::table('users')->where('is_platform_admin', true)->count());
    }

    public function test_recusar_a_confirmacao_nao_altera_updated_at(): void
    {
        $this->envelhecerCarimbo($this->operador);

        $retrato = DB::table('users')->where('id', $this->operador->id)->first();

        $this->promover('operador@lucraone.test', 'no')->assertSuccessful();

        $this->assertSame(self::CARIMBO_ANTIGO, $this->carimbo($this->operador));
        $this->assertEquals($retrato, DB::table('users')->where('id', $this->operador->id)->first());
    }

    public function test_recusar_a_confirmacao_nao_gera_registro_de_promocao(): void
    {
        Log::spy();

        $this->promover('operador@lucraone.test', 'no')->assertSuccessful();

        Log::shouldNotHaveReceived('info');
    }

    public function test_a_recusa_diz_que_nada_foi_alterado(): void
    {
        $this->promover('operador@lucraone.test', 'no')
            ->expectsOutputToContain('nada foi alterado')
            ->assertSuccessful();
    }

    // -------------------------------------------------------------------- force

    public function test_force_promove_sem_pedir_confirmacao(): void
    {
        // Nenhum expectsConfirmation registrado: se o comando perguntasse,
        // o teste quebraria aqui.
        $this->promoverComForce('operador@lucraone.test')->assertSuccessful();

        $this->assertTrue($this->operador->fresh()->isPlatformAdmin());
    }

    public function test_force_tambem_e_cirurgico(): void
    {
        $hashAntes = DB::table('users')->where('id', $this->operador->id)->value('password');

        $this->promoverComForce('operador@lucraone.test')->assertSuccessful();

        $recarregado = $this->operador->fresh();

        $this->assertTrue($recarregado->isPlatformAdmin());
        $this->assertSame($hashAntes, DB::table('users')->where('id', $this->operador->id)->value('password'));
        $this->assertTrue($recarregado->hasRole('admin', $this->tenant->id));
        $this->assertCount(1, $recarregado->memberships()->get());
    }

    // --------------------------------------------------- recusas antes de gravar

    public function test_usuario_inexistente_falha_sem_pedir_confirmacao_e_sem_gravar(): void
    {
        $usuariosAntes = DB::table('users')->count();

        // Sem expectsConfirmation: a falha tem de vir antes de qualquer pergunta.
        $this->artisan('plataforma:promover', ['email' => 'ninguem@lucraone.test'])
            ->expectsOutputToContain('ninguem@lucraone.test')
            ->assertFailed();

        $this->assertSame($usuariosAntes, DB::table('users')->count());
        $this->assertDatabaseMissing('users', ['email' => 'ninguem@lucraone.test']);
        $this->assertSame(0, DB::table('users')->where('is_platform_admin', true)->count());
    }

    public function test_o_comando_nao_cria_usuario_inexistente(): void
    {
        $this->artisan('plataforma:promover', ['email' => 'novo@lucraone.test'])
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'novo@lucraone.test']);
    }

    public function test_identidade_arquivada_falha_sem_pedir_confirmacao(): void
    {
        $this->operador->delete();

        $this->artisan('plataforma:promover', ['email' => 'operador@lucraone.test'])
            ->assertFailed();

        $this->assertSame(0, DB::table('users')->where('is_platform_admin', true)->count());
    }

    public function test_force_nao_promove_identidade_inexistente(): void
    {
        $this->promoverComForce('ninguem@lucraone.test')->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'ninguem@lucraone.test']);
    }

    // --------------------------------------------------------------- idempotência

    public function test_promover_quem_ja_e_platform_admin_nao_pede_confirmacao_nem_grava(): void
    {
        $this->promover('operador@lucraone.test')->assertSuccessful();

        $this->envelhecerCarimbo($this->operador);
        $retrato = DB::table('users')->where('id', $this->operador->id)->first();

        // Sem expectsConfirmation: já sendo Platform Admin, nada há a confirmar.
        $this->artisan('plataforma:promover', ['email' => 'operador@lucraone.test'])
            ->expectsOutputToContain('já')
            ->assertSuccessful();

        $this->assertEquals($retrato, DB::table('users')->where('id', $this->operador->id)->first());
        $this->assertSame(self::CARIMBO_ANTIGO, $this->carimbo($this->operador));
        $this->assertTrue($this->operador->fresh()->isPlatformAdmin());
        $this->assertSame(1, DB::table('users')->where('is_platform_admin', true)->count());
    }

    public function test_quem_ja_e_platform_admin_nao_gera_novo_registro_de_promocao(): void
    {
        $this->promoverComForce('operador@lucraone.test')->assertSuccessful();

        Log::spy();

        $this->promoverComForce('operador@lucraone.test')->assertSuccessful();

        Log::shouldNotHaveReceived('info');
    }

    // ----------------------------------------------------------- identidade global

    public function test_localiza_a_identidade_global_sem_depender_de_vinculo(): void
    {
        $semVinculo = User::factory()->create(['email' => 'avulso@lucraone.test']);

        $this->assertCount(0, $semVinculo->memberships()->get(), 'pré-condição: nenhum vínculo');

        $this->promover('avulso@lucraone.test')->assertSuccessful();

        $this->assertTrue($semVinculo->fresh()->isPlatformAdmin());
        $this->assertCount(0, $semVinculo->fresh()->memberships()->get());
    }

    public function test_localiza_a_identidade_global_de_outro_estabelecimento(): void
    {
        $outro = Tenant::factory()->active()->create(['name' => 'Outra Loja']);
        $pessoa = User::factory()->forTenant($outro)->create(['email' => 'outra@lucraone.test']);

        $this->promover('outra@lucraone.test')->assertSuccessful();

        $this->assertTrue($pessoa->fresh()->isPlatformAdmin());
    }

    // ------------------------------------------------------------------ fronteiras

    public function test_is_platform_admin_continua_fora_do_mass_assignment(): void
    {
        $naoSalvo = new User(['is_platform_admin' => true]);

        $this->assertNotTrue($naoSalvo->is_platform_admin);

        $criado = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Tentativa de Escalada',
            'email' => 'escalada@lucraone.test',
            'password' => 'senha-de-teste',
            'is_platform_admin' => true,
        ]);

        $this->assertFalse($criado->fresh()->isPlatformAdmin());
    }

    public function test_admin_de_estabelecimento_continua_sem_autoridade_de_plataforma(): void
    {
        $outraPessoa = User::factory()->forTenant($this->tenant)->create(['email' => 'promovido@lucraone.test']);

        $this->promover('promovido@lucraone.test')->assertSuccessful();

        // O admin do estabelecimento não foi promovido e continua barrado.
        $this->assertFalse($this->operador->fresh()->isPlatformAdmin());

        $this->actingAs($this->operador)
            ->get(route('tenants.index'))
            ->assertForbidden();

        $this->assertTrue($outraPessoa->fresh()->isPlatformAdmin());
    }

    // ------------------------------------------------------------------------ log

    public function test_a_promocao_deixa_registro_operacional_sem_dado_sensivel(): void
    {
        Log::spy();

        $this->promover('operador@lucraone.test')->assertSuccessful();

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $mensagem, array $contexto = []) {
                $serializado = strtolower($mensagem.' '.json_encode($contexto));

                foreach (['password', 'senha', 'remember_token', 'hash', 'token', 'session'] as $proibido) {
                    if (str_contains($serializado, $proibido)) {
                        return false;
                    }
                }

                return ($contexto['user_id'] ?? null) === $this->operador->id;
            });
    }

    public function test_o_registro_da_promocao_nao_contem_e_mail_nem_nome(): void
    {
        Log::spy();

        $this->promover('operador@lucraone.test')->assertSuccessful();

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $mensagem, array $contexto = []) {
                $serializado = $mensagem.' '.json_encode($contexto);

                // Nem o endereço, nem o nome, nem qualquer coisa com arroba.
                if (str_contains($serializado, 'operador@lucraone.test')
                    || str_contains($serializado, '@')
                    || str_contains($serializado, 'Pessoa Operadora')) {
                    return false;
                }

                return array_keys($contexto) === ['user_id', 'origem', 'comando'];
            });
    }

    // -------------------------------------------------------------------- opções

    public function test_o_comando_aceita_force_e_segue_recusando_opcoes_fora_do_escopo(): void
    {
        $definicao = $this->app->make(Kernel::class)
            ->all()['plataforma:promover']
            ->getDefinition();

        $this->assertTrue($definicao->hasArgument('email'));
        $this->assertTrue($definicao->hasOption('force'), 'o comando precisa aceitar --force');

        foreach (['tenant', 'role', 'password', 'create-user'] as $opcao) {
            $this->assertFalse(
                $definicao->hasOption($opcao),
                "o comando não deve aceitar --{$opcao}"
            );
        }
    }
}
