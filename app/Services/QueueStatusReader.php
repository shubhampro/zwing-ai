<?php

namespace App\Services;

use App\Support\ExternalQueryQueue;
use Illuminate\Support\Facades\Queue;
use Throwable;

class QueueStatusReader
{
    /**
     * Lightweight queue lengths for the sidebar (all authenticated users).
     *
     * @return array{
     *     available: bool,
     *     connection: string,
     *     queues: array<string, array{pending: int, processing: int}>,
     *     waiting: int,
     *     processing: int
     * }
     */
    public function snapshot(): array
    {
        $connection = (string) config('queue.default');

        try {
            $queue = Queue::connection($connection);

            $queues = [];
            $waiting = 0;
            $processing = 0;

            foreach (['default', ExternalQueryQueue::NAME] as $name) {
                $pending = (int) $queue->pendingSize($name);
                $reserved = (int) $queue->reservedSize($name);

                $queues[$name] = [
                    'pending' => $pending,
                    'processing' => $reserved,
                ];

                $waiting += $pending;
                $processing += $reserved;
            }

            return [
                'available' => true,
                'connection' => $connection,
                'queues' => $queues,
                'waiting' => $waiting,
                'processing' => $processing,
            ];
        } catch (Throwable) {
            return [
                'available' => false,
                'connection' => $connection,
                'queues' => [
                    'default' => ['pending' => 0, 'processing' => 0],
                    ExternalQueryQueue::NAME => ['pending' => 0, 'processing' => 0],
                ],
                'waiting' => 0,
                'processing' => 0,
            ];
        }
    }
}
