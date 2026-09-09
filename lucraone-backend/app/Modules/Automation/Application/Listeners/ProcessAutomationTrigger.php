<?php

namespace App\Modules\Automation\Application\Listeners;

use App\Modules\Automation\Application\RuleEngine;
use App\Modules\Automation\Domain\Events\AutomationTriggered;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Ponte entre o evento de negócio e o motor de regras.
 *
 * Roda na fila: enviar e-mail ou reprecificar não pode segurar a resposta de
 * quem só cadastrou um produto. Como na fila não há requisição, o contexto de
 * estabelecimento é restaurado a partir do tenant que viajou no evento — sem
 * isso, o escopo global de tenant não filtraria nada e as regras de um
 * estabelecimento veriam dados de outro.
 */
class ProcessAutomationTrigger implements ShouldQueue
{
    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        private TenantContext $context,
        private RuleEngine $motor,
    ) {}

    public function handle(AutomationTriggered $evento): void
    {
        $this->context->withTenant(
            $evento->tenantId,
            fn () => $this->motor->processar($evento->tenantId, $evento->trigger, $evento->payload)
        );
    }
}
