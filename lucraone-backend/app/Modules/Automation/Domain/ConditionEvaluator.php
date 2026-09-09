<?php

namespace App\Modules\Automation\Domain;

/**
 * Avalia as condições de uma regra contra o payload do gatilho.
 *
 * Todas as condições precisam bater (E lógico). Não há OU nem parênteses: a
 * regra que precisa disso são duas regras. Manter uma condição sem precedência
 * é o que permite que a tela seja um formulário e não um editor de expressão.
 */
class ConditionEvaluator
{
    /**
     * @param  array  $condicoes  lista de ['campo' => , 'operador' => , 'valor' => ]
     * @param  array  $payload  dados do gatilho, já achatados
     */
    public function satisfaz(string $gatilho, array $condicoes, array $payload): bool
    {
        foreach ($condicoes as $condicao) {
            if (! $this->condicaoBate($gatilho, $condicao, $payload)) {
                return false;
            }
        }

        return true;
    }

    private function condicaoBate(string $gatilho, array $condicao, array $payload): bool
    {
        $campo = $condicao['campo'] ?? null;
        $operador = $condicao['operador'] ?? null;

        // Campo fora do catálogo não é avaliado: a regra não pode alcançar
        // dado que o gatilho não declarou expor.
        if (! $campo || ! TriggerCatalog::campoExiste($gatilho, $campo)) {
            return false;
        }

        $tipo = TriggerCatalog::tipoDoCampo($gatilho, $campo);

        if (! $operador || ! Operator::aceito($tipo, $operador)) {
            return false;
        }

        $atual = $payload[$campo] ?? null;
        $esperado = $condicao['valor'] ?? null;

        return $tipo === TriggerCatalog::TIPO_NUMERO
            ? $this->compararNumero((float) $atual, $operador, (float) $esperado)
            : $this->compararTexto((string) $atual, $operador, (string) $esperado);
    }

    private function compararNumero(float $atual, string $operador, float $esperado): bool
    {
        return match ($operador) {
            Operator::IGUAL => abs($atual - $esperado) < 0.00001,
            Operator::DIFERENTE => abs($atual - $esperado) >= 0.00001,
            Operator::MENOR => $atual < $esperado,
            Operator::MENOR_IGUAL => $atual <= $esperado,
            Operator::MAIOR => $atual > $esperado,
            Operator::MAIOR_IGUAL => $atual >= $esperado,
            default => false,
        };
    }

    /**
     * Texto compara sem diferenciar maiúscula e acento não normalizado seria
     * surpresa para o operador — mas comparar "Café" com "cafe" também seria.
     * Fica em minúsculas, sem mexer em acento.
     */
    private function compararTexto(string $atual, string $operador, string $esperado): bool
    {
        $atual = mb_strtolower(trim($atual));
        $esperado = mb_strtolower(trim($esperado));

        return match ($operador) {
            Operator::IGUAL => $atual === $esperado,
            Operator::DIFERENTE => $atual !== $esperado,
            Operator::CONTEM => $esperado !== '' && str_contains($atual, $esperado),
            Operator::NAO_CONTEM => $esperado === '' || ! str_contains($atual, $esperado),
            default => false,
        };
    }
}
