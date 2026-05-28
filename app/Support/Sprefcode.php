<?php

namespace App\Support;

class Sprefcode
{
    /**
     * Extract numeric suffix from sprefcode (matches stock CSV parsing).
     * e.g. "NDPL010-2" → 2, "42" → 42, "ABC-XYZ-5" → 5.
     */
    public static function parse(string $value): int
    {
        $parts = explode('-', trim($value));
        $last = trim((string) end($parts));

        return is_numeric($last) ? (int) $last : 0;
    }

    public static function matches(string $stored, string $needle): bool
    {
        return self::parse($stored) === self::parse($needle);
    }
}
