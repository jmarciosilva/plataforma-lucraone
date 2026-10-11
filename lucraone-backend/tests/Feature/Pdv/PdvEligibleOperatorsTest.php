<?php

namespace Tests\Feature\Pdv;

use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Pdv\Domain\Models\PdvOperatorProfile;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;

/**
 * GET /api/v1/pdv/operators — lista de seleção do caixa.
 *
 * A propriedade central é que a lista não pode ser mais generosa que o login:
 * oferecer um nome que vai recusar a senha é pior do que não oferecer nome
 * nenhum. Cada teste daqui remove uma das condições do login e confirma que o
 * nome desaparece da lista.
 *
 * A segunda propriedade é o que NÃO sai: senha, hash, e-mail, credencial de
 * sessão, token e qualquer pessoa de outra filial ou de outro estabelecimento.
 */
#[Group('pdv-operator')]
class PdvEligibleOperatorsTest extends PdvOperatorTestCase
{
    public function test_lista_o_operador_elegivel_com_dados_minimos(): void
    {
        $resposta = $this->listar();

        $resposta->assertOk()
            ->assertJsonPath('data.branch_id', $this->branch->id)
            ->assertJsonPath('data.operators.0.id', $this->operador->id)
            ->assertJsonPath('data.operators.0.username', $this->username)
            ->assertJsonPath('data.operators.0.display_name', $this->operador->name)
            ->assertJsonCount(1, 'data.operators')
            ->assertJsonStructure([
                'data' => ['branch_id', 'operators' => [['id', 'display_name', 'username']]],
                'meta' => ['page', 'per_page', 'total', 'last_page'],
            ]);

        $this->assertSame('no-store, private', $resposta->headers->get('Cache-Control'));
    }

    /**
     * A resposta não carrega nada além do necessário para a tela.
     */
    public function test_nao_devolve_segredo_nem_dado_desnecessario(): void
    {
        $credencial = $this->abrirSessao();
        $corpo = $this->listar()->assertOk()->getContent();

        foreach ([$this->operador->email, $this->operador->password, $credencial, hash('sha256', $credencial), $this->maquina, self::SENHA] as $proibido) {
            $this->assertStringNotContainsString((string) $proibido, $corpo);
        }

        foreach (['password', 'email', 'hash', 'token', 'credential', 'remember'] as $chave) {
            $this->assertStringNotContainsString($chave, $corpo);
        }
    }

    public function test_filial_sem_operador_devolve_lista_vazia_e_nao_erro(): void
    {
        PdvOperatorBranchAssignment::withoutGlobalScopes()->delete();

        $this->listar()->assertOk()
            ->assertJsonPath('data.operators', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_operador_inativo_nao_aparece(): void
    {
        PdvOperatorProfile::withoutGlobalScopes()->update(['status' => PdvOperatorProfile::STATUS_INACTIVE]);

        $this->listar()->assertOk()->assertJsonPath('data.operators', []);
    }

    public function test_conta_inativa_nao_aparece(): void
    {
        $this->operador->update(['status' => User::STATUS_INACTIVE]);

        $this->listar()->assertOk()->assertJsonPath('data.operators', []);
    }

    public function test_vinculo_suspenso_nao_aparece(): void
    {
        $this->operador->joinTenant($this->tenant->id, TenantUser::STATUS_SUSPENDED);

        $this->listar()->assertOk()->assertJsonPath('data.operators', []);
    }

    public function test_sem_permissao_de_pdv_nao_aparece(): void
    {
        $this->operador->removeRole(
            Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', 'pdv-operator')->sole(),
            $this->tenant->id,
        );

        $this->listar()->assertOk()->assertJsonPath('data.operators', []);
    }

    public function test_atribuicao_revogada_nao_aparece(): void
    {
        PdvOperatorBranchAssignment::withoutGlobalScopes()
            ->update(['status' => PdvOperatorBranchAssignment::STATUS_REVOKED]);

        $this->listar()->assertOk()->assertJsonPath('data.operators', []);
    }

    /**
     * Operador de OUTRA filial do mesmo estabelecimento não aparece.
     */
    public function test_operador_de_outra_filial_nao_aparece(): void
    {
        $company = Company::withoutGlobalScopes()->findOrFail($this->branch->company_id);
        $outraFilial = Branch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $company->id,
            'status' => 'ACTIVE',
        ]);

        $this->operadorAutorizado(branch: $outraFilial, username: 'so.na.outra');

        $resposta = $this->listar()->assertOk();

        $this->assertJsonNaoContem($resposta, 'so.na.outra');
        $resposta->assertJsonCount(1, 'data.operators');
    }

    /**
     * Operador de outro ESTABELECIMENTO não aparece, mesmo com username igual.
     */
    public function test_operador_de_outro_estabelecimento_nao_aparece(): void
    {
        $outra = Branch::factory()->create(['status' => 'ACTIVE']);
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($outra->tenant_id);
        $tenant->update(['status' => 'ACTIVE', 'active' => true]);
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($tenant);

        // MESMO username, outro estabelecimento: legítimo, e invisível aqui.
        $this->operadorAutorizado($tenant, $outra, $this->username);

        $resposta = $this->listar()->assertOk();

        $resposta->assertJsonCount(1, 'data.operators')
            ->assertJsonPath('data.operators.0.id', $this->operador->id);
    }

    public function test_pessoa_arquivada_nao_aparece(): void
    {
        $this->operador->delete();

        $this->listar()->assertOk()->assertJsonPath('data.operators', []);
    }

    public function test_paginacao_respeita_o_teto(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->operadorAutorizado(username: "extra{$i}");
        }

        $primeira = $this->listar(['per_page' => 2, 'page' => 1])->assertOk();
        $segunda = $this->listar(['per_page' => 2, 'page' => 2])->assertOk();

        $primeira->assertJsonCount(2, 'data.operators')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3);
        $segunda->assertJsonCount(2, 'data.operators');

        $this->assertNotSame(
            $primeira->json('data.operators.0.id'),
            $segunda->json('data.operators.0.id'),
        );

        // Pedido absurdo é limitado, não aceito.
        $this->listar(['per_page' => 5000])->assertOk()->assertJsonPath('meta.per_page', 100);
    }

    public function test_credencial_legada_nao_alcanca_a_listagem(): void
    {
        $this->terminal->tokens()->delete();
        $legada = $this->terminal->createToken('pdv-machine', ['pdv:terminal:read'], now()->addHour());

        $this->assertErroPdv($this->listar(maquina: $legada->plainTextToken), 403, 'forbidden');
    }

    public function test_token_humano_nao_alcanca_a_listagem(): void
    {
        $humano = $this->operador->createToken('auth_token', ['business:read', 'business:write']);

        $this->assertErroPdv($this->listar(maquina: $humano->plainTextToken), 403, 'forbidden');
    }

    public function test_sem_credencial_de_maquina_nao_lista(): void
    {
        $this->novaRequisicao();

        $this->assertErroPdv(
            $this->withServerVariables(['REMOTE_ADDR' => self::IP_CLIENTE])->getJson('/api/v1/pdv/operators'),
            401,
            'unauthenticated',
        );
    }

    public function test_cabecalho_de_tenant_e_recusado(): void
    {
        $this->assertErroPdv(
            $this->listar(cabecalhos: ['X-Tenant-ID' => $this->tenant->id]),
            403,
            'forbidden',
        );
    }

    public function test_terminal_bloqueado_nao_lista(): void
    {
        $this->terminal->update(['status' => Terminal::STATUS_BLOCKED]);

        $this->assertErroPdv($this->listar(), 401, 'unauthenticated');
    }

    /**
     * A listagem NÃO autentica: ela não cria sessão nem dispensa a senha.
     */
    public function test_listagem_nao_cria_sessao_nem_dispensa_senha(): void
    {
        $this->listar()->assertOk();

        $this->assertDatabaseCount('pdv_operator_sessions', 0);

        // Com a senha errada, continua 401 — a lista não vale como prova.
        $this->login(payload: ['username' => $this->username, 'password' => 'errada-mas-longa'])
            ->assertStatus(401);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $cabecalhos
     */
    private function listar(array $query = [], ?string $maquina = null, array $cabecalhos = []): TestResponse
    {
        $this->novaRequisicao();

        $url = '/api/v1/pdv/operators'.($query === [] ? '' : '?'.http_build_query($query));

        return $this->withServerVariables(['REMOTE_ADDR' => self::IP_CLIENTE])
            ->withToken($maquina ?? $this->maquina)
            ->withHeaders($cabecalhos)
            ->getJson($url);
    }

    private function assertJsonNaoContem(TestResponse $resposta, string $valor): void
    {
        $this->assertStringNotContainsString($valor, $resposta->getContent());
    }
}
