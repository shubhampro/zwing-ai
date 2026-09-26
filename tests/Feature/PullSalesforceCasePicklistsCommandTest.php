<?php

use App\Exceptions\SalesforceQueryException;
use App\Models\SfApplication;
use App\Models\SfGroup;
use App\Models\SfModule;
use App\Models\SfProduct;
use App\Models\SfSubModule;
use App\Models\SfType;
use App\Services\Salesforce\SalesforceSoqlClient;

test('artisan command pulls product picklist values', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('describe')
            ->once()
            ->with('Case')
            ->andReturn(casePicklistDescribe());
    });

    $this->artisan('sf:pull-products')
        ->expectsOutputToContain('Pulled 2 Salesforce products.')
        ->assertSuccessful();

    expect(SfProduct::query()->count())->toBe(2);

    $zwing = SfProduct::query()->where('name', 'Zwing')->first();

    expect($zwing)->not->toBeNull()
        ->and($zwing->sort_order)->toBe(0)
        ->and($zwing->is_active)->toBeTrue()
        ->and($zwing->synced_at)->not->toBeNull();
});

test('artisan command pulls application picklist values', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('describe')->once()->with('Case')->andReturn(casePicklistDescribe());
    });

    $this->artisan('sf:pull-applications')
        ->expectsOutputToContain('Pulled 2 Salesforce applications.')
        ->assertSuccessful();

    $inactive = SfApplication::query()->where('name', 'Ginesys (ERP)')->first();

    expect(SfApplication::query()->count())->toBe(2)
        ->and($inactive?->is_active)->toBeFalse()
        ->and($inactive?->sort_order)->toBe(1);
});

test('artisan command pulls module picklist values', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('describe')->once()->with('Case')->andReturn(casePicklistDescribe());
    });

    $this->artisan('sf:pull-modules')
        ->expectsOutputToContain('Pulled 2 Salesforce modules.')
        ->assertSuccessful();

    expect(SfModule::query()->pluck('name')->all())->toEqualCanonicalizing(['Orders', 'Wallet Service']);
});

test('artisan command pulls sub module picklist values', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('describe')->once()->with('Case')->andReturn(casePicklistDescribe());
    });

    $this->artisan('sf:pull-sub-modules')
        ->expectsOutputToContain('Pulled 2 Salesforce sub modules.')
        ->assertSuccessful();

    expect(SfSubModule::query()->pluck('name')->all())->toEqualCanonicalizing(['Order Processing', 'Live Sync']);
});

test('artisan command pulls type picklist values', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('describe')->once()->with('Case')->andReturn(casePicklistDescribe());
    });

    $this->artisan('sf:pull-types')
        ->expectsOutputToContain('Pulled 2 Salesforce types.')
        ->assertSuccessful();

    expect(SfType::query()->pluck('name')->all())->toEqualCanonicalizing(['Incident', 'Service Request']);
});

test('artisan command pulls group picklist values', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('describe')->once()->with('Case')->andReturn(casePicklistDescribe());
    });

    $this->artisan('sf:pull-groups')
        ->expectsOutputToContain('Pulled 2 Salesforce groups.')
        ->assertSuccessful();

    expect(SfGroup::query()->pluck('name')->all())->toEqualCanonicalizing(['Zwing-Tech', 'ERP Helpdesk-L1']);
});

test('artisan command upserts products by name', function () {
    $existing = SfProduct::factory()->create([
        'name' => 'Zwing',
        'sort_order' => 9,
        'is_active' => false,
    ]);

    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('describe')->once()->andReturn(casePicklistDescribe());
    });

    $this->artisan('sf:pull-products')
        ->expectsOutputToContain('Pulled 2 Salesforce products.')
        ->assertSuccessful();

    $existing->refresh();

    expect(SfProduct::query()->count())->toBe(2)
        ->and($existing->sort_order)->toBe(0)
        ->and($existing->is_active)->toBeTrue();
});

test('artisan command dry run does not write products', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('describe')->once()->andReturn(casePicklistDescribe());
    });

    $this->artisan('sf:pull-products', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run: 2 Salesforce products (not saved).')
        ->assertSuccessful();

    expect(SfProduct::query()->count())->toBe(0);
});

test('artisan command skips blank picklist values', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('describe')->once()->andReturn([
            'fields' => [
                [
                    'name' => 'Product__c',
                    'picklistValues' => [
                        ['value' => '', 'active' => true],
                        ['value' => 'Zwing', 'active' => true],
                    ],
                ],
            ],
        ]);
    });

    $this->artisan('sf:pull-products')
        ->expectsOutputToContain('Pulled 1 Salesforce products.')
        ->assertSuccessful();

    expect(SfProduct::query()->pluck('name')->all())->toBe(['Zwing']);
});

test('artisan command fails when the case field is missing', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('describe')->once()->andReturn(['fields' => []]);
    });

    $this->artisan('sf:pull-products')
        ->expectsOutputToContain('Salesforce Case field [Product__c] was not found.')
        ->assertFailed();

    expect(SfProduct::query()->count())->toBe(0);
});

test('artisan command fails when salesforce describe fails', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('describe')
            ->once()
            ->andThrow(new SalesforceQueryException('Salesforce describe failed: org not found'));
    });

    $this->artisan('sf:pull-applications')
        ->expectsOutputToContain('Salesforce describe failed: org not found')
        ->assertFailed();

    expect(SfApplication::query()->count())->toBe(0);
});

/**
 * @return array{fields: list<array{name: string, picklistValues: list<array{value: string, active: bool}>}>}
 */
function casePicklistDescribe(): array
{
    return [
        'fields' => [
            [
                'name' => 'Product__c',
                'picklistValues' => [
                    ['value' => 'Zwing', 'active' => true],
                    ['value' => 'Ginesys', 'active' => true],
                ],
            ],
            [
                'name' => 'Application__c',
                'picklistValues' => [
                    ['value' => 'Zwing (Cloud POS)', 'active' => true],
                    ['value' => 'Ginesys (ERP)', 'active' => false],
                ],
            ],
            [
                'name' => 'Module__c',
                'picklistValues' => [
                    ['value' => 'Orders', 'active' => true],
                    ['value' => 'Wallet Service', 'active' => true],
                ],
            ],
            [
                'name' => 'Sub_module__c',
                'picklistValues' => [
                    ['value' => 'Order Processing', 'active' => true],
                    ['value' => 'Live Sync', 'active' => true],
                ],
            ],
            [
                'name' => 'Type',
                'picklistValues' => [
                    ['value' => 'Incident', 'active' => true],
                    ['value' => 'Service Request', 'active' => true],
                ],
            ],
            [
                'name' => 'Group__c',
                'picklistValues' => [
                    ['value' => 'Zwing-Tech', 'active' => true],
                    ['value' => 'ERP Helpdesk-L1', 'active' => true],
                ],
            ],
        ],
    ];
}
