<?php

namespace App\Services;

use App\Enums\PayloadComposerSlotShape;
use App\Models\Organization;
use App\Models\PayloadComposer;
use App\Models\PayloadComposerSlot;
use App\Models\SavedSqlQuery;
use Illuminate\Validation\ValidationException;
use Throwable;

class PayloadComposerGenerator
{
    public function __construct(
        private readonly OrganizationDatabaseConnector $connector,
    ) {}

    public function maxRowsPerSlot(): int
    {
        return max(1, (int) config('payload-composer.max_rows_per_slot', 100_000));
    }

    /**
     * @param  array<string, mixed>  $scalars
     * @param  array<string, mixed>  $bindings
     * @return array{payload: array<string, mixed>, meta: array{row_counts: array<string, int>, organization_id: int, database: string}}
     */
    public function generate(
        PayloadComposer $composer,
        Organization $organization,
        array $scalars,
        array $bindings,
    ): array {
        $database = trim((string) ($organization->db_name ?? ''));

        if ($database === '') {
            throw ValidationException::withMessages([
                'organization_id' => 'Selected organization has no MySQL database name.',
            ]);
        }

        $composer->loadMissing(['slots.savedSqlQuery']);

        $payload = $this->buildScalarPayload($composer, $scalars);
        $rowCounts = [];

        $runtimeName = $this->connector->openMysqlSshDatabase($database);

        try {
            foreach ($composer->slots as $slot) {
                [$value, $count] = $this->runSlot($runtimeName, $slot, $bindings);
                $slotKey = trim((string) ($slot->key ?? ''));
                $metaKey = $slotKey !== '' ? $slotKey : '__root_'.$slot->id;

                if ($slotKey === '' && $slot->shape === PayloadComposerSlotShape::Object) {
                    // Empty-key object slots merge into root like scalars.
                    // No rows => null; skip merge instead of failing.
                    if (is_array($value)) {
                        $payload = array_merge($payload, $value);
                    }
                } elseif ($slotKey === '') {
                    throw ValidationException::withMessages([
                        'slots' => 'Array slots need a payload key. Only object slots may omit the key (merge like scalars).',
                    ]);
                } else {
                    $payload[$slotKey] = $value;
                }

                $rowCounts[$metaKey] = $count;
            }
        } finally {
            $this->connector->close($runtimeName);
        }

        return [
            'payload' => $payload,
            'meta' => [
                'row_counts' => $rowCounts,
                'organization_id' => $organization->id,
                'database' => $database,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $scalars
     * @return array<string, mixed>
     */
    private function buildScalarPayload(PayloadComposer $composer, array $scalars): array
    {
        $payload = [];

        foreach ($composer->scalars ?? [] as $definition) {
            $key = $definition['key'] ?? null;

            if (! is_string($key) || $key === '') {
                continue;
            }

            $hasInput = array_key_exists($key, $scalars);
            $value = $hasInput ? $scalars[$key] : ($definition['default'] ?? null);

            if (($definition['required'] ?? false) && ($value === null || $value === '')) {
                throw ValidationException::withMessages([
                    "scalars.{$key}" => "The {$key} scalar is required.",
                ]);
            }

            $payload[$key] = $value ?? '';
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $bindings
     * @return array{0: mixed, 1: int}
     */
    private function runSlot(string $runtimeName, PayloadComposerSlot $slot, array $bindings): array
    {
        $query = $slot->savedSqlQuery;

        if (! $query instanceof SavedSqlQuery) {
            throw ValidationException::withMessages([
                'slots' => "Slot {$slot->key} has no saved SQL query.",
            ]);
        }

        $slotLabel = trim((string) ($slot->key ?? '')) !== ''
            ? (string) $slot->key
            : 'root';

        $this->assertSafeSelect($query->sql, $slotLabel);

        foreach ($this->extractBindingNames($query->sql) as $name) {
            if (! array_key_exists($name, $bindings)) {
                throw ValidationException::withMessages([
                    "bindings.{$name}" => "Missing SQL binding: {$name}.",
                ]);
            }
        }

        [$sql, $positional] = $this->toPositional($query->sql, $bindings);

        $rows = [];
        $maxRows = $this->maxRowsPerSlot();

        try {
            $this->connector->eachRow($runtimeName, $sql, $positional, function (array $row) use (&$rows, $slotLabel, $maxRows): void {
                if (count($rows) >= $maxRows) {
                    throw ValidationException::withMessages([
                        'slots.'.$slotLabel => 'Slot '.$slotLabel.' exceeded the '.$maxRows.' row limit.',
                    ]);
                }

                $rows[] = $this->normalizeRow($row);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'slots.'.$slotLabel => 'Query failed for slot '.$slotLabel.': '.$exception->getMessage(),
            ]);
        }

        $count = count($rows);

        if ($slot->shape === PayloadComposerSlotShape::Object) {
            return [$rows[0] ?? null, $count];
        }

        return [$rows, $count];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }

    public function assertSafeSelect(string $sql, string $slotKey = 'sql'): void
    {
        $withoutLineComments = preg_replace('/--.*$/m', '', $sql) ?? '';
        $withoutBlockComments = preg_replace('/\/\*.*?\*\//s', '', $withoutLineComments) ?? '';
        $normalized = trim($withoutBlockComments);

        if ($normalized === '') {
            throw ValidationException::withMessages([
                'slots.'.$slotKey => 'SQL query is empty.',
            ]);
        }

        if (! preg_match('/^(with|select)\b/i', $normalized)) {
            throw ValidationException::withMessages([
                'slots.'.$slotKey => 'Only SELECT / WITH queries are allowed.',
            ]);
        }

        $withoutTrailingSemicolon = rtrim($normalized, " \t\n\r\0\x0B;");

        if (str_contains($withoutTrailingSemicolon, ';')) {
            throw ValidationException::withMessages([
                'slots.'.$slotKey => 'Multiple SQL statements are not allowed.',
            ]);
        }

        if (preg_match('/\b(insert|update|delete|drop|alter|truncate|create|grant|revoke|call|execute|copy|merge)\b/i', $withoutTrailingSemicolon) === 1) {
            throw ValidationException::withMessages([
                'slots.'.$slotKey => 'SQL contains a forbidden keyword.',
            ]);
        }
    }

    /**
     * @return list<string>
     */
    public function extractBindingNames(string $sql): array
    {
        preg_match_all('/(?<!:):([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches);

        /** @var list<string> $names */
        $names = array_values(array_unique($matches[1] ?? []));

        return $names;
    }

    /**
     * @param  array<string, mixed>  $named
     * @return array{0: string, 1: list<mixed>}
     */
    public function toPositional(string $sql, array $named): array
    {
        $bindings = [];

        $positionalSql = preg_replace_callback(
            '/(?<!:):([a-zA-Z_][a-zA-Z0-9_]*)/',
            function (array $match) use ($named, &$bindings): string {
                $key = $match[1];

                if (! array_key_exists($key, $named)) {
                    throw ValidationException::withMessages([
                        "bindings.{$key}" => "Missing SQL binding: {$key}.",
                    ]);
                }

                $bindings[] = $named[$key];

                return '?';
            },
            $sql,
        );

        return [(string) $positionalSql, $bindings];
    }
}
