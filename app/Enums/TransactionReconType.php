<?php

namespace App\Enums;

enum TransactionReconType: string
{
    case Packet = 'packet';
    case Grn = 'grn';
    case Grt = 'grt';
    case Spt = 'spt';

    public function label(): string
    {
        return match ($this) {
            self::Packet => 'Packet',
            self::Grn => 'GRN',
            self::Grt => 'GRT',
            self::Spt => 'SPT',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
