<?php

namespace App\Enums;

enum SfMbrSlaPriority: string
{
    case Urgent = 'Urgent';
    case High = 'High';
    case Medium = 'Medium';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function hours(): int
    {
        return match ($this) {
            self::Urgent => 24,
            self::High => 48,
            self::Medium => 120,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Urgent => 'Urgent (24 Hrs)',
            self::High => 'High (48 Hrs)',
            self::Medium => 'Medium (120 Hrs)',
        };
    }
}
