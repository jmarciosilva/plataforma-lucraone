<?php

namespace Tests\Unit\Pdv;

use App\Modules\Pdv\Domain\OperatorUsername;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Normalização do nome de usuário operacional.
 *
 * As duas formas têm papéis distintos e o teste separa os dois: a de EXIBIÇÃO é
 * o que o administrador vê e o operador reconhece; a CANÔNICA é o que garante
 * unicidade e o que o login procura. Confundi-las produziria ou cadastros
 * duplicados ou usernames que a pessoa não reconhece.
 */
#[Group('pdv-operator')]
class OperatorUsernameTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function formasDeExibicao(): array
    {
        return [
            'já normalizado' => ['marcio', 'marcio'],
            'maiúsculas' => ['MARCIO', 'marcio'],
            'espaços nas pontas' => ['  marcio  ', 'marcio'],
            'acento' => ['márcio', 'marcio'],
            'cedilha' => ['açucena', 'acucena'],
            'espaço interno vira ponto' => ['jose silva', 'jose.silva'],
            'separador repetido' => ['maria--jose', 'maria.jose'],
            'separador nas pontas' => ['-caixa01-', 'caixa01'],
            'dígitos preservados' => ['caixa01', 'caixa01'],
        ];
    }

    #[DataProvider('formasDeExibicao')]
    public function test_forma_de_exibicao(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, OperatorUsername::normalizar($entrada));
    }

    /**
     * Grafias que precisam colidir dentro de um estabelecimento.
     *
     * @return array<string, array{0: list<string>}>
     */
    public static function grafiasEquivalentes(): array
    {
        return [
            'ponto, sublinhado e junto' => [['jose.silva', 'jose_silva', 'josesilva', 'José Silva', 'JOSE-SILVA']],
            'caixa com e sem separador' => [['caixa.01', 'caixa_01', 'caixa01']],
        ];
    }

    /**
     * @param  list<string>  $grafias
     */
    #[DataProvider('grafiasEquivalentes')]
    public function test_grafias_equivalentes_tem_a_mesma_forma_canonica(array $grafias): void
    {
        $canonicas = array_map(fn (string $g) => OperatorUsername::canonical($g), $grafias);

        $this->assertCount(1, array_unique($canonicas), 'grafias equivalentes precisam colidir: '.implode(', ', $grafias));
    }

    /**
     * Dígito e letra parecida NÃO são unificados.
     *
     * `caixa0` e `caixao` são palavras diferentes, e tratá-las como iguais
     * recusaria cadastros legítimos. O custo de errar aqui é alto e o ganho é
     * pequeno — decisão registrada no domínio.
     */
    public function test_digito_e_letra_parecida_continuam_distintos(): void
    {
        $this->assertNotSame(
            OperatorUsername::canonical('caixa0'),
            OperatorUsername::canonical('caixao'),
        );
        $this->assertNotSame(
            OperatorUsername::canonical('l1lian'),
            OperatorUsername::canonical('lllian'),
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function aceitos(): array
    {
        return [
            'curto no limite' => ['abc'],
            'nome simples' => ['marcio'],
            'com ponto' => ['jose.silva'],
            'com sublinhado' => ['jose_silva'],
            'com hífen' => ['jose-silva'],
            'com dígitos' => ['caixa01'],
            'papel genérico' => ['gerente'],
            'no limite de tamanho' => ['abcdefghijklmnopqrstuvwxyz012345'],
        ];
    }

    #[DataProvider('aceitos')]
    public function test_aceita(string $valor): void
    {
        $this->assertTrue(OperatorUsername::valido($valor), "deveria aceitar: {$valor}");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function recusados(): array
    {
        return [
            'vazio' => [''],
            'só separadores' => ['...'],
            'curto demais' => ['ab'],
            'curto demais com separador' => ['a.b'],
            'longo demais' => ['abcdefghijklmnopqrstuvwxyz0123456'],
            'caractere proibido' => ['jose@silva'],
            'espaço no meio não resolvido' => ['jose!silva'],
            'reservado admin' => ['admin'],
            'reservado root' => ['root'],
            'reservado com separador' => ['sis.tema'],
            'reservado pdv' => ['PDV'],
            'reservado terminal' => ['terminal'],
        ];
    }

    #[DataProvider('recusados')]
    public function test_recusa(string $valor): void
    {
        $this->assertFalse(OperatorUsername::valido($valor), "deveria recusar: {$valor}");
    }

    /**
     * O tamanho mínimo é medido na forma canônica: `a.b` tem três caracteres na
     * tela e dois de verdade, e dois não identificam ninguém numa loja.
     */
    public function test_tamanho_minimo_e_medido_na_forma_canonica(): void
    {
        $this->assertSame('ab', OperatorUsername::canonical('a.b'));
        $this->assertFalse(OperatorUsername::valido('a.b'));
        $this->assertTrue(OperatorUsername::valido('a.bc'));
    }
}
