<?php

namespace App\Modules\Reporting\Domain;

use Carbon\CarbonImmutable;

/**
 * Recorte de tempo de um relatório: início, fim e granularidade do agrupamento.
 *
 * Guarda também o deslocamento do fuso do estabelecimento. Os timestamps são
 * gravados em UTC, então sem o deslocamento uma venda às 22h em Brasília cairia
 * no dia seguinte do relatório.
 */
final class ReportPeriod
{
    public const DIA = 'day';

    public const SEMANA = 'week';

    public const MES = 'month';

    public const ANO = 'year';

    public const GRANULARIDADES = [self::DIA, self::SEMANA, self::MES, self::ANO];

    public function __construct(
        public readonly CarbonImmutable $inicio,
        public readonly CarbonImmutable $fim,
        public readonly string $granularidade,
        public readonly int $offsetMinutos = 0,
    ) {}

    /**
     * Monta o período a partir da entrada do usuário, com padrão de 30 dias.
     *
     * O fuso entra como nome IANA (ex.: America/Sao_Paulo) e vira minutos fixos.
     * Regiões que ainda praticam horário de verão terão erro de uma hora nas
     * datas de virada — o Brasil não pratica desde 2019.
     */
    public static function fromInput(
        ?string $inicio = null,
        ?string $fim = null,
        ?string $granularidade = null,
        string $timezone = 'UTC',
    ): self {
        $granularidade = in_array($granularidade, self::GRANULARIDADES, true)
            ? $granularidade
            : self::DIA;

        $fimResolvido = $fim
            ? CarbonImmutable::parse($fim)->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        $inicioResolvido = $inicio
            ? CarbonImmutable::parse($inicio)->startOfDay()
            : $fimResolvido->subDays(29)->startOfDay();

        // Entrada invertida não é erro do usuário que valha um 422: só ordena.
        if ($inicioResolvido->greaterThan($fimResolvido)) {
            [$inicioResolvido, $fimResolvido] = [$fimResolvido->startOfDay(), $inicioResolvido->endOfDay()];
        }

        return new self(
            $inicioResolvido,
            $fimResolvido,
            $granularidade,
            self::offsetEmMinutos($timezone, $fimResolvido),
        );
    }

    /**
     * Dias no intervalo, contando as duas pontas.
     *
     * O fim é sempre fim-do-dia, então a diferença vem quebrada (9,99…) e o
     * piso é o número inteiro de dias completos entre as datas.
     */
    public function dias(): int
    {
        return (int) floor($this->inicio->diffInDays($this->fim)) + 1;
    }

    /**
     * Período imediatamente anterior, de mesmo tamanho — base das comparações.
     */
    public function anterior(): self
    {
        $dias = $this->dias();

        return new self(
            $this->inicio->subDays($dias)->startOfDay(),
            $this->inicio->subDay()->endOfDay(),
            $this->granularidade,
            $this->offsetMinutos,
        );
    }

    public function rotulo(): string
    {
        return $this->inicio->format('d/m/Y').' a '.$this->fim->format('d/m/Y');
    }

    public function toArray(): array
    {
        return [
            'inicio' => $this->inicio->toDateString(),
            'fim' => $this->fim->toDateString(),
            'granularidade' => $this->granularidade,
            'dias' => $this->dias(),
        ];
    }

    private static function offsetEmMinutos(string $timezone, CarbonImmutable $referencia): int
    {
        try {
            return $referencia->setTimezone($timezone)->getOffset() / 60;
        } catch (\Throwable) {
            return 0;
        }
    }
}
