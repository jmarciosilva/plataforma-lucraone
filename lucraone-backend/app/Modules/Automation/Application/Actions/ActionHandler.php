<?php

namespace App\Modules\Automation\Application\Actions;

use App\Modules\Automation\Domain\Models\AutomationRule;

interface ActionHandler
{
    /**
     * Executa a ação e devolve o que foi feito, para o log de auditoria.
     *
     * Deve lançar InvalidArgumentException quando a configuração da regra não
     * serve para este payload — o motor grava como falha e segue para a
     * próxima regra, sem derrubar as outras.
     */
    public function executar(AutomationRule $regra, array $payload): array;

    /**
     * Regras de validação da configuração desta ação, para o formulário.
     */
    public static function regrasDeConfiguracao(): array;

    public static function rotulo(): string;
}
