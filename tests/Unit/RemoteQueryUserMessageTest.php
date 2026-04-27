<?php

use App\Support\Database\RemoteQueryUserMessage;
use Illuminate\Database\QueryException;

test('uses PDO error text and appends sql state and driver code', function () {
    $pdo = new \PDOException('ignored');
    $pdo->errorInfo = ['42000', '1064', 'You have an error in your SQL syntax near \'FROM\''];

    $exception = new QueryException('mysql', 'SELECT * FROM', [], $pdo);

    expect(RemoteQueryUserMessage::fromQueryException($exception))->toBe(
        'You have an error in your SQL syntax near \'FROM\' [42000 / 1064]',
    );
});

test('uses previous exception message when it is safe', function () {
    $exception = new QueryException('mysql', 'SELECT 1', [], new \Exception('Connection timed out'));

    expect(RemoteQueryUserMessage::fromQueryException($exception))->toBe('Connection timed out');
});

test('falls back when the only message would echo interpolated sql', function () {
    $exception = new QueryException('mysql', 'SELECT 1', [], new \Exception('Error (Connection: x, SQL: SELECT 1)'));

    expect(RemoteQueryUserMessage::fromQueryException($exception))->toContain('could not run');
});
