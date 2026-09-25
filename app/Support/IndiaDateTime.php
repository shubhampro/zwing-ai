<?php

namespace App\Support;

use Carbon\CarbonInterface;

class IndiaDateTime
{
    public const TIMEZONE = 'Asia/Kolkata';

    public static function format(?CarbonInterface $date): ?string
    {
        if (! $date instanceof CarbonInterface) {
            return null;
        }

        return $date->copy()->timezone(self::TIMEZONE)->format('j M Y, h:i a').' IST';
    }

    public static function date(?CarbonInterface $date): ?string
    {
        if (! $date instanceof CarbonInterface) {
            return null;
        }

        return $date->copy()->timezone(self::TIMEZONE)->format('j M Y');
    }
}
