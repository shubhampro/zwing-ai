<?php

use App\Support\DatabaseHost;

it('strips http scheme and trailing slash from host', function () {
    expect(DatabaseHost::normalize('http://pgflex-erpdb-prod-02.postgres.database.azure.com/'))
        ->toBe('pgflex-erpdb-prod-02.postgres.database.azure.com');
});

it('strips https scheme and port from host url', function () {
    expect(DatabaseHost::normalize('https://db.example.com:5432/path'))
        ->toBe('db.example.com');
});

it('keeps plain hostname', function () {
    expect(DatabaseHost::normalize('db.example.com'))->toBe('db.example.com');
});

it('returns null for blank host', function () {
    expect(DatabaseHost::normalize(''))->toBeNull()
        ->and(DatabaseHost::normalize(null))->toBeNull();
});

it('masks host to a short asterisk label', function () {
    expect(DatabaseHost::mask('pgflex-erpdb-prod-02.postgres.database.azure.com'))
        ->toBe('p********m')
        ->and(DatabaseHost::mask(null))->toBe('****')
        ->and(DatabaseHost::mask('ab'))->toBe('**');
});
