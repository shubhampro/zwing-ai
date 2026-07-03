<?php

use App\Support\InvoiceRefId;

test('normalizes hyphen-separated ref ids', function () {
    expect(InvoiceRefId::normalize('22-21'))->toBe('22-21');
    expect(InvoiceRefId::normalize(' 22 - 21 '))->toBe('22-21');
    expect(InvoiceRefId::normalize('22'))->toBe('22');
});

test('parts returns individual ref ids', function () {
    expect(InvoiceRefId::parts('22-21'))->toBe(['22', '21']);
    expect(InvoiceRefId::parts('22'))->toBe(['22']);
});

test('uniqueParts deduplicates repeated ref ids', function () {
    expect(InvoiceRefId::uniqueParts('22-21-22'))->toBe(['22', '21']);
});

test('isValid rejects empty ref ids', function () {
    expect(InvoiceRefId::isValid('22-21'))->toBeTrue();
    expect(InvoiceRefId::isValid(''))->toBeFalse();
    expect(InvoiceRefId::isValid('-'))->toBeFalse();
});
