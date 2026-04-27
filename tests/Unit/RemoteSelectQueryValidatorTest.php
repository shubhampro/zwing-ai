<?php

use App\Support\Database\RemoteSelectQueryValidator;

test('accepts select and with statements', function () {
    RemoteSelectQueryValidator::validate('SELECT id FROM users WHERE x = 1');
    RemoteSelectQueryValidator::validate('WITH t AS (SELECT 1 AS n) SELECT * FROM t');
    RemoteSelectQueryValidator::validate("/* c */\nSELECT 1");
    expect(true)->toBeTrue();
});

test('rejects empty and multi statement', function () {
    expect(fn () => RemoteSelectQueryValidator::validate(''))->toThrow(InvalidArgumentException::class);
    expect(fn () => RemoteSelectQueryValidator::validate('SELECT 1; SELECT 2'))->toThrow(InvalidArgumentException::class);
});

test('rejects non select', function () {
    expect(fn () => RemoteSelectQueryValidator::validate('UPDATE users SET x = 1'))->toThrow(InvalidArgumentException::class);
    expect(fn () => RemoteSelectQueryValidator::validate('INSERT INTO users VALUES (1)'))->toThrow(InvalidArgumentException::class);
});

test('rejects into outfile and for update', function () {
    expect(fn () => RemoteSelectQueryValidator::validate('SELECT * INTO OUTFILE "/tmp/x" FROM users'))->toThrow(InvalidArgumentException::class);
    expect(fn () => RemoteSelectQueryValidator::validate('SELECT * FROM users FOR UPDATE'))->toThrow(InvalidArgumentException::class);
});
