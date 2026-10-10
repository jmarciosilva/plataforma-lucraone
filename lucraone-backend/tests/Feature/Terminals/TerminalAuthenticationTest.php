<?php

namespace Tests\Feature\Terminals;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\HasApiTokens;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * PDV-BE-04 — Terminal como sujeito autenticável e enforcement por requisição.
 *
 * O coração desta suíte é o enforcement contínuo: um token íntegro, dentro do
 * prazo e com a ability certa precisa parar de valer no instante em que o
 * Terminal é bloqueado ou a estrutura acima dele sai de operação, sem que nada
 * toque no token. Cada teste de estado emite a credencial com tudo em ordem,
 * muda UM estado e usa o MESMO token.
 */
class TerminalAuthenticationTest extends MachineTestCase
{
    private function rotaDeMaquina(): string
    {
        Route::middleware(['auth:sanctum', 'terminal.context'])
            ->get('/_pdvbe04/maquina', fn () => response()->json(['ok' => true]));

        return '/_pdvbe04/maquina';
    }

    public function test_terminal_e_authenticatable_e_usa_hasapitokens(): void
    {
        $this->assertInstanceOf(AuthenticatableContract::class, $this->terminal);
        $this->assertContains(HasApiTokens::class, class_uses_recursive(Terminal::class));
        $this->assertSame('id', $this->terminal->getAuthIdentifierName());
        $this->assertSame($this->terminal->id, $this->terminal->getAuthIdentifier());
    }

    public function test_terminal_nao_tem_fluxo_de_senha(): void
    {
        // Falhar alto, em vez de devolver string vazia: um Terminal chegando a
        // um fluxo de senha é erro de programação, não caso a tolerar.
        $this->expectException(LogicException::class);
        $this->terminal->getAuthPassword();
    }

    public function test_terminal_nao_tem_nome_de_coluna_de_senha(): void
    {
        $this->expectException(LogicException::class);
        $this->terminal->getAuthPasswordName();
    }

    public function test_terminal_nao_tem_remember_token_funcional(): void
    {
        // null no nome da coluna é como o framework reconhece que
        // "lembrar-me" não existe para este sujeito.
        $this->assertNull($this->terminal->getRememberTokenName());
        $this->assertNull($this->terminal->getRememberToken());

        $this->terminal->setRememberToken('tentativa');
        $this->assertNull($this->terminal->getRememberToken());
        $this->assertArrayNotHasKey('remember_token', $this->terminal->fresh()->getAttributes());
    }

    public function test_terminal_nao_usa_provider_humano(): void
    {
        // O guard sanctum deste projeto tem provider nulo: nenhum model
        // restringe o tokenable, e é por isso que o callback é o controle.
        $this->assertNull(config('auth.guards.sanctum.provider'));

        // Terminal não é o model de nenhum provider configurado.
        foreach (config('auth.providers') as $provider) {
            $this->assertNotSame(Terminal::class, $provider['model'] ?? null);
        }
    }

    public function test_terminal_ativo_com_estrutura_operacional_autentica(): void
    {
        $rota = $this->rotaDeMaquina();
        $credencial = $this->emitir();

        $this->requisicaoDeMaquina($rota, $credencial->plainTextToken)
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_terminal_pendente_nao_autentica(): void
    {
        $rota = $this->rotaDeMaquina();
        $credencial = $this->emitir();

        // Volta a PENDING limpando o status sem passar pelo pairing. O token
        // continua existindo fisicamente? Não: sair de ACTIVE revoga. Mas a
        // negação não depende disso — ver o teste de token preservado abaixo.
        $this->terminal->fresh()->update(['status' => Terminal::STATUS_PENDING]);

        $this->requisicaoDeMaquina($rota, $credencial->plainTextToken)->assertUnauthorized();
    }

    public function test_terminal_bloqueado_nao_autentica(): void
    {
        $rota = $this->rotaDeMaquina();
        $credencial = $this->emitir();

        $this->terminal->fresh()->update(['status' => Terminal::STATUS_BLOCKED]);

        $this->requisicaoDeMaquina($rota, $credencial->plainTextToken)->assertUnauthorized();
    }

    public function test_terminal_revogado_nao_autentica(): void
    {
        $rota = $this->rotaDeMaquina();
        $credencial = $this->emitir();

        $this->terminal->fresh()->update(['status' => Terminal::STATUS_REVOKED]);

        $this->requisicaoDeMaquina($rota, $credencial->plainTextToken)->assertUnauthorized();
    }

    /**
     * A negação é por estado, não por ausência da linha do token.
     *
     * Este é o teste que prova enforcement por requisição de verdade: o status
     * é alterado fora do Eloquent, então o gancho de revogação não roda e a
     * credencial continua fisicamente no banco. Mesmo assim não autentica.
     */
    public function test_status_nao_operacional_nega_mesmo_com_token_preservado(): void
    {
        $rota = $this->rotaDeMaquina();
        $credencial = $this->emitir();

        Terminal::withoutGlobalScopes()->whereKey($this->terminal->id)
            ->update(['status' => Terminal::STATUS_BLOCKED]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->requisicaoDeMaquina($rota, $credencial->plainTextToken)->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_terminal_ativo_sem_instalacao_nao_autentica(): void
    {
        $rota = $this->rotaDeMaquina();
        $credencial = $this->emitir();

        // Invariante do domínio diz que isto não deveria existir; só se chega
        // aqui por escrita fora do Eloquent, e é justamente o que se barra.
        Terminal::withoutGlobalScopes()->whereKey($this->terminal->id)
            ->update(['installation_id' => null]);

        $this->requisicaoDeMaquina($rota, $credencial->plainTextToken)->assertUnauthorized();
    }

    public function test_vinculos_incoerentes_nao_autenticam(): void
    {
        $rota = $this->rotaDeMaquina();
        $credencial = $this->emitir();

        // Sem FK composta, o banco aceita uma Branch que não é mais da Company
        // do Terminal. A autenticação não passa por validador de escrita, então
        // reconfere o alinhamento por conta própria.
        $outra = Company::factory()->create();
        Branch::withoutGlobalScopes()
            ->whereKey($this->terminal->branch_id)
            ->update(['company_id' => $outra->id]);

        $this->requisicaoDeMaquina($rota, $credencial->plainTextToken)->assertUnauthorized();
    }

    /**
     * Tenant TRIAL com active=true continua valendo, como no pairing.
     *
     * A semântica vem de Tenant::isActive(), compartilhada com o fluxo humano:
     * mudá-la só para máquina criaria duas definições de "tenant em operação".
     */
    public function test_tenant_trial_ativo_autentica_como_no_pairing(): void
    {
        $rota = $this->rotaDeMaquina();
        $this->terminal->tenant->update(['status' => 'TRIAL', 'active' => true]);
        $credencial = $this->emitir();

        $this->requisicaoDeMaquina($rota, $credencial->plainTextToken)->assertOk();
    }

    #[DataProvider('estruturasNaoOperacionais')]
    public function test_estrutura_nao_operacional_derruba_token_existente(string $entidade, string $status): void
    {
        $rota = $this->rotaDeMaquina();
        $credencial = $this->emitir();

        $this->mudarEstrutura($entidade, $status);

        $this->requisicaoDeMaquina($rota, $credencial->plainTextToken)->assertUnauthorized();
    }

    public static function estruturasNaoOperacionais(): array
    {
        return [
            'tenant suspenso' => ['tenant', 'SUSPENDED'],
            'tenant cancelado' => ['tenant', 'CANCELLED'],
            'tenant soft-deleted' => ['tenant', 'DELETED'],
            'company inativa' => ['company', 'INACTIVE'],
            'company suspensa' => ['company', 'SUSPENDED'],
            'branch inativa' => ['branch', 'INACTIVE'],
            'branch suspensa' => ['branch', 'SUSPENDED'],
        ];
    }
}
