<?php

namespace Tests\Feature\Terminals;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Terminals\Application\TerminalContext;
use App\Modules\Terminals\Domain\MachineTokenAbility;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Support\Facades\Route;

/**
 * PDV-BE-04 — contexto de máquina, proibição de X-Tenant-ID e fronteira de sujeito.
 *
 * Duas ideias se provam aqui. A primeira: o contexto de uma requisição de
 * máquina vem inteiramente do Terminal autenticado, e cabeçalho nenhum
 * participa — nem para confirmar. A segunda: a fronteira entre pessoa e máquina
 * é de TIPO, não de ability; por isso cada caso de fronteira é testado também
 * com a ability "certa" adulterada na fixture, que é a única forma de provar
 * que não é a ability que está barrando.
 */
class TerminalMachineContextTest extends MachineTestCase
{
    private function rotaDeContexto(): string
    {
        Route::middleware(['auth:sanctum', 'terminal.context'])
            ->get('/_pdvbe04/contexto', fn (TerminalContext $maquina, TenantContext $tenant) => response()->json([
                'terminal_id' => $maquina->terminalId(),
                'tenant_id' => $maquina->tenantId(),
                'company_id' => $maquina->companyId(),
                'branch_id' => $maquina->branchId(),
                'tenant_context' => $tenant->resolved() ? $tenant->id() : null,
            ]));

        return '/_pdvbe04/contexto';
    }

    private function rotaComAbility(): string
    {
        Route::middleware(['auth:sanctum', 'terminal.context', 'machine.ability:pdv:terminal:read'])
            ->get('/_pdvbe04/com-ability', fn () => response()->json(['ok' => true]));

        return '/_pdvbe04/com-ability';
    }

    public function test_contexto_vem_do_terminal_autenticado(): void
    {
        $rota = $this->rotaDeContexto();
        $credencial = $this->emitir();
        $terminal = $this->terminal->fresh();

        $this->requisicaoDeMaquina($rota, $credencial->plainTextToken)
            ->assertOk()
            ->assertJson([
                'terminal_id' => $terminal->id,
                'tenant_id' => $terminal->tenant_id,
                'company_id' => $terminal->company_id,
                'branch_id' => $terminal->branch_id,
            ]);
    }

    public function test_tenant_context_recebe_o_tenant_do_terminal(): void
    {
        $rota = $this->rotaDeContexto();
        $credencial = $this->emitir();

        // O TenantScope dos models de negócio lê o TenantContext; preenchê-lo a
        // partir do vínculo do Terminal é o que permite consultar dados com o
        // mesmo filtro do caminho humano, sem header nenhum.
        $this->requisicaoDeMaquina($rota, $credencial->plainTextToken)
            ->assertOk()
            ->assertJson(['tenant_context' => $this->terminal->fresh()->tenant_id]);
    }

    public function test_sem_header_o_contexto_e_resolvido_normalmente(): void
    {
        $rota = $this->rotaDeContexto();
        $credencial = $this->emitir();

        $this->requisicaoDeMaquina($rota, $credencial->plainTextToken)->assertOk();
    }

    public function test_x_tenant_id_correto_e_recusado(): void
    {
        $rota = $this->rotaDeContexto();
        $credencial = $this->emitir();

        // Recusado mesmo apontando para o tenant certo: aceitar "porque
        // coincide" ensinaria o cliente de PDV a enviar o cabeçalho, e a
        // divergência passaria a ser decisão de servidor.
        $this->requisicaoDeMaquina($rota, $credencial->plainTextToken, [
            'X-Tenant-ID' => $this->terminal->fresh()->tenant_id,
        ])->assertForbidden();
    }

    public function test_x_tenant_id_diferente_e_recusado(): void
    {
        $rota = $this->rotaDeContexto();
        $credencial = $this->emitir();
        $outro = Terminal::factory()->create();

        $this->requisicaoDeMaquina($rota, $credencial->plainTextToken, [
            'X-Tenant-ID' => $outro->tenant_id,
        ])->assertForbidden();
    }

    public function test_header_nunca_muda_o_contexto(): void
    {
        $rota = $this->rotaDeContexto();
        $credencial = $this->emitir();
        $outro = Terminal::factory()->create();

        // Não basta recusar: nada do contexto pedido pode ter sido fixado.
        $this->requisicaoDeMaquina($rota, $credencial->plainTextToken, [
            'X-Tenant-ID' => $outro->tenant_id,
        ])->assertForbidden();

        $this->assertFalse(app(TerminalContext::class)->resolved());
        $this->assertFalse(app(TenantContext::class)->resolved());
    }

    public function test_user_nao_entra_em_rota_de_maquina(): void
    {
        $rota = $this->rotaDeContexto();
        $pessoa = $this->pessoaComVinculo();
        $token = $pessoa->createToken('auth_token', ['business:read', 'business:write']);

        $this->requisicaoDeMaquina($rota, $token->plainTextToken)->assertForbidden();
    }

    public function test_user_com_ability_de_maquina_adulterada_continua_recusado(): void
    {
        $rota = $this->rotaComAbility();
        $pessoa = $this->pessoaComVinculo();

        // Ability de máquina concedida artificialmente a uma pessoa: se fosse a
        // ability que barra, este caso passaria. É o tipo que barra.
        $token = $pessoa->createToken('auth_token', [MachineTokenAbility::TERMINAL_READ]);

        $this->requisicaoDeMaquina($rota, $token->plainTextToken)->assertForbidden();
    }

    public function test_terminal_nao_entra_em_rota_humana(): void
    {
        $credencial = $this->emitir();

        // Rota de negócio real, com o pipeline de produção
        // ['auth:sanctum', 'token.ability', 'tenant'].
        $this->requisicaoDeMaquina('/api/v1/products', $credencial->plainTextToken)
            ->assertForbidden();
    }

    public function test_terminal_com_abilities_humanas_adulteradas_continua_recusado_em_rota_humana(): void
    {
        // Credencial de Terminal com as abilities humanas: passa pelo
        // EnsureTokenAbility e chega ao TenantResolver, que recusa por tipo.
        // É o que prova que a fronteira humana não depende de ability.
        $terminal = $this->terminal->fresh();
        $token = $terminal->createToken('pdv-machine', ['business:read', 'business:write']);

        $this->requisicaoDeMaquina('/api/v1/products', $token->plainTextToken)
            ->assertForbidden();

        // E o TenantContext não ficou pela metade.
        $this->assertFalse(app(TenantContext::class)->resolved());
    }

    public function test_credencial_sem_a_ability_nao_acessa_rota_com_ability(): void
    {
        $rota = $this->rotaComAbility();

        $terminal = $this->terminal->fresh();
        $token = $terminal->createToken('pdv-machine', ['pdv:outra:coisa']);

        $this->requisicaoDeMaquina($rota, $token->plainTextToken)->assertForbidden();
    }

    public function test_credencial_com_a_ability_acessa_rota_com_ability(): void
    {
        $rota = $this->rotaComAbility();
        $credencial = $this->emitir();

        $this->requisicaoDeMaquina($rota, $credencial->plainTextToken)
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_requisicao_sem_credencial_nao_resolve_contexto(): void
    {
        $rota = $this->rotaDeContexto();

        $this->getJson($rota)->assertUnauthorized();
        $this->assertFalse(app(TerminalContext::class)->resolved());
    }

    public function test_terminal_sem_token_de_acesso_nao_passa_pela_ability(): void
    {
        $rota = $this->rotaComAbility();

        // Sujeito Terminal resolvido sem token de acesso na requisição — é o
        // que acontece se um caminho de autenticação futuro injetar o sujeito
        // sem credencial. Sem token não há ability a conferir, logo recusa.
        $this->actingAs($this->terminal->fresh(), 'sanctum');

        $this->getJson($rota)->assertForbidden();
    }

    private function pessoaComVinculo(): User
    {
        $terminal = $this->terminal->fresh();
        $pessoa = User::factory()->create(['status' => 'ACTIVE']);
        TenantUser::create([
            'tenant_id' => $terminal->tenant_id,
            'user_id' => $pessoa->id,
            'status' => TenantUser::STATUS_ACTIVE,
        ]);

        return $pessoa->fresh();
    }

    public function test_company_do_contexto_e_a_do_terminal_nao_outra(): void
    {
        $rota = $this->rotaDeContexto();
        $credencial = $this->emitir();

        // Uma segunda Company no mesmo tenant não pode influenciar o contexto.
        Company::factory()->create(['tenant_id' => $this->terminal->fresh()->tenant_id]);

        $this->requisicaoDeMaquina($rota, $credencial->plainTextToken)
            ->assertOk()
            ->assertJson(['company_id' => $this->terminal->fresh()->company_id]);
    }
}
