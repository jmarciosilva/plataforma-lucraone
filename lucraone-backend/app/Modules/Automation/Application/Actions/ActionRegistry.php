<?php

namespace App\Modules\Automation\Application\Actions;

use App\Modules\Automation\Domain\TriggerCatalog;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Catálogo de ações disponíveis.
 *
 * Como o TriggerCatalog faz com os gatilhos, aqui a lista é fechada: a regra
 * guarda uma chave, nunca um nome de classe vindo do banco.
 */
class ActionRegistry
{
    private const HANDLERS = [
        CreateNotificationAction::CHAVE => CreateNotificationAction::class,
        SendEmailAction::CHAVE => SendEmailAction::class,
        UpdatePriceAction::CHAVE => UpdatePriceAction::class,
    ];

    public function __construct(
        private Container $container
    ) {}

    public function resolver(string $chave): ActionHandler
    {
        if (! isset(self::HANDLERS[$chave])) {
            throw new InvalidArgumentException("ação desconhecida: {$chave}");
        }

        return $this->container->make(self::HANDLERS[$chave]);
    }

    public static function chaves(): array
    {
        return array_keys(self::HANDLERS);
    }

    public static function existe(string $chave): bool
    {
        return isset(self::HANDLERS[$chave]);
    }

    public static function opcoes(): array
    {
        return collect(self::HANDLERS)
            ->map(fn (string $classe) => $classe::rotulo())
            ->all();
    }

    public static function rotulo(string $chave): string
    {
        return isset(self::HANDLERS[$chave]) ? self::HANDLERS[$chave]::rotulo() : $chave;
    }

    public static function regrasDeConfiguracao(string $chave): array
    {
        return isset(self::HANDLERS[$chave]) ? self::HANDLERS[$chave]::regrasDeConfiguracao() : [];
    }

    /**
     * Ações que só funcionam com gatilho que carrega produto.
     */
    public static function exigeProduto(string $chave): bool
    {
        return $chave === UpdatePriceAction::CHAVE;
    }

    public static function compativel(string $acao, string $gatilho): bool
    {
        return ! self::exigeProduto($acao)
            || in_array($gatilho, TriggerCatalog::comProduto(), true);
    }
}
