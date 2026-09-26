<?php

use App\Support\IndiaDateTime;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class);

it('formats utc instants in india time', function () {
    $utc = Carbon::parse('2026-08-04 10:00:00', 'UTC');

    expect(IndiaDateTime::format($utc))->toBe('4 Aug 2026, 03:30 pm IST')
        ->and(IndiaDateTime::date($utc))->toBe('4 Aug 2026');
});

it('rolls to the next india day after utc evening', function () {
    $utc = Carbon::parse('2026-08-04 20:00:00', 'UTC');

    expect(IndiaDateTime::format($utc))->toBe('5 Aug 2026, 01:30 am IST')
        ->and(IndiaDateTime::date($utc))->toBe('5 Aug 2026');
});

it('returns null for empty dates', function () {
    expect(IndiaDateTime::format(null))->toBeNull()
        ->and(IndiaDateTime::date(null))->toBeNull();
});

it('converts inclusive ist days to utc bounds', function () {
    $start = IndiaDateTime::utcInclusiveStart('2026-03-01');
    $end = IndiaDateTime::utcExclusiveEnd('2026-03-31');

    expect($start->toDateTimeString())->toBe('2026-02-28 18:30:00')
        ->and($end->toDateTimeString())->toBe('2026-03-31 18:30:00');
});
