<?php

namespace App\Modules\Menu\Domain;

final class ModifierGroupType
{
    public const ADDITIONAL = 'ADDITIONAL';

    public const REMOVAL = 'REMOVAL';

    public const CHOICE = 'CHOICE';

    public const LABELS = [
        self::ADDITIONAL => 'adicional',
        self::REMOVAL => 'remoção',
        self::CHOICE => 'escolha',
    ];

    public const ALL = [self::ADDITIONAL, self::REMOVAL, self::CHOICE];

    public static function isValid(string $type): bool
    {
        return in_array($type, self::ALL, true);
    }
}
