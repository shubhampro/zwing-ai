<?php

use App\Support\Database\WriteIntentSqlDetector;

test('detects insert as write query', function () {
    expect(WriteIntentSqlDetector::isWriteQuery('INSERT INTO users (name) VALUES (?)'))->toBeTrue();
});

test('detects update as write query', function () {
    expect(WriteIntentSqlDetector::isWriteQuery('UPDATE users SET name = ?'))->toBeTrue();
});

test('allows select', function () {
    expect(WriteIntentSqlDetector::isWriteQuery('SELECT * FROM users'))->toBeFalse();
});

test('allows select after block comment', function () {
    expect(WriteIntentSqlDetector::isWriteQuery('/* cache */ SELECT id FROM users'))->toBeFalse();
});

test('detects write after line comment', function () {
    expect(WriteIntentSqlDetector::isWriteQuery("-- setup\nDELETE FROM sessions"))->toBeTrue();
});
