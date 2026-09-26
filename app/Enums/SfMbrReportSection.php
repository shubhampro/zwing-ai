<?php

namespace App\Enums;

enum SfMbrReportSection: string
{
    case SegregateAccounts = 'segregate_accounts';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::SegregateAccounts => 'Segregate Accounts',
        };
    }
}
