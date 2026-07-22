<?php

use App\Services\QueueStatusReader;
use App\Support\ExternalQueryQueue;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(TestCase::class);

it('sums pending and processing depths across queues', function () {
    $connection = Mockery::mock(QueueContract::class);
    $connection->shouldReceive('pendingSize')->with('default')->andReturn(2);
    $connection->shouldReceive('reservedSize')->with('default')->andReturn(1);
    $connection->shouldReceive('pendingSize')->with(ExternalQueryQueue::NAME)->andReturn(4);
    $connection->shouldReceive('reservedSize')->with(ExternalQueryQueue::NAME)->andReturn(1);

    Queue::shouldReceive('connection')
        ->once()
        ->with(config('queue.default'))
        ->andReturn($connection);

    $snapshot = app(QueueStatusReader::class)->snapshot();

    expect($snapshot['available'])->toBeTrue()
        ->and($snapshot['waiting'])->toBe(6)
        ->and($snapshot['processing'])->toBe(2)
        ->and($snapshot['queues']['default']['pending'])->toBe(2)
        ->and($snapshot['queues']['default']['processing'])->toBe(1)
        ->and($snapshot['queues'][ExternalQueryQueue::NAME]['pending'])->toBe(4)
        ->and($snapshot['queues'][ExternalQueryQueue::NAME]['processing'])->toBe(1);
});

it('returns unavailable snapshot when the queue connection fails', function () {
    Queue::shouldReceive('connection')
        ->once()
        ->andThrow(new RuntimeException('redis down'));

    $snapshot = app(QueueStatusReader::class)->snapshot();

    expect($snapshot['available'])->toBeFalse()
        ->and($snapshot['waiting'])->toBe(0)
        ->and($snapshot['processing'])->toBe(0)
        ->and($snapshot['queues'][ExternalQueryQueue::NAME]['pending'])->toBe(0);
});
