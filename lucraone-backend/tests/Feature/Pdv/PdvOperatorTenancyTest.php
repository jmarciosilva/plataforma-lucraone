<?php

namespace Tests\Feature\Pdv;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Domain\Exceptions\OperatorSessionFailed;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Pdv\Domain\Models\PdvOperatorSession;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use PHPUnit\Framework\Attributes\Group;

/**
 * Fase 5 — isolamento entre estabelecimentos no caminho do operador.
 *
 * O ponto é que o contexto NUNCA vem do cliente. Nas rotas de operador ele é
 * derivado do Terminal autenticado, e por isso não existe campo, cabeçalho ou
 * parâmetro capaz de movê-lo. Uma permissão de estabelecimento continua valendo
 * no estabelecimento inteiro — e é justamente por isso que a atribuição à
 * filial existe.
 */
#[Group('pdv-operator')]
class PdvOperatorTenancyTest extends PdvOperatorTestCase
{
    public function test_pessoa_de_outro_estabelecimento_nao_autentica(): void
    {
        $outraLoja = $this->outraLoja();

        $forasteiro = User::factory()->create(['password' => self::SENHA]);
        $forasteiro->joinTenant($outraLoja['tenant']->id, TenantUser::STATUS_ACTIVE);
        $this->concederPermissaoDePdv($forasteiro, $outraLoja['tenant']);
        $this->atribuirFilial($forasteiro, $outraLoja['branch']);
        // Perfil de caixa na OUTRA loja, com o mesmo username que o teste tenta
        // no caixa desta: o login tem de não encontrá-lo aqui.
        $this->perfilDeOperador($forasteiro, $outraLoja['tenant'], 'forasteiro');

        $resposta = $this->login(payload: ['username' => 'forasteiro', 'password' => self::SENHA]);

        $this->assertErroPdv($resposta, 401, OperatorSessionFailed::CODE_AUTHENTICATION);
        $this->assertSame(0, PdvOperatorSession::withoutGlobalScopes()->count());
    }

    /**
     * Dois vínculos, uma atribuição: a pessoa opera só onde foi atribuída.
     *
     * É o caso que mostra por que o vínculo com o estabelecimento não basta.
     */
    public function test_pessoa_de_dois_estabelecimentos_opera_so_onde_tem_atribuicao(): void
    {
        $outraLoja = $this->outraLoja();

        $this->operador->joinTenant($outraLoja['tenant']->id, TenantUser::STATUS_ACTIVE);
        $this->concederPermissaoDePdv($this->operador, $outraLoja['tenant']);

        // No caixa da loja onde tem atribuição: entra.
        $this->login()->assertCreated();

        // No caixa da outra loja, com vínculo e permissão lá, mas sem
        // atribuição àquela filial: não entra.
        $caixaDaOutra = $this->terminalOperacional($outraLoja['branch']);
        $resposta = $this->login($this->credencialDeMaquina($caixaDaOutra));

        $this->assertErroPdv($resposta, 401, OperatorSessionFailed::CODE_AUTHENTICATION);
    }

    /**
     * Mesma loja, outra filial: a permissão vale no estabelecimento, a
     * atribuição não.
     */
    public function test_atribuicao_de_uma_filial_nao_autoriza_outra_filial(): void
    {
        $company = Company::withoutGlobalScopes()->findOrFail($this->branch->company_id);
        $outraFilial = Branch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $company->id,
            'status' => 'ACTIVE',
        ]);

        $caixaDaOutraFilial = $this->terminalOperacional($outraFilial);

        $resposta = $this->login($this->credencialDeMaquina($caixaDaOutraFilial));

        $this->assertErroPdv($resposta, 401, OperatorSessionFailed::CODE_AUTHENTICATION);
    }

    /**
     * Atribuição criada na filial de OUTRO estabelecimento não autoriza:
     * a revalidação procura pela tripla (estabelecimento do terminal, pessoa,
     * filial do terminal), e um dos três nunca bate.
     */
    public function test_atribuicao_em_estabelecimento_diferente_nao_autoriza(): void
    {
        $outraLoja = $this->outraLoja();

        PdvOperatorBranchAssignment::withoutGlobalScopes()->where('user_id', $this->operador->id)->delete();
        $this->atribuirFilial($this->operador, $outraLoja['branch']);

        $this->assertErroPdv($this->login(), 401, OperatorSessionFailed::CODE_AUTHENTICATION);
    }

    /**
     * Papel administrativo não é passe livre.
     *
     * O admin do estabelecimento tem manage-branches, manage-users e tudo mais
     * — e continua sem operar caixa, porque não tem `access-pdv` nem
     * atribuição. Autoridade para administrar não é autoridade para operar.
     */
    public function test_administrador_do_estabelecimento_nao_opera_caixa_por_causa_do_papel(): void
    {
        $admin = User::factory()->create(['password' => self::SENHA]);
        app(ProvisionarEstabelecimento::class)->atribuirAdministrador($this->tenant, $admin);
        // Perfil existe, mas sem `access-pdv` e sem atribuição: o papel
        // administrativo não substitui nenhuma das duas.
        $this->perfilDeOperador($admin, $this->tenant, 'admin.loja');

        $resposta = $this->login(payload: ['username' => 'admin.loja', 'password' => self::SENHA]);

        $this->assertErroPdv($resposta, 401, OperatorSessionFailed::CODE_AUTHENTICATION);
    }

    /**
     * Platform Admin também não: a autoridade dele é da plataforma, e não
     * substitui vínculo, permissão nem atribuição.
     */
    public function test_platform_admin_nao_opera_caixa_sem_atribuicao(): void
    {
        $plataforma = User::factory()->create([
            'password' => self::SENHA,
            'is_platform_admin' => true,
        ]);
        $plataforma->joinTenant($this->tenant->id, TenantUser::STATUS_ACTIVE);
        $this->perfilDeOperador($plataforma, $this->tenant, 'plataforma');

        $resposta = $this->login(payload: ['username' => 'plataforma', 'password' => self::SENHA]);

        $this->assertErroPdv($resposta, 401, OperatorSessionFailed::CODE_AUTHENTICATION);
    }

    public function test_tenant_id_no_corpo_e_recusado_como_campo_desconhecido(): void
    {
        $outraLoja = $this->outraLoja();

        $resposta = $this->login(payload: [
            'username' => $this->username,
            'password' => self::SENHA,
            'tenant_id' => $outraLoja['tenant']->id,
        ]);

        $this->assertErroPdv($resposta, 422, 'validation_error');
    }

    public function test_branch_id_no_corpo_e_recusado_como_campo_desconhecido(): void
    {
        $resposta = $this->login(payload: [
            'username' => $this->username,
            'password' => self::SENHA,
            'branch_id' => $this->branch->id,
        ]);

        $this->assertErroPdv($resposta, 422, 'validation_error');
    }

    /**
     * A sessão carrega o contexto do Terminal, nunca o escolhido pelo cliente.
     */
    public function test_sessao_grava_o_contexto_derivado_do_terminal(): void
    {
        $this->login()->assertCreated();

        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();

        $this->assertSame($this->terminal->tenant_id, $sessao->tenant_id);
        $this->assertSame($this->terminal->company_id, $sessao->company_id);
        $this->assertSame($this->terminal->branch_id, $sessao->branch_id);
    }

    /**
     * Terminal de outro estabelecimento não reaproveita sessão alheia, mesmo
     * que a pessoa seja autorizada nos dois.
     */
    public function test_terminal_de_outro_estabelecimento_nao_reutiliza_sessao(): void
    {
        $credencial = $this->abrirSessao();

        $outraLoja = $this->outraLoja();
        $this->operador->joinTenant($outraLoja['tenant']->id, TenantUser::STATUS_ACTIVE);
        $this->concederPermissaoDePdv($this->operador, $outraLoja['tenant']);
        $this->atribuirFilial($this->operador, $outraLoja['branch']);

        $caixaDaOutra = $this->terminalOperacional($outraLoja['branch']);

        $this->assertErroPdv(
            $this->consultarSessao($credencial, $this->credencialDeMaquina($caixaDaOutra)),
            401,
            OperatorSessionFailed::CODE_SESSION_INVALID,
        );
    }

    /**
     * Uma segunda loja operacional e independente.
     *
     * @return array{tenant: Tenant, branch: Branch}
     */
    private function outraLoja(): array
    {
        $branch = Branch::factory()->create(['status' => 'ACTIVE']);
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($branch->tenant_id);
        $tenant->update(['status' => 'ACTIVE', 'active' => true]);

        app(ProvisionarEstabelecimento::class)->provisionarMatriz($tenant);

        return ['tenant' => $tenant->fresh(), 'branch' => $branch->fresh()];
    }
}
