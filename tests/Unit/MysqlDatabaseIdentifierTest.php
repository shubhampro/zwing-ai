<?php

use App\Support\Database\MysqlDatabaseIdentifier;

test('valid identifiers pass validation and quote correctly', function () {
    expect(MysqlDatabaseIdentifier::isValid('my_db-1'))->toBeTrue();

    expect(MysqlDatabaseIdentifier::quoteForUse('my_db-1'))->toBe('`my_db-1`');
});

test('invalid identifiers are rejected', function () {
    expect(MysqlDatabaseIdentifier::isValid(''))->toBeFalse();
    expect(MysqlDatabaseIdentifier::isValid('bad;drop'))->toBeFalse();
    expect(MysqlDatabaseIdentifier::isValid(str_repeat('a', 65)))->toBeFalse();
});

test('backticks in name are escaped for USE', function () {
    expect(MysqlDatabaseIdentifier::isValid('weird`name'))->toBeFalse();

    expect(MysqlDatabaseIdentifier::quoteForUse('pre_fix'))->toBe('`pre_fix`');
});
