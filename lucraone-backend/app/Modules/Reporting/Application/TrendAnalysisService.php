<?php

namespace App\Modules\Reporting\Application;

use App\Modules\Reporting\Domain\ReportPeriod;

/**
 * Tendência de vendas e projeção simples do próximo período.
 *
 * A projeção é uma reta de mínimos quadrados sobre a série do período — não é
 * previsão estatística: não considera sazonalidade, feriado nem campanha. Serve
 * para responder "se continuar assim, dá quanto?", e é rotulada como estimativa
 * em toda a interface.
 */
class TrendAnalysisService
{
    public function __construct(
        private SalesReportService $vendas
    ) {}

    public function analisar(ReportPeriod $periodo, array $filtros = []): array
    {
        $serie = $this->vendas->serie($periodo, $filtros);
        $valores = array_map(static fn (array $balde) => $balde['faturamento'], $serie);

        $reta = $this->regressaoLinear($valores);

        return [
            'serie' => $serie,
            'media' => $valores === [] ? 0.0 : array_sum($valores) / count($valores),
            'melhor_balde' => $this->extremo($serie, 'max'),
            'pior_balde' => $this->extremo($serie, 'min'),
            'tendencia' => $this->direcao($reta['inclinacao']),
            'inclinacao' => $reta['inclinacao'],
            'projecao_proximo_periodo' => $reta['projecao'],
            'crescimento_percentual' => $this->crescimento($valores),
            'confiavel' => count($valores) >= 3,
        ];
    }

    /**
     * Mínimos quadrados sobre os índices da série.
     *
     * Com menos de dois pontos não existe reta: devolve inclinação zero e repete
     * o único valor conhecido, em vez de inventar uma tendência.
     */
    private function regressaoLinear(array $valores): array
    {
        $n = count($valores);

        if ($n === 0) {
            return ['inclinacao' => 0.0, 'projecao' => 0.0];
        }

        if ($n === 1) {
            return ['inclinacao' => 0.0, 'projecao' => (float) $valores[0]];
        }

        $somaX = 0.0;
        $somaY = 0.0;
        $somaXY = 0.0;
        $somaX2 = 0.0;

        foreach ($valores as $i => $y) {
            $somaX += $i;
            $somaY += $y;
            $somaXY += $i * $y;
            $somaX2 += $i * $i;
        }

        $denominador = ($n * $somaX2) - ($somaX * $somaX);

        if ($denominador == 0.0) {
            return ['inclinacao' => 0.0, 'projecao' => $somaY / $n];
        }

        $inclinacao = (($n * $somaXY) - ($somaX * $somaY)) / $denominador;
        $intercepto = ($somaY - ($inclinacao * $somaX)) / $n;

        // Faturamento não fica negativo: uma reta em queda vira zero, não dívida.
        $projecao = max(0.0, $intercepto + ($inclinacao * $n));

        return [
            'inclinacao' => round($inclinacao, 2),
            'projecao' => round($projecao, 2),
        ];
    }

    /**
     * Compara a segunda metade da série com a primeira.
     */
    private function crescimento(array $valores): ?float
    {
        $n = count($valores);

        if ($n < 2) {
            return null;
        }

        $meio = (int) floor($n / 2);
        $primeira = array_slice($valores, 0, $meio);
        $segunda = array_slice($valores, $meio);

        $somaPrimeira = array_sum($primeira);
        $somaSegunda = array_sum($segunda);

        if ($somaPrimeira <= 0.0) {
            return $somaSegunda > 0.0 ? null : 0.0;
        }

        return round((($somaSegunda - $somaPrimeira) / $somaPrimeira) * 100, 1);
    }

    private function direcao(float $inclinacao): string
    {
        return match (true) {
            $inclinacao > 0.01 => 'alta',
            $inclinacao < -0.01 => 'queda',
            default => 'estavel',
        };
    }

    private function extremo(array $serie, string $tipo): ?array
    {
        if ($serie === []) {
            return null;
        }

        $ordenada = collect($serie)->sortBy('faturamento');

        return $tipo === 'max' ? $ordenada->last() : $ordenada->first();
    }
}
