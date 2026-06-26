<?php

namespace App\Support;

class InvoiceRefId
{
    /** @var non-empty-string */
    public const SEPARATOR = '-';

    /**
     * Normalize a hyphen-separated Mop Ref id string (e.g. "22-21" → "22-21").
     */
    public static function normalize(string $refId): string
    {
        $ids = array_values(array_filter(
            array_map('trim', explode(self::SEPARATOR, $refId)),
            fn (string $id) => $id !== '',
        ));

        return implode(self::SEPARATOR, $ids);
    }

    /**
     * @return array<int, string>
     */
    public static function parts(string $refId): array
    {
        $normalized = self::normalize($refId);

        if ($normalized === '') {
            return [];
        }

        return explode(self::SEPARATOR, $normalized);
    }

    public static function isValid(string $refId): bool
    {
        return self::normalize($refId) !== '';
    }

    /**
     * @return array<int, string>
     */
    public static function uniqueParts(string $refId): array
    {
        return array_values(array_unique(self::parts($refId)));
    }
}
