<?php

use App\Exceptions\SalesforceQueryException;
use App\Models\SfAccount;
use App\Services\Salesforce\SalesforceAccountPuller;
use App\Services\Salesforce\SalesforceSoqlClient;

test('account pull soql has no created-date cap', function () {
    expect(SalesforceAccountPuller::ACCOUNT_SOQL)
        ->toContain('FROM Account')
        ->toContain('AccountId != null')
        ->not->toContain('CreatedDate');
});

test('artisan command pulls accounts referenced by any case', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')
            ->once()
            ->with(SalesforceAccountPuller::ACCOUNT_SOQL)
            ->andReturn([
                [
                    'Id' => '001xx000000AcmeAAA',
                    'Name' => 'Acme Retail',
                    'LastModifiedDate' => '2026-09-21T06:34:12.000+0000',
                ],
                [
                    'Id' => '001xx000000BetaBBB',
                    'Name' => 'Beta Stores',
                    'LastModifiedDate' => '2026-09-22T10:00:00.000+0000',
                ],
            ]);
    });

    $this->artisan('sf:pull-accounts')
        ->expectsOutputToContain('Pulled 2 Salesforce accounts.')
        ->assertSuccessful();

    expect(SfAccount::query()->count())->toBe(2);

    $acme = SfAccount::query()->where('sf_id', '001xx000000AcmeAAA')->first();

    expect($acme)->not->toBeNull()
        ->and($acme->name)->toBe('Acme Retail')
        ->and($acme->last_modified_at_sf?->equalTo('2026-09-21 06:34:12'))->toBeTrue()
        ->and($acme->synced_at)->not->toBeNull();
});

test('artisan command upserts existing accounts by sf id', function () {
    $existing = SfAccount::factory()->create([
        'sf_id' => '001xx000000AcmeAAA',
        'name' => 'Old Name',
    ]);

    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->once()->andReturn([
            [
                'Id' => '001xx000000AcmeAAA',
                'Name' => 'Acme Retail',
                'LastModifiedDate' => '2026-09-23T01:00:00.000+0000',
            ],
        ]);
    });

    $this->artisan('sf:pull-accounts')
        ->expectsOutputToContain('Pulled 1 Salesforce accounts.')
        ->assertSuccessful();

    expect(SfAccount::query()->count())->toBe(1);

    $existing->refresh();

    expect($existing->name)->toBe('Acme Retail')
        ->and($existing->last_modified_at_sf?->equalTo('2026-09-23 01:00:00'))->toBeTrue();
});

test('artisan command dry run does not write accounts', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->once()->andReturn([
            [
                'Id' => '001xx000000AcmeAAA',
                'Name' => 'Acme Retail',
                'LastModifiedDate' => '2026-09-21T06:34:12.000+0000',
            ],
        ]);
    });

    $this->artisan('sf:pull-accounts', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run: 1 Salesforce accounts (not saved).')
        ->assertSuccessful();

    expect(SfAccount::query()->count())->toBe(0);
});

test('artisan command skips records without a salesforce id', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->once()->andReturn([
            ['Name' => 'Missing Id'],
            [
                'Id' => '001xx000000AcmeAAA',
                'Name' => 'Acme Retail',
                'LastModifiedDate' => null,
            ],
        ]);
    });

    $this->artisan('sf:pull-accounts')
        ->expectsOutputToContain('Pulled 1 Salesforce accounts.')
        ->assertSuccessful();

    $account = SfAccount::query()->first();

    expect(SfAccount::query()->count())->toBe(1)
        ->and($account?->sf_id)->toBe('001xx000000AcmeAAA')
        ->and($account?->last_modified_at_sf)->toBeNull();
});

test('artisan command fails when salesforce query fails', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')
            ->once()
            ->andThrow(new SalesforceQueryException('Salesforce query failed: org not found'));
    });

    $this->artisan('sf:pull-accounts')
        ->expectsOutputToContain('Salesforce query failed: org not found')
        ->assertFailed();

    expect(SfAccount::query()->count())->toBe(0);
});
