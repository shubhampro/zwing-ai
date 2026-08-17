<?php

namespace App\Enums;

enum TransactionReconType: string
{
    case Packet = 'packet';
    case Grn = 'grn';
    case Grt = 'grt';
    case Spt = 'spt';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Packet => 'Packet',
            self::Grn => 'GRN',
            self::Grt => 'GRT',
            self::Spt => 'SPT',
            self::Cash => 'CASH',
        };
    }

    public function usesCashColumns(): bool
    {
        return $this === self::Cash;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
