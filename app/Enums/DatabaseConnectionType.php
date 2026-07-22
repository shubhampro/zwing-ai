<?php

namespace App\Enums;

enum DatabaseConnectionType: string
{
    case Mysql = 'mysql';
    case Pgsql = 'pgsql';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
