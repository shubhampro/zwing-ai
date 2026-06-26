<?php

declare(strict_types=1);

/**
 * @return array<string, string>
 */
function loadProjectEnv(?string $projectRoot = null): array
{
    $root = $projectRoot ?? dirname(__DIR__, 4);
    $envPath = $root.'/.env';

    if (! is_readable($envPath)) {
        return [];
    }

    $variables = [];
    $lines = file($envPath, FILE_IGNORE_NEW_LINES);

    if ($lines === false) {
        return [];
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $variables[$key] = $value;
    }

    return $variables;
}

function envValue(string $key, ?string $default = null): ?string
{
    $value = getenv($key);

    if ($value !== false && $value !== '') {
        return $value;
    }

    static $fileEnv = null;

    if ($fileEnv === null) {
        $fileEnv = loadProjectEnv();
    }

    if (array_key_exists($key, $fileEnv) && $fileEnv[$key] !== '') {
        return $fileEnv[$key];
    }

    return $default;
}
