<?php

namespace Tests\Integration\Pdv;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Application\OperatorBranchAccess;
use App\Modules\Pdv\Application\OperatorRegistry;
use App\Modules\Pdv\Application\OperatorSessionLifecycle;
use App\Modules\Pdv\Domain\Exceptions\OperatorSessionFailed;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Pdv\Domain\Models\PdvOperatorProfile;
use App\Modules\Pdv\Domain\Models\PdvOperatorSession;
use App\Modules\Pdv\Domain\OperatorPermission;
use App\Modules\Pdv\Domain\OperatorSessionCredential;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Terminals\Application\IssueTerminalMachineCredential;
use App\Modules\Terminals\Application\TerminalCredentialLifecycle;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Integration\ConcurrencyTestCase;

/**
 * Concorrência da sessão operacional, contra MySQL real e descartável.
 *
 * A invariante que estes testes protegem é "uma sessão ativa por caixa", e ela
 * não pode ser garantida por índice: o MySQL 8 não tem índice único parcial, e
 * um único em (terminal_id, status) impediria dois turnos ENDED no mesmo caixa.
 * O que garante é o lock no Terminal — e lock só se prova com processos e
 * conexões independentes disputando de verdade.
 *
 * SQLite em memória não serve como evidência aqui, e por isso esta suíte vive
 * no runner `phpunit.mysql-lifecycle.xml`, que só roda no ambiente descartável.
 */
class OperatorSessionConcurrencyTest extends ConcurrencyTestCase
{
    use DatabaseMigrations;

    private const SENHA = 'segredo-de-teste-123';

    private Terminal $terminal;

    private PersonalAccessToken $maquina;

    private User $operador;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pdv-lifecycle-mysql', config('database.connections.mysql.host'));
        $this->assertSame('pdv_lifecycle_test', DB::connection()->getDatabaseName());

        $this->terminal = Terminal::factory()->create([
            'status' => Terminal::STATUS_ACTIVE,
            'installation_id' => (string) Str::uuid(),
        ]);
        $this->terminal->tenant->update(['status' => 'ACTIVE', 'active' => true]);
        $this->branch = Branch::withoutGlobalScopes()->findOrFail($this->terminal->branch_id);

        app(ProvisionarEstabelecimento::class)->provisionarMatriz($this->terminal->tenant);

        app(IssueTerminalMachineCredential::class)->issue($this->terminal);
        $this->maquina = $this->terminal->tokens()->sole();

        $this->operador = $this->operadorAutorizado('primeiro');

        // O contexto da requisição não existe num teste de serviço; deixá-lo
        // resolvido fixaria o TenantScope e esconderia o filtro explícito que
        // os serviços aplicam.
        app(TenantContext::class)->clear();
    }

    /**
     * Dois operadores, um caixa, mesmo instante: exatamente um entra.
     */
    public function test_dois_operadores_simultaneos_produzem_uma_unica_sessao(): void
    {
        $this->operadorAutorizado('segundo');

        $resultados = $this->race([
            fn () => $this->iniciar('primeiro'),
            fn () => $this->iniciar('segundo'),
        ]);

        $sucessos = array_values(array_filter($resultados, fn ($r) => $r['ok']));
        $recusas = array_values(array_filter($resultados, fn ($r) => ! $r['ok']));

        $this->assertCount(1, $sucessos);
        $this->assertCount(1, $recusas);
        $this->assertSame(OperatorSessionFailed::class, $recusas[0]['type']);

        $this->assertSame(1, PdvOperatorSession::withoutGlobalScopes()->count());
        $this->assertSame(1, PdvOperatorSession::withoutGlobalScopes()
            ->where('status', PdvOperatorSession::STATUS_ACTIVE)->count());
    }

    /**
     * Mesmo operador, dois logins simultâneos: uma sessão só.
     *
     * É o caso da resposta perdida com retry agressivo. As duas chamadas podem
     * devolver credencial — a rotação é serializada —, mas o banco guarda um
     * único hash, então exatamente uma delas autentica depois.
     */
    public function test_recuperacao_concorrente_do_mesmo_operador_nao_duplica_sessao(): void
    {
        $resultados = $this->race([
            fn () => $this->iniciar('primeiro'),
            fn () => $this->iniciar('primeiro'),
        ]);

        foreach ($resultados as $resultado) {
            $this->assertTrue($resultado['ok'], 'O mesmo operador não deveria ser recusado no próprio caixa.');
        }

        $this->assertSame(1, PdvOperatorSession::withoutGlobalScopes()->count());

        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();
        $validas = array_filter(
            $resultados,
            fn ($r) => hash_equals($sessao->credential_hash, $r['result']['credential_hash']),
        );

        // Uma credencial vale; a outra foi rotacionada para fora.
        $this->assertCount(1, $validas);
        $this->assertSame(1, count(array_unique(array_column(array_column($resultados, 'result'), 'session_id'))));
    }

    /**
     * Revogar a filial no instante do login: nunca sobra sessão ativa com
     * atribuição revogada.
     */
    public function test_revogacao_da_filial_durante_o_login_nao_deixa_sessao_ativa_indevida(): void
    {
        $admin = $this->administrador();

        $this->race([
            fn () => $this->iniciar('primeiro'),
            fn () => $this->revogar($admin),
        ]);

        $atribuicao = PdvOperatorBranchAssignment::withoutGlobalScopes()
            ->where('user_id', $this->operador->id)
            ->sole();

        if ($atribuicao->status === PdvOperatorBranchAssignment::STATUS_REVOKED) {
            $this->assertSame(0, PdvOperatorSession::withoutGlobalScopes()
                ->where('status', PdvOperatorSession::STATUS_ACTIVE)
                ->count());
        }

        // Em qualquer ordem, nunca duas sessões.
        $this->assertLessThanOrEqual(1, PdvOperatorSession::withoutGlobalScopes()->count());
    }

    /**
     * Logout e revogação administrativa ao mesmo tempo: um estado final, não
     * dois, e nenhuma sessão ativa sobrando.
     */
    public function test_logout_e_revogacao_simultaneos_convergem_para_um_estado(): void
    {
        $credencial = $this->abrirSessao();
        $admin = $this->administrador();

        $this->race([
            fn () => $this->encerrar($credencial),
            fn () => $this->revogar($admin),
        ]);

        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();

        $this->assertContains($sessao->status, [
            PdvOperatorSession::STATUS_ENDED,
            PdvOperatorSession::STATUS_REVOKED,
        ]);
        $this->assertFalse($sessao->isActive());
    }

    /**
     * Revalidar enquanto a sessão é encerrada: uma das duas vence, e a sessão
     * termina encerrada — revalidar não ressuscita nada.
     */
    public function test_revalidacao_concorrente_com_encerramento_nao_mantem_a_sessao_ativa(): void
    {
        $credencial = $this->abrirSessao();

        $this->race([
            fn () => $this->revalidarSessao($credencial),
            fn () => $this->encerrar($credencial),
        ]);

        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();

        $this->assertSame(PdvOperatorSession::STATUS_ENDED, $sessao->status);
        // O prazo absoluto não se move, nem com revalidação no meio.
        $this->assertFalse($sessao->isActive());
    }

    /**
     * Rotação da credencial de máquina concorrente com o uso da sessão.
     *
     * A inversão deliberada da regra anterior: a rotação de rotina PRESERVA o
     * turno. Em MySQL real, com processos independentes, o que importa é que a
     * troca seja atômica — não pode existir instante em que a credencial nova já
     * vale e a sessão ainda aponta para a antiga, nem o contrário.
     *
     * Qualquer ordem é aceitável; o estado final, não. No fim tem de haver UMA
     * sessão, ativa, apontando para o PAT vigente, com uma rotação contada e com
     * a jornada preservada.
     */
    public function test_rotacao_da_maquina_durante_o_uso_preserva_a_sessao(): void
    {
        $credencial = $this->abrirSessao();
        $antes = PdvOperatorSession::withoutGlobalScopes()->sole();
        $operacao = (string) Str::uuid();
        $segredo = bin2hex(random_bytes(32));

        $this->race([
            fn () => $this->revalidarSessao($credencial),
            fn () => $this->rotacionar($operacao, $segredo),
        ]);

        $promovido = $this->terminal->tokens()->sole();
        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();

        $this->assertSame(1, PdvOperatorSession::withoutGlobalScopes()->count());
        $this->assertSame(PdvOperatorSession::STATUS_ACTIVE, $sessao->status);
        $this->assertSame((int) $promovido->id, (int) $sessao->machine_token_id);
        $this->assertSame(1, $sessao->machine_rotations);
        // A jornada não é reiniciada por uma renovação técnica.
        $this->assertTrue($sessao->authenticated_at->equalTo($antes->authenticated_at));

        // E a sessão continua autenticável sob a credencial promovida.
        $this->assertSame(
            PdvOperatorSession::STATUS_ACTIVE,
            app(OperatorSessionLifecycle::class)
                ->authenticate($this->terminal->fresh(), $promovido, $credencial)
                ->status,
        );
    }

    /**
     * Duas rotações concorrentes com um turno aberto.
     *
     * Uma vence e a outra encontra a pendência resolvida. O turno não pode ser
     * contado duas vezes nem ficar apontando para um PAT que já não existe.
     */
    public function test_rotacoes_concorrentes_nao_duplicam_a_contagem_do_turno(): void
    {
        $this->abrirSessao();
        $segredo = bin2hex(random_bytes(32));

        $this->race([
            fn () => $this->rotacionar((string) Str::uuid(), $segredo),
            fn () => $this->rotacionar((string) Str::uuid(), $segredo),
        ]);

        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();
        $vigente = $this->terminal->tokens()->first();

        $this->assertNotNull($vigente);
        $this->assertLessThanOrEqual(1, $sessao->machine_rotations);
        $this->assertSame((int) $vigente->id, (int) $sessao->machine_token_id);
    }

    /**
     * Desativar o operador enquanto a sessão dele está em uso.
     *
     * Em qualquer ordem, o estado final não pode ser "perfil inativo com sessão
     * ativa" — a combinação que deixaria um funcionário afastado operando.
     */
    public function test_desativacao_concorrente_com_uso_nao_deixa_sessao_ativa(): void
    {
        $credencial = $this->abrirSessao();
        $admin = $this->administrador();

        $this->race([
            fn () => $this->revalidarSessao($credencial),
            fn () => $this->desativar($admin),
        ]);

        $perfil = PdvOperatorProfile::withoutGlobalScopes()
            ->where('user_id', $this->operador->id)
            ->sole();
        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();

        $this->assertSame(PdvOperatorProfile::STATUS_INACTIVE, $perfil->status);
        $this->assertFalse($sessao->isActive(), 'perfil inativo não pode conviver com sessão ativa.');
    }

    /**
     * Dois cadastros concorrentes com usernames equivalentes.
     *
     * `jose.silva` e `josesilva` têm a MESMA forma canônica, então o índice
     * único por estabelecimento tem de recusar o segundo — e é o MySQL real que
     * prova isso, porque o SQLite da suíte padrão não exercita a corrida.
     */
    public function test_usernames_equivalentes_concorrentes_criam_um_unico_perfil(): void
    {
        $admin = $this->administrador();

        $resultados = $this->race([
            fn () => $this->cadastrar($admin, 'jose.silva'),
            fn () => $this->cadastrar($admin, 'josesilva'),
        ]);

        $sucessos = array_filter($resultados, fn ($r) => $r['ok']);

        $this->assertCount(1, $sucessos);
        $this->assertSame(1, PdvOperatorProfile::withoutGlobalScopes()
            ->where('username_canonical', 'josesilva')
            ->count());
    }

    // ----------------------------------------------------------------
    // Trabalhos executados nos processos filhos.
    // ----------------------------------------------------------------

    /**
     * @return array{session_id: string, credential_hash: string}
     */
    private function iniciar(string $username): array
    {
        $resultado = app(OperatorSessionLifecycle::class)->start(
            $this->terminal->fresh(),
            $this->maquina,
            $username,
            self::SENHA,
        );

        // Nunca devolve a credencial em claro pelo socket.
        return [
            'session_id' => $resultado->session->id,
            'credential_hash' => OperatorSessionCredential::hash((string) $resultado->credencial),
        ];
    }

    private function encerrar(string $credencial): bool
    {
        app(OperatorSessionLifecycle::class)->end($this->terminal->fresh(), $credencial);

        return true;
    }

    private function revalidarSessao(string $credencial): string
    {
        return app(OperatorSessionLifecycle::class)
            ->revalidate($this->terminal->fresh(), $this->maquina, $credencial)
            ->status;
    }

    private function desativar(User $admin): string
    {
        app(TenantContext::class)->set($this->terminal->tenant_id);

        $perfil = PdvOperatorProfile::withoutGlobalScopes()
            ->where('user_id', $this->operador->id)
            ->sole();

        return app(OperatorRegistry::class)
            ->atualizarPerfil($admin->fresh(), $perfil, $perfil->username, PdvOperatorProfile::STATUS_INACTIVE)
            ->status;
    }

    private function cadastrar(User $admin, string $username): string
    {
        app(TenantContext::class)->set($this->terminal->tenant_id);

        // Com e-mail de propósito: o `down()` da migration que tornou
        // `users.email` opcional recusa reverter enquanto houver identidade sem
        // e-mail, e o DatabaseMigrations desta suíte faz rollback no teardown.
        // Este teste é sobre unicidade de username, não sobre e-mail ausente —
        // esse caso é coberto na suíte administrativa.
        return app(OperatorRegistry::class)->criar($admin->fresh(), $this->terminal->tenant_id, [
            'name' => 'Operador '.$username,
            'username' => $username,
            'password' => self::SENHA,
            'email' => $username.'@concorrencia.test',
            'branch_ids' => [$this->terminal->branch_id],
        ])->username;
    }

    private function revogar(User $admin): string
    {
        app(TenantContext::class)->set($this->terminal->tenant_id);

        return app(OperatorBranchAccess::class)
            ->revoke($admin->fresh(), $this->operador->fresh(), $this->branch->fresh())
            ->status;
    }

    private function rotacionar(string $operacao, string $segredo): string
    {
        $lifecycle = app(TerminalCredentialLifecycle::class);
        $preparo = $lifecycle->prepare($this->terminal->fresh(), $this->maquina, $operacao, $segredo);
        [$id] = explode('|', (string) $preparo->credential);
        $candidata = PersonalAccessToken::findOrFail($id);

        return $lifecycle->confirm($this->terminal->fresh(), $candidata, $operacao)->operation->status;
    }

    // ----------------------------------------------------------------
    // Cenário.
    // ----------------------------------------------------------------

    private function abrirSessao(): string
    {
        $credencial = OperatorSessionCredential::gerar();

        PdvOperatorSession::create([
            'tenant_id' => $this->terminal->tenant_id,
            'company_id' => $this->terminal->company_id,
            'branch_id' => $this->terminal->branch_id,
            'terminal_id' => $this->terminal->id,
            'installation_id' => $this->terminal->installation_id,
            'user_id' => $this->operador->id,
            'machine_token_id' => $this->maquina->id,
            'credential_hash' => OperatorSessionCredential::hash($credencial),
            'status' => PdvOperatorSession::STATUS_ACTIVE,
            'authenticated_at' => now(),
            'last_revalidated_at' => now(),
            'expires_at' => now()->addMinutes((int) config('pdv.operator_sessions.sliding_minutes')),
            'absolute_expires_at' => null,
        ]);

        return $credencial;
    }

    private function operadorAutorizado(string $username): User
    {
        $tenant = $this->terminal->tenant;

        $operador = User::factory()->create(['password' => self::SENHA]);
        $operador->joinTenant($tenant->id, TenantUser::STATUS_ACTIVE);

        $perfil = new PdvOperatorProfile([
            'tenant_id' => $tenant->id,
            'user_id' => $operador->id,
            'status' => PdvOperatorProfile::STATUS_ACTIVE,
        ]);
        $perfil->id = (string) Str::ulid();
        $perfil->definirUsername($username);
        $perfil->save();

        $papel = Role::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'pdv-operator'],
            ['id' => (string) Str::ulid(), 'description' => 'Operador de caixa']
        );
        $papel->grantPermission(Permission::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('name', OperatorPermission::ACESSO)
            ->firstOrFail());
        $operador->assignRole($papel, $tenant->id);

        PdvOperatorBranchAssignment::create([
            'tenant_id' => $tenant->id,
            'user_id' => $operador->id,
            'branch_id' => $this->terminal->branch_id,
            'status' => PdvOperatorBranchAssignment::STATUS_ACTIVE,
        ]);

        return $operador->fresh();
    }

    private function administrador(): User
    {
        $admin = User::factory()->create();
        app(ProvisionarEstabelecimento::class)->atribuirAdministrador($this->terminal->tenant, $admin);

        return $admin->fresh();
    }
}
