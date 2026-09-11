<?php

namespace App\Modules\Authorization\Application;

use RuntimeException;

/**
 * Recusa da contenção de administração de usuários. A mensagem é genérica e pode
 * ser exibida a quem executou: não revela a autoridade de ninguém.
 */
class UserAdministrationDenied extends RuntimeException {}
