<?php

namespace App\Modules\Authorization\Application;

use RuntimeException;

/**
 * Recusa da contenção de delegação. A mensagem é exibida a quem executou.
 */
class PermissionDelegationDenied extends RuntimeException {}
