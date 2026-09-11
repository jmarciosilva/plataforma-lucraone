<?php

namespace Tests\Feature\Security;

use App\Modules\Authorization\Domain\AdminPermissionMatrix;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Concerns\MontaCenariosDeAutorizacao;
use Tests\TestCase;

/**
 * SEC-04 · E2 — pré-requisito da matriz dos papéis padrão.
 *
 * Baseline de caracterização: os testes descrevem a matriz SEGURA e falham
 * enquanto ela não existir.
 *
 * A contenção do E2 exige que quem atribui um papel domine as permissões dele.
 * Isso só permite os fluxos legítimos — admin gerindo manager e user, manager
 * gerindo user — se os conjuntos de permissões dos papéis padrão forem
 * aninhados. A propriedade protegida é a contenção entre conjuntos, não um
 * ranking pelo nome do papel nem a ausência de uma permissão específica.
 *
 * user e viewer não precisam estar contidos um no outro.
 */
#[Group('sec-04')]
#[Group('sec-04-e2')]
class StandardRoleMatrixSecurityTest extends TestCase
{
    use MontaCenariosDeAutorizacao, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create();
        $this->provisionarPapeisPadrao();
    }

    public static function papeisAninhados(): array
    {
        return [
            'manager ⊆ admin' => ['manager', 'admin'],
            'user ⊆ manager' => ['user', 'manager'],
            'user ⊆ admin' => ['user', 'admin'],
            'viewer ⊆ manager' => ['viewer', 'manager'],
            'viewer ⊆ admin' => ['viewer', 'admin'],
        ];
    }

    #[DataProvider('papeisAninhados')]
    public function test_permissoes_do_papel_estao_contidas_no_papel_que_o_domina(string $contido, string $dominante): void
    {
        $permissoesDoContido = $this->nomesDasPermissoes($contido);

        // Sem esta guarda, um papel vazio ou ausente passaria por vacuidade.
        $this->assertNotEmpty($permissoesDoContido, "pré-condição: o papel {$contido} precisa ter permissões");

        $foraDoDominante = array_values(array_diff($permissoesDoContido, $this->nomesDasPermissoes($dominante)));

        $this->assertSame(
            [],
            $foraDoDominante,
            "permissões de {$contido} fora de {$dominante}: ".implode(', ', $foraDoDominante)
        );
    }

    /**
     * A correção da matriz mexe nos papéis inferiores. O admin continua com a
     * matriz explícita de 26 permissões.
     */
    public function test_admin_mantem_exatamente_a_matriz_explicita_de_26_permissoes(): void
    {
        $esperadas = AdminPermissionMatrix::NAMES;
        sort($esperadas);

        $this->assertCount(26, AdminPermissionMatrix::NAMES);
        $this->assertSame($esperadas, $this->nomesDasPermissoes('admin'));
    }

    /**
     * Nomes das permissões do papel padrão no estabelecimento, em ordem.
     *
     * Linha e permissão precisam ser do mesmo estabelecimento do papel. A
     * ordenação é feita no PHP, com o mesmo critério da matriz esperada, e não
     * na colação do banco.
     */
    private function nomesDasPermissoes(string $papel): array
    {
        return $this->papel($this->tenant, $papel)
            ->permissionsForTenant()
            ->where('permissions.tenant_id', $this->tenant->id)
            ->pluck('permissions.name')
            ->sort()
            ->values()
            ->all();
    }
}
