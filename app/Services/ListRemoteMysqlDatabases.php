<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class ListRemoteMysqlDatabases
{
    private const array EXCLUDED = [
        'information_schema',
        'mysql',
        'performance_schema',
        'sys',
    ];

    /**
     * @return list<string>
     *
     * @throws Throwable
     */
    public function __invoke(string $connectionSlug): array
    {
        $rows = DB::connection($connectionSlug)->select('SHOW DATABASES');

        return collect($rows)
            ->map(function (object $row): ?string {
                foreach (get_object_vars($row) as $value) {
                    if (is_string($value) && $value !== '') {
                        return $value;
                    }
                }

                return null;
            })
            ->filter()
            ->reject(fn (string $name): bool => in_array($name, self::EXCLUDED, true))
            ->sort()
            ->values()
            ->all();
    }
}
