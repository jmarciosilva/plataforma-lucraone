<?php

namespace App\Modules\Terminals\Domain;

/**
 * Abilities das credenciais de máquina (Terminal de PDV).
 *
 * Fonte separada do TokenAbility humano de propósito. O token humano responde
 * "este token pode ler ou escrever dados de negócio?"; o de máquina responde
 * "este Terminal pode consultar a si mesmo?". São eixos diferentes, e juntá-los
 * num único enum faria com que acrescentar uma ability de PDV ampliasse, por
 * descuido, o alcance de um token de pessoa — ou o contrário.
 *
 * O conjunto nasce com um único item porque existe um único contrato que o
 * consome. Abilities de venda, sincronização, estoque ou pagamento entram
 * quando as APIs correspondentes existirem, não antes: uma ability emitida sem
 * endpoint é alcance concedido sem revisão.
 *
 * Nunca `['*']`: com o coringa, qualquer ability criada depois passaria a
 * valer automaticamente para credenciais já distribuídas em campo.
 */
final class MachineTokenAbility
{
    /** Consultar o próprio Terminal e seus vínculos. */
    public const TERMINAL_READ = 'pdv:terminal:read';

    /**
     * Abilities de uma credencial de máquina recém-emitida.
     *
     * @return list<string>
     */
    public static function paraCredencialDeMaquina(): array
    {
        return [self::TERMINAL_READ];
    }
}
