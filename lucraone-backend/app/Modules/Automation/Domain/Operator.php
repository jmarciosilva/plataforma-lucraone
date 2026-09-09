<?php

namespace App\Modules\Automation\Domain;

/**
 * Operadores de comparação aceitos, por tipo de campo.
 *
 * Lista fechada: a condição da regra nunca vira expressão avaliada.
 */
class Operator
{
    public const IGUAL = 'igual';

    public const DIFERENTE = 'diferente';

    public const MENOR = 'menor';

    public const MENOR_IGUAL = 'menor_igual';

    public const MAIOR = 'maior';

    public const MAIOR_IGUAL = 'maior_igual';

    public const CONTEM = 'contem';

    public const NAO_CONTEM = 'nao_contem';

    private const ROTULOS = [
        self::IGUAL => 'é igual a',
        self::DIFERENTE => 'é diferente de',
        self::MENOR => 'é menor que',
        self::MENOR_IGUAL => 'é menor ou igual a',
        self::MAIOR => 'é maior que',
        self::MAIOR_IGUAL => 'é maior ou igual a',
        self::CONTEM => 'contém',
        self::NAO_CONTEM => 'não contém',
    ];

    private const POR_TIPO = [
        TriggerCatalog::TIPO_NUMERO => [
            self::IGUAL, self::DIFERENTE,
            self::MENOR, self::MENOR_IGUAL,
            self::MAIOR, self::MAIOR_IGUAL,
        ],
        TriggerCatalog::TIPO_TEXTO => [
            self::IGUAL, self::DIFERENTE,
            self::CONTEM, self::NAO_CONTEM,
        ],
        TriggerCatalog::TIPO_LISTA => [
            self::IGUAL, self::DIFERENTE,
        ],
    ];

    public static function paraTipo(string $tipo): array
    {
        return self::POR_TIPO[$tipo] ?? [];
    }

    public static function todos(): array
    {
        return array_keys(self::ROTULOS);
    }

    public static function rotulo(string $operador): string
    {
        return self::ROTULOS[$operador] ?? $operador;
    }

    public static function aceito(string $tipo, string $operador): bool
    {
        return in_array($operador, self::paraTipo($tipo), true);
    }

    /**
     * Rótulos agrupados por tipo — a tela usa para trocar o select de operador
     * conforme o campo escolhido.
     */
    public static function opcoesPorTipo(): array
    {
        $opcoes = [];

        foreach (self::POR_TIPO as $tipo => $operadores) {
            foreach ($operadores as $operador) {
                $opcoes[$tipo][$operador] = self::rotulo($operador);
            }
        }

        return $opcoes;
    }
}
