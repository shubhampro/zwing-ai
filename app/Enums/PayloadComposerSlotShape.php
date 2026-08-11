<?php

namespace App\Enums;

enum PayloadComposerSlotShape: string
{
    case Array = 'array';
    case Object = 'object';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
