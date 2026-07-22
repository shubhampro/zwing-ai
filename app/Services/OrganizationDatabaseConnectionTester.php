<?php

namespace App\Services;

use App\Models\OrganizationDatabaseConnection;
use Throwable;

class OrganizationDatabaseConnectionTester
{
    public function __construct(private OrganizationDatabaseConnector $connector) {}

    /**
     * @return array{ok: bool, message: string, latency_ms: int|null}
     */
    public function test(OrganizationDatabaseConnection $connection): array
    {
        $runtimeName = 'org_db_test_'.$connection->id;
        $started = hrtime(true);

        try {
            $this->connector->open($connection, $runtimeName);
            $this->connector->connection($runtimeName)->select('select 1 as ok');

            return [
                'ok' => true,
                'message' => __('Connection successful for :type.', [
                    'type' => $connection->type->value,
                ]),
                'latency_ms' => $this->latencyMs($started),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => __('Connection failed: :error', [
                    'error' => $this->safeErrorMessage($e->getMessage()),
                ]),
                'latency_ms' => $this->latencyMs($started),
            ];
        } finally {
            $this->connector->close($runtimeName);
        }
    }

    private function safeErrorMessage(string $message): string
    {
        $message = preg_replace('/Database:\s*[^,]*/i', 'Database: [hidden]', $message) ?? $message;

        return $message;
    }

    private function latencyMs(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
