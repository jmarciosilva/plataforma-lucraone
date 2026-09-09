<?php

namespace App\Modules\Automation\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento único de entrada do motor de automação.
 *
 * Products, Inventory e Sales dependem desta classe e do TriggerCatalog — e de
 * mais nada do módulo. Eles anunciam que algo aconteceu, com o payload já
 * achatado nos campos que o catálogo declara, e não sabem que existem regras,
 * condições ou ações. Um evento por gatilho, em vez de uma classe por fato,
 * mantém essa superfície de acoplamento em um ponto só.
 *
 * O tenant viaja no evento porque o processamento é assíncrono: na fila não
 * existe requisição, e portanto não existe contexto de estabelecimento
 * resolvido — quem executa precisa restaurá-lo.
 */
class AutomationTriggered
{
    use Dispatchable;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $trigger,
        public readonly array $payload,
    ) {}
}
