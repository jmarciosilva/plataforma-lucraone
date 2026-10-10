<?php

namespace Tests\Feature\Terminals;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * PDV-BE-04 — política de sujeito do Sanctum.
 *
 * Antes desta etapa o callback terminava em `: true`: qualquer tokenable que
 * não fosse User autenticava só porque o guard tinha considerado o token
 * válido, sem nenhuma checagem de estado. O guard não ajuda — o provider do
 * guard `sanctum` é nulo neste projeto, então o `hasValidProvider()` do
 * Sanctum aceita qualquer tokenable. Este callback é o único ponto de
 * controle, e a decisão aqui é por tipo, fail-closed.
 *
 * O que estes testes protegem é a borda: o ramo humano não pode mudar, e
 * nenhum sujeito fora da lista explícita pode entrar.
 */
class SanctumSubjectPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Invoca o callback registrado, e não uma cópia da regra: um teste que
     * reimplementasse a política não provaria nada sobre o que roda.
     */
    private function politica(PersonalAccessToken $token, bool $valido): bool
    {
        $callback = Sanctum::$accessTokenAuthenticationCallback;
        $this->assertIsCallable($callback, 'Sanctum deve ter um callback de autenticação registrado.');

        return (bool) $callback($token, $valido);
    }

    private function tokenPara(mixed $tokenable): PersonalAccessToken
    {
        $token = new PersonalAccessToken;
        $token->setRelation('tokenable', $tokenable);

        return $token;
    }

    public function test_guard_invalido_nao_e_revertido_pelo_callback(): void
    {
        $usuario = User::factory()->create(['status' => 'ACTIVE']);

        // Token expirado/fora do teto global chega com $valido = false. O
        // callback só pode restringir, nunca ressuscitar.
        $this->assertFalse($this->politica($this->tokenPara($usuario), false));
    }

    public function test_user_ativo_continua_aceito(): void
    {
        $usuario = User::factory()->create(['status' => 'ACTIVE']);

        $this->assertTrue($this->politica($this->tokenPara($usuario), true));
    }

    public function test_user_inativo_continua_recusado(): void
    {
        $usuario = User::factory()->create(['status' => 'INACTIVE']);

        $this->assertFalse($this->politica($this->tokenPara($usuario), true));
    }

    public function test_sujeito_desconhecido_e_recusado(): void
    {
        // Tenant não é sujeito de autenticação em nenhum fluxo. Antes do
        // PDV-BE-04 este caso retornava true.
        $tenant = Tenant::factory()->active()->create();

        $this->assertFalse($this->politica($this->tokenPara($tenant), true));
    }

    public function test_tokenable_ausente_e_recusado(): void
    {
        // tokenable órfão (dono apagado) não pode virar autenticação válida.
        $this->assertFalse($this->politica($this->tokenPara(null), true));
    }

    public function test_terminal_tem_politica_propria_e_nao_cai_no_ramo_desconhecido(): void
    {
        $terminal = Terminal::factory()->create();

        // PENDING não autentica: não tem instalação vinculada nem está ACTIVE.
        $this->assertFalse($this->politica($this->tokenPara($terminal), true));
    }

    public function test_tokenable_sem_hasapitokens_nao_autentica_na_borda_http(): void
    {
        Route::middleware('auth:sanctum')->get('/_pdvbe04/sujeito', fn () => response()->json(['ok' => true]));

        // Linha de PAT forjada apontando para um tokenable que não usa
        // HasApiTokens: o guard recusa antes do callback. Prova que as duas
        // camadas são fail-closed, não só a de cima.
        $tenant = Tenant::factory()->active()->create();
        $plain = 'forjado-'.str_repeat('a', 40);
        $token = new PersonalAccessToken;
        $token->forceFill([
            'tokenable_type' => Tenant::class,
            'tokenable_id' => $tenant->id,
            'name' => 'forjado',
            'token' => hash('sha256', $plain),
            'abilities' => ['*'],
        ])->save();

        $this->withToken($token->getKey().'|'.$plain)
            ->getJson('/_pdvbe04/sujeito')
            ->assertUnauthorized();
    }
}
