<?php

namespace App\Modules\Pdv\Domain;

use Illuminate\Support\Str;

/**
 * O nome de usuário operacional do caixa.
 *
 * É um identificador de trabalho, não a identidade da pessoa: `marcio`,
 * `caixa01`, `gerente`. Quem digita está de pé, com fila na frente, às vezes num
 * teclado de membrana — então a regra é toda voltada a perdoar a digitação sem
 * abrir ambiguidade.
 *
 * Duas formas, de propósito:
 *
 *   - `normalizar()` devolve a forma de EXIBIÇÃO: minúscula, sem espaços nas
 *     pontas, sem acento. É o que o administrador vê e o que o PDV lista;
 *   - `canonical()` devolve a forma de COMPARAÇÃO: a de exibição sem `.`, `_`
 *     e `-`. É ela que garante unicidade no estabelecimento e é por ela que o
 *     login procura.
 *
 * O efeito combinado é que `José_Silva`, `jose.silva` e `josesilva` são a MESMA
 * pessoa dentro de um estabelecimento: não podem ser cadastradas duas vezes, e
 * qualquer uma das grafias entra na conta certa.
 *
 * O que NÃO é unificado: dígito com letra parecida. `caixa0` e `caixao`
 * continuam distintos, porque `0` e `o` são escolhas legítimas de palavras
 * diferentes e tratá-las como iguais recusaria cadastros razoáveis — o custo de
 * errar aqui é alto e o ganho é pequeno.
 *
 * A unicidade é por estabelecimento. Dois clientes do LucraOne podem ter cada
 * um o seu `marcio`, e nenhum consegue descobrir a existência do outro: o login
 * resolve o username dentro do tenant do Terminal autenticado, nunca num espaço
 * global.
 */
final class OperatorUsername
{
    public const MIN = 3;

    public const MAX = 32;

    /**
     * Nomes que não podem ser usados.
     *
     * Não é censura: são rótulos que um cliente de PDV ou um log poderiam
     * confundir com um estado em vez de uma pessoa.
     *
     * @var list<string>
     */
    public const RESERVADOS = ['admin', 'root', 'system', 'sistema', 'null', 'none', 'nenhum', 'pdv', 'terminal'];

    /**
     * Forma de exibição: minúscula, sem acento, sem espaço nas pontas.
     *
     * Espaço interno vira `.` em vez de ser removido: quem digita "jose silva"
     * quase sempre quer um separador, e removê-lo silenciosamente produziria um
     * username que a pessoa não reconhece.
     */
    public static function normalizar(string $valor): string
    {
        $texto = Str::lower(trim(Str::ascii($valor)));
        $texto = (string) preg_replace('/\s+/', '.', $texto);

        // Separadores repetidos ou nas pontas são erro de digitação, não escolha.
        $texto = (string) preg_replace('/[._-]{2,}/', '.', $texto);

        return trim($texto, '._-');
    }

    /**
     * Forma de comparação. Nunca exibida, nunca gravada como username.
     */
    public static function canonical(string $valor): string
    {
        return str_replace(['.', '_', '-'], '', self::normalizar($valor));
    }

    /**
     * A forma de exibição é aceitável?
     *
     * Letras, dígitos e os três separadores, começando e terminando em letra ou
     * dígito. O tamanho é medido na forma CANÔNICA: `a.b` tem três caracteres de
     * exibição e dois de verdade, e dois caracteres não identificam ninguém numa
     * loja com dez funcionários.
     */
    public static function valido(string $valor): bool
    {
        $normalizado = self::normalizar($valor);
        $canonico = self::canonical($valor);

        if (preg_match('/\A[a-z0-9]([a-z0-9._-]*[a-z0-9])?\z/', $normalizado) !== 1) {
            return false;
        }

        if (strlen($normalizado) > self::MAX || strlen($canonico) < self::MIN) {
            return false;
        }

        return ! in_array($canonico, self::RESERVADOS, true);
    }
}
