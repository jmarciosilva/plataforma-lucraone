<?php

namespace App\Modules\Automation\Domain;

/**
 * Destinatários de e-mail de uma regra de automação.
 *
 * Existe porque o mesmo texto precisa ser interpretado em dois lugares: na
 * validação da configuração, para recusar cedo, e na execução da ação, para
 * não confiar em JSON que já está no banco. Sem isto, as duas leituras
 * divergiriam com o tempo.
 *
 * `max:1000` caracteres continua na validação como freio barato, mas nunca foi
 * um limite semântico: cabem **143** endereços mínimos em mil caracteres. Quem
 * limita de verdade é `MAXIMO`.
 */
final class EmailRecipients
{
    /**
     * Destinatários por regra.
     *
     * Automação aqui é notificação operacional — avisar o comprador, o
     * estoquista, o contador —, não campanha. Dez cobre com folga uma equipe
     * envolvida em um processo e mantém o alcance de uma configuração indevida
     * pequeno.
     */
    public const MAXIMO = 10;

    /** Mensagem preexistente; mantida para não mudar o contrato do log. */
    public const MOTIVO_VAZIO = 'nenhum destinatário válido configurado na regra.';

    public const MOTIVO_INVALIDOS = 'a regra tem destinatário com endereço inválido; nenhum e-mail foi enviado.';

    public const MOTIVO_EXCEDE_MAXIMO = 'a regra tem mais destinatários do que o permitido; nenhum e-mail foi enviado.';

    /**
     * @param  list<string>  $validos
     * @param  list<string>  $invalidos
     */
    private function __construct(
        private array $validos,
        private array $invalidos,
    ) {}

    /**
     * Interpreta o campo `recipients`: aceita vírgula, ponto e vírgula e
     * quebra de linha, descarta espaço em volta e entradas vazias, normaliza a
     * caixa e remove repetições.
     *
     * A caixa é normalizada porque na prática `A@Casa.test` e `a@casa.test`
     * são a mesma caixa postal — sem isso a pessoa receberia duas cópias e
     * ainda consumiria duas vagas do limite.
     */
    public static function deConfiguracao(?string $bruto): self
    {
        $itens = collect(preg_split('/[,;\r\n]+/', (string) $bruto))
            ->map(fn (string $item) => trim($item))
            ->reject(fn (string $item) => $item === '');

        $validos = $itens
            ->filter(fn (string $item) => filter_var($item, FILTER_VALIDATE_EMAIL) !== false)
            ->map(fn (string $item) => mb_strtolower($item))
            ->unique()
            ->values()
            ->all();

        $invalidos = $itens
            ->filter(fn (string $item) => filter_var($item, FILTER_VALIDATE_EMAIL) === false)
            ->values()
            ->all();

        return new self($validos, $invalidos);
    }

    /** @return list<string> */
    public function lista(): array
    {
        return $this->validos;
    }

    public function quantidade(): int
    {
        return count($this->validos);
    }

    public function vazio(): bool
    {
        return $this->validos === [];
    }

    public function temInvalidos(): bool
    {
        return $this->invalidos !== [];
    }

    public function excedeMaximo(): bool
    {
        return $this->quantidade() > self::MAXIMO;
    }

    /**
     * Por que esta lista não pode ser usada, ou null se pode.
     *
     * Endereço inválido recusa a lista inteira em vez de ser descartado em
     * silêncio: antes, um erro de digitação no meio da lista fazia a
     * notificação simplesmente não chegar a essa pessoa, sem ninguém saber.
     * Falhar com motivo registrado é o que dá chance de corrigir.
     */
    public function motivoDeRecusa(): ?string
    {
        return match (true) {
            // Nenhum endereço aproveitável: mantém a mensagem antiga, que é a
            // descrição exata deste caso.
            $this->vazio() => self::MOTIVO_VAZIO,
            $this->temInvalidos() => self::MOTIVO_INVALIDOS,
            $this->excedeMaximo() => self::MOTIVO_EXCEDE_MAXIMO,
            default => null,
        };
    }
}
