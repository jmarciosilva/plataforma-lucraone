<?php

namespace App\Modules\Pdv\Domain;

use SensitiveParameter;

/**
 * A credencial opaca da sessão operacional.
 *
 * Trinta e dois bytes de CSPRNG em 64 caracteres hexadecimais minúsculos. Não
 * carrega estrutura: não é JWT, não embute o id da sessão, não é derivada de
 * senha e não diz nada sobre quem a possui. Quem quer saber a que sessão ela
 * pertence precisa do banco, e o banco só tem o SHA-256.
 *
 * Deliberadamente SEM selector público. O código de pareamento e o de
 * recuperação têm selector porque precisam ser lidos e digitados por uma
 * pessoa, e o selector é o que permite achar a linha sem comparar segredos em
 * tempo variável. Aqui o portador é um programa: a busca é pelo hash do valor
 * inteiro, que é um índice único, e não sobra nenhuma parte pública para vazar
 * em log de proxy ou em URL.
 *
 * SHA-256 e não bcrypt/argon porque o segredo é de alta entropia, gerado pelo
 * servidor: não há dicionário a resistir, e o custo de um hash lento entraria
 * em TODA requisição autenticada do caixa. É o mesmo raciocínio — e o mesmo
 * algoritmo — que o Sanctum usa nos PATs.
 */
final class OperatorSessionCredential
{
    private const BYTES = 32;

    public static function gerar(): string
    {
        return bin2hex(random_bytes(self::BYTES));
    }

    public static function hash(#[SensitiveParameter] string $credencial): string
    {
        return hash('sha256', $credencial);
    }

    /**
     * O valor tem a forma de uma credencial desta família?
     *
     * Filtro de forma, não de autenticidade: evita consultar o banco com
     * qualquer lixo que chegue no cabeçalho, e dá a mesma resposta pública para
     * valor malformado e para valor inexistente.
     */
    public static function bemFormada(#[SensitiveParameter] mixed $credencial): bool
    {
        return is_string($credencial)
            && strlen($credencial) === self::BYTES * 2
            && ctype_xdigit($credencial)
            && strtolower($credencial) === $credencial;
    }
}
