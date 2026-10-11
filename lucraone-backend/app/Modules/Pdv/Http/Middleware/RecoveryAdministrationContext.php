<?php

namespace App\Modules\Pdv\Http\Middleware;

/**
 * Porta humana da recuperação administrativa de credencial de máquina.
 *
 * A regra é a mesma de toda administração do PDV e vive no
 * HumanAdministrationContext, compartilhado com a administração das atribuições
 * de operador. A classe permanece com nome próprio porque é ela que está
 * declarada na rota de recuperação e documentada no contrato — duplicar a
 * verificação para manter o nome criaria duas versões de uma checagem de
 * segurança, que divergiriam no primeiro ajuste.
 */
final class RecoveryAdministrationContext extends HumanAdministrationContext {}
