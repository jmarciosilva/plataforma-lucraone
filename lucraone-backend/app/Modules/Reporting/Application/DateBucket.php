<?php

namespace App\Modules\Reporting\Application;

use App\Modules\Reporting\Domain\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Traduz granularidade de relatório em SQL de agrupamento por data.
 *
 * MySQL roda em desenvolvimento e produção; SQLite roda nos testes. As funções
 * de data dos dois não coincidem, então a expressão é montada por driver — e a
 * semana é sempre identificada pela sua segunda-feira, para que os dois deem
 * exatamente o mesmo balde.
 */
class DateBucket
{
    /**
     * Expressão SQL que reduz a coluna ao balde do período.
     */
    public function expressao(string $coluna, ReportPeriod $periodo): string
    {
        $deslocada = $this->comFuso($coluna, $periodo->offsetMinutos);

        return match ($periodo->granularidade) {
            ReportPeriod::DIA => $this->formato($deslocada, '%Y-%m-%d'),
            ReportPeriod::MES => $this->formato($deslocada, '%Y-%m'),
            ReportPeriod::ANO => $this->formato($deslocada, '%Y'),
            ReportPeriod::SEMANA => $this->segundaFeira($deslocada),
            default => throw new RuntimeException("granularidade não suportada: {$periodo->granularidade}"),
        };
    }

    /**
     * Todos os baldes do período, inclusive os sem venda.
     *
     * Sem isso o gráfico mostraria só os dias com movimento, e uma semana parada
     * pareceria uma semana inexistente.
     */
    public function baldes(ReportPeriod $periodo): array
    {
        $baldes = [];
        $cursor = $this->inicioDoBalde($periodo->inicio, $periodo->granularidade);

        while ($cursor->lessThanOrEqualTo($periodo->fim)) {
            $baldes[$this->chave($cursor, $periodo->granularidade)] = $this->rotulo($cursor, $periodo->granularidade);
            $cursor = $this->proximo($cursor, $periodo->granularidade);
        }

        return $baldes;
    }

    public function rotulo(CarbonImmutable $data, string $granularidade): string
    {
        return match ($granularidade) {
            ReportPeriod::DIA => $data->format('d/m'),
            ReportPeriod::SEMANA => 'sem. '.$data->format('d/m'),
            ReportPeriod::MES => $data->format('m/Y'),
            ReportPeriod::ANO => $data->format('Y'),
            default => $data->toDateString(),
        };
    }

    private function chave(CarbonImmutable $data, string $granularidade): string
    {
        return match ($granularidade) {
            ReportPeriod::DIA, ReportPeriod::SEMANA => $data->format('Y-m-d'),
            ReportPeriod::MES => $data->format('Y-m'),
            ReportPeriod::ANO => $data->format('Y'),
            default => $data->toDateString(),
        };
    }

    private function inicioDoBalde(CarbonImmutable $data, string $granularidade): CarbonImmutable
    {
        return match ($granularidade) {
            ReportPeriod::DIA => $data->startOfDay(),
            ReportPeriod::SEMANA => $data->startOfWeek(CarbonImmutable::MONDAY),
            ReportPeriod::MES => $data->startOfMonth(),
            ReportPeriod::ANO => $data->startOfYear(),
            default => $data->startOfDay(),
        };
    }

    private function proximo(CarbonImmutable $data, string $granularidade): CarbonImmutable
    {
        return match ($granularidade) {
            ReportPeriod::DIA => $data->addDay(),
            ReportPeriod::SEMANA => $data->addWeek(),
            ReportPeriod::MES => $data->addMonth(),
            ReportPeriod::ANO => $data->addYear(),
            default => $data->addDay(),
        };
    }

    private function comFuso(string $coluna, int $offsetMinutos): string
    {
        if ($offsetMinutos === 0) {
            return $coluna;
        }

        return match ($this->driver()) {
            'sqlite' => "datetime({$coluna}, '{$offsetMinutos} minutes')",
            default => "DATE_ADD({$coluna}, INTERVAL {$offsetMinutos} MINUTE)",
        };
    }

    private function formato(string $expressao, string $formato): string
    {
        return match ($this->driver()) {
            'sqlite' => "strftime('{$formato}', {$expressao})",
            default => "DATE_FORMAT({$expressao}, '{$formato}')",
        };
    }

    /**
     * Segunda-feira da semana da data — mesmo resultado nos dois drivers.
     *
     * No SQLite, 'weekday 0' avança até o domingo daquela semana e voltar 6 dias
     * chega na segunda. No MySQL, WEEKDAY() já conta a partir de segunda.
     */
    private function segundaFeira(string $expressao): string
    {
        return match ($this->driver()) {
            'sqlite' => "date({$expressao}, 'weekday 0', '-6 days')",
            default => "DATE_FORMAT(DATE_SUB({$expressao}, INTERVAL WEEKDAY({$expressao}) DAY), '%Y-%m-%d')",
        };
    }

    private function driver(): string
    {
        return DB::connection()->getDriverName();
    }
}
