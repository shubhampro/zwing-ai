<?php

namespace App\Support;

final class DatabaseHost
{
    /**
     * Normalize a DB host value to hostname only (no scheme/path/port).
     */
    public static function normalize(?string $host): ?string
    {
        if ($host === null) {
            return null;
        }

        $host = trim($host);

        if ($host === '') {
            return null;
        }

        if (! str_contains($host, '://')) {
            $host = preg_replace('#/.*$#', '', $host) ?? $host;
            $host = preg_replace('#:\d+$#', '', $host) ?? $host;

            return $host !== '' ? $host : null;
        }

        $parts = parse_url($host);

        if (! is_array($parts) || empty($parts['host'])) {
            $host = preg_replace('#^https?://#i', '', $host) ?? $host;
            $host = rtrim($host, '/');

            return $host !== '' ? $host : null;
        }

        return $parts['host'];
    }

    /**
     * Short masked host for UI (mostly asterisks, no full hostname).
     */
    public static function mask(?string $host): string
    {
        $host = self::normalize($host);

        if ($host === null) {
            return '****';
        }

        $length = strlen($host);

        if ($length <= 2) {
            return str_repeat('*', $length);
        }

        // Keep first + last char, middle as fixed short star run so label stays small.
        return $host[0].str_repeat('*', 8).$host[$length - 1];
    }
}
