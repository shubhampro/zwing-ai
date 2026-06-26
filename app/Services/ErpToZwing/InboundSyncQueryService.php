<?php

namespace App\Services\ErpToZwing;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\UTCDateTime;
use RuntimeException;
use Throwable;

class InboundSyncQueryService
{
    /**
     * @return array{
     *     result: array<int, array<string, mixed>>,
     *     stats: array{total_sync: int, success_sync: int, pending: int, remain_sync: int, sync_percentage: float}
     * }
     */
    public function fetch(
        int $vendorId,
        Carbon $startDate,
        Carbon $endDate,
        ?string $clientId = null,
        ?string $clientEventName = null,
        ?string $clientEventUniqueCode = null,
    ): array {
        $this->ensureConfigured();

        $fields = config('inbound_sync.fields');
        $collectionName = (string) config('inbound_sync.collection');
        $connectionName = (string) config('inbound_sync.connection');

        $vendorField = (string) $fields['vendor_id'];
        $clientIdField = (string) $fields['client_id'];
        $eventField = (string) $fields['event_name'];
        $uniqueCodeField = (string) $fields['event_unique_code'];
        $statusField = (string) $fields['status'];
        $statusFallbackField = (string) ($fields['status_fallback'] ?? 'xstatus');
        $createdAtField = (string) $fields['created_at'];
        $eventTimeField = (string) ($fields['event_time'] ?? 'client_event_time');

        $startUtc = new UTCDateTime($startDate->copy()->utc()->getTimestampMs());
        $endUtc = new UTCDateTime($endDate->copy()->utc()->getTimestampMs());
        $dateRange = ['$gte' => $startUtc, '$lte' => $endUtc];

        $match = [
            '$and' => [
                [
                    '$or' => [
                        [$vendorField => $vendorId],
                        [$vendorField => (string) $vendorId],
                    ],
                ],
                [
                    '$or' => [
                        [$createdAtField => $dateRange],
                        [$eventTimeField => $dateRange],
                    ],
                ],
            ],
        ];

        if ($clientId !== null && $clientId !== '') {
            $match['$and'][] = [$clientIdField => $clientId];
        }

        if ($clientEventName !== null && $clientEventName !== '') {
            $match['$and'][] = [$eventField => $clientEventName];
        }

        if ($clientEventUniqueCode !== null && $clientEventUniqueCode !== '') {
            $uniqueMatchers = [
                [$uniqueCodeField => $clientEventUniqueCode],
            ];

            if (ctype_digit($clientEventUniqueCode)) {
                $uniqueMatchers[] = [$uniqueCodeField => (int) $clientEventUniqueCode];
            }

            $match['$and'][] = ['$or' => $uniqueMatchers];
        }

        $statusBranches = $this->statusBranches($statusField, $statusFallbackField);

        $pipeline = [
            ['$match' => $match],
            [
                '$addFields' => [
                    '_status_value' => [
                        '$ifNull' => ['$'.$statusField, '$'.$statusFallbackField],
                    ],
                ],
            ],
            [
                '$addFields' => [
                    '_sync_status' => [
                        '$switch' => [
                            'branches' => $statusBranches,
                            'default' => 'pending',
                        ],
                    ],
                    '_document_id' => ['$toString' => '$_id'],
                ],
            ],
            [
                '$group' => [
                    '_id' => '$'.$eventField,
                    'trans' => ['$sum' => 1],
                    'success_sync' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$_sync_status', 'success']],
                                1,
                                0,
                            ],
                        ],
                    ],
                    'fail_cnt' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$_sync_status', 'failed']],
                                1,
                                0,
                            ],
                        ],
                    ],
                    'pending' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$_sync_status', 'pending']],
                                1,
                                0,
                            ],
                        ],
                    ],
                    'need_to_sync' => [
                        '$push' => [
                            '$cond' => [
                                ['$eq' => ['$_sync_status', 'failed']],
                                '$_document_id',
                                '$$REMOVE',
                            ],
                        ],
                    ],
                    'event_miss' => [
                        '$push' => [
                            '$cond' => [
                                ['$eq' => ['$_sync_status', 'pending']],
                                '$_document_id',
                                '$$REMOVE',
                            ],
                        ],
                    ],
                    'failed_details' => [
                        '$push' => [
                            '$cond' => [
                                ['$eq' => ['$_sync_status', 'failed']],
                                [
                                    'id' => '$_document_id',
                                    'client_event_unique_code' => '$'.$uniqueCodeField,
                                    'request' => '$request',
                                    'response' => '$response',
                                ],
                                '$$REMOVE',
                            ],
                        ],
                    ],
                    'pending_details' => [
                        '$push' => [
                            '$cond' => [
                                ['$eq' => ['$_sync_status', 'pending']],
                                [
                                    'id' => '$_document_id',
                                    'client_event_unique_code' => '$'.$uniqueCodeField,
                                    'request' => '$request',
                                    'response' => '$response',
                                ],
                                '$$REMOVE',
                            ],
                        ],
                    ],
                ],
            ],
            ['$sort' => ['_id' => 1]],
        ];

        try {
            $connection = DB::connection($connectionName);
            $collection = $connection->getCollection($collectionName);
            $rows = iterator_to_array($collection->aggregate($pipeline, [
                'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
                'allowDiskUse' => true,
                'maxTimeMS' => 120000,
            ]));
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'MongoDB inbound sync query failed: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        $idLimit = (int) config('inbound_sync.id_list_limit', 500);

        $result = collect($rows)
            ->map(function (array $row) use ($idLimit): array {
                $name = (string) ($row['_id'] ?? 'unknown');
                $trans = (int) ($row['trans'] ?? 0);
                $successSync = (int) ($row['success_sync'] ?? 0);
                $failCnt = (int) ($row['fail_cnt'] ?? 0);
                $pending = (int) ($row['pending'] ?? 0);
                $needToSync = array_values(array_slice(
                    array_map('strval', $row['need_to_sync'] ?? []),
                    0,
                    $idLimit,
                ));
                $eventMiss = array_values(array_slice(
                    array_map('strval', $row['event_miss'] ?? []),
                    0,
                    $idLimit,
                ));
                $failedDetails = array_values(array_slice(
                    array_map(
                        fn (array $detail): array => $this->normalizeDocumentDetail($detail),
                        $row['failed_details'] ?? [],
                    ),
                    0,
                    $idLimit,
                ));
                $pendingDetails = array_values(array_slice(
                    array_map(
                        fn (array $detail): array => $this->normalizeDocumentDetail($detail),
                        $row['pending_details'] ?? [],
                    ),
                    0,
                    $idLimit,
                ));

                return [
                    'name' => $name,
                    'trans' => $trans,
                    'val' => $successSync,
                    'fail_cnt' => $failCnt,
                    'pending' => $pending,
                    'success_sync' => $successSync,
                    'need_to_sync' => $needToSync,
                    'event_miss' => $eventMiss,
                    'failed_details' => $failedDetails,
                    'pending_details' => $pendingDetails,
                    'need_to_sync_count' => $failCnt,
                    'event_miss_count' => $pending,
                ];
            })
            ->values()
            ->all();

        $totalSync = (int) collect($result)->sum('trans');
        $successSync = (int) collect($result)->sum('success_sync');
        $pendingTotal = (int) collect($result)->sum('pending');

        return [
            'result' => $result,
            'stats' => [
                'total_sync' => $totalSync,
                'success_sync' => $successSync,
                'pending' => $pendingTotal,
                'remain_sync' => max($totalSync - $successSync, 0),
                'sync_percentage' => $totalSync > 0
                    ? round(($successSync / $totalSync) * 100, 2)
                    : 0.0,
            ],
        ];
    }

    public function isConfigured(): bool
    {
        $connection = config('database.connections.mongodb_ssh', []);

        return is_array($connection)
            && ($connection['database'] ?? '') !== ''
            && ($connection['database'] ?? null) !== null;
    }

    /**
     * @return list<array{case: array<mixed>, then: string}>
     */
    private function statusBranches(string $statusField, string $statusFallbackField): array
    {
        $branches = [];

        foreach (config('inbound_sync.status', []) as $bucket => $values) {
            $normalized = collect($values)
                ->flatMap(fn (string $value) => [$value, strtolower($value), strtoupper($value)])
                ->unique()
                ->values()
                ->all();

            $branches[] = [
                'case' => ['$in' => ['$_status_value', $normalized]],
                'then' => $bucket,
            ];
        }

        foreach ([$statusField, $statusFallbackField] as $field) {
            $branches[] = [
                'case' => ['$eq' => ['$'.$field, true]],
                'then' => 'success',
            ];
            $branches[] = [
                'case' => ['$eq' => ['$'.$field, false]],
                'then' => 'failed',
            ];
        }

        $branches[] = [
            'case' => ['$eq' => ['$response_status_code', 200]],
            'then' => 'success',
        ];
        $branches[] = [
            'case' => ['$gte' => ['$response_status_code', 400]],
            'then' => 'failed',
        ];

        return $branches;
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array{id: string, client_event_unique_code: string|null, request: mixed, response: mixed}
     */
    private function normalizeDocumentDetail(array $detail): array
    {
        $uniqueCode = $detail['client_event_unique_code'] ?? null;

        return [
            'id' => (string) ($detail['id'] ?? ''),
            'client_event_unique_code' => $uniqueCode !== null && $uniqueCode !== ''
                ? (string) $uniqueCode
                : null,
            'request' => $this->parseJsonField($detail['request'] ?? null),
            'response' => $this->parseJsonField($detail['response'] ?? null),
        ];
    }

    private function parseJsonField(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        $decoded = json_decode($trimmed, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $this->normalizeNestedJson($decoded);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeNestedJson(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $trimmed = trim($value);

                if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                    $nested = json_decode($trimmed, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($nested)) {
                        $data[$key] = $this->normalizeNestedJson($nested);

                        continue;
                    }
                }
            }

            if (is_array($value)) {
                $data[$key] = $this->normalizeNestedJson($value);
            }
        }

        return $data;
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'MongoDB is not configured. Set MONGODB_SSH_DATABASE (or MONGO_DB_DATABASE) in .env.',
            );
        }

        if ((string) config('inbound_sync.collection', '') === '') {
            throw new RuntimeException('Inbound sync collection is not configured. Set INBOUND_SYNC_COLLECTION in .env.');
        }
    }
}
