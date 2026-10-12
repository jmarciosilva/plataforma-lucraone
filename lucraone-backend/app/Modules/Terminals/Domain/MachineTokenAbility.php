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
 * Cada ability existe porque existe um contrato que a consome. Abilities de
 * venda, sincronização, estoque ou pagamento entram quando as APIs
 * correspondentes existirem, não antes: uma ability emitida sem endpoint é
 * alcance concedido sem revisão.
 *
 * Nunca `['*']`: com o coringa, qualquer ability criada depois passaria a
 * valer automaticamente para credenciais já distribuídas em campo.
 */
final class MachineTokenAbility
{
    /** Consultar o próprio Terminal e seus vínculos. */
    public const TERMINAL_READ = 'pdv:terminal:read';

    public const CREDENTIAL_RENEW = 'pdv:credential:renew';

    public const CREDENTIAL_CONFIRM = 'pdv:credential:confirm';

    /**
     * Autenticar um operador e manter a sessão operacional dele neste Terminal.
     *
     * Alcance de MÁQUINA, não de pessoa: ela diz que este Terminal pode abrir o
     * fluxo de login do caixa, e não que alguém está autorizado a operar. Quem
     * responde isso é a credencial do operador somada à atribuição de filial e à
     * permissão `access-pdv`. Nenhuma capacidade comercial vem daqui.
     */
    public const OPERATOR_SESSION = 'pdv:operator-session';

    /** Consultar somente o catálogo vinculado à empresa do Terminal. */
    public const CATALOG_READ = 'pdv:catalog:read';

    /**
     * Abilities de uma credencial de máquina operacional.
     *
     * Um único conjunto para a emissão inicial (pairing, administração) e para a
     * promoção da candidata na confirmação de rotação. Antes havia dois: a
     * emissão nascia com `[pdv:terminal:read]` e a promoção ampliava para
     * renovação e confirmação. Manter a divergência agora significaria que um
     * Terminal recém-pareado não poderia autenticar operador até rotacionar —
     * e a rotação dele só funciona hoje por uma exceção de transição, pensada
     * para credenciais que já estão em campo, não para credenciais novas.
     *
     * Nunca `['*']`: com o coringa, qualquer ability criada depois passaria a
     * valer automaticamente para credenciais já distribuídas em campo.
     *
     * @return list<string>
     */
    public static function paraCredencialDeMaquina(): array
    {
        return [self::TERMINAL_READ, self::CREDENTIAL_RENEW, self::CREDENTIAL_CONFIRM, self::OPERATOR_SESSION, self::CATALOG_READ];
    }
}
