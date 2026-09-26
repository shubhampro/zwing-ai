<?php

use App\Exceptions\SalesforceQueryException;
use App\Models\SfAccount;
use App\Models\SfAgent;
use App\Models\SfApplication;
use App\Models\SfCase;
use App\Models\SfGroup;
use App\Models\SfModule;
use App\Models\SfProduct;
use App\Models\SfType;
use App\Services\Salesforce\SalesforceCasePuller;
use App\Services\Salesforce\SalesforceSoqlClient;

function salesforceCaseRecord(array $overrides = []): array
{
    return array_merge([
        'Id' => '500xx000000CaseAAA',
        'CaseNumber' => '00164426',
        'Subject' => 'Sync Not Working',
        'Description' => 'Sync between Zwing and Ginesys is not working.',
        'Status' => 'Resolved',
        'Priority' => 'High',
        'Type' => 'Incident',
        'Origin' => 'Portal',
        'IsClosed' => true,
        'Is_Spam__c' => false,
        'Product__c' => 'Zwing',
        'Product_Name__c' => 'Zwing (Cloud POS)',
        'Application__c' => 'Zwing (Cloud POS)',
        'Module__c' => 'Zwing Console',
        'Sub_module__c' => null,
        'Group__c' => 'Zwing-Tech',
        'First_Assigned_Group__c' => 'ERP Helpdesk-L1',
        'Owner' => ['Name' => 'ZPOS Tech/Dev'],
        'Agent__c' => '005xx000000AgentAAA',
        'Agent__r' => ['Name' => 'Himanshi'],
        'AccountId' => '001xx000000AcmeAAA',
        'Requester_Name__c' => 'Jayesh',
        'Tags__c' => 'PostgreSQL',
        'Size__c' => 'XS',
        'Jira_ID__c' => null,
        'Jira_Status__c' => null,
        'CreatedDate' => '2026-09-21T06:34:12.000+0000',
        'Resolved_Date_Time__c' => '2026-09-22T10:07:00.000+0000',
        'ClosedDate' => '2026-09-22T10:07:00.000+0000',
        'LastModifiedDate' => '2026-09-22T10:08:00.000+0000',
    ], $overrides);
}

test('artisan command pulls cases since march and maps master foreign keys', function () {
    $account = SfAccount::factory()->create([
        'sf_id' => '001xx000000AcmeAAA',
        'name' => 'Acme Retail',
    ]);
    $product = SfProduct::factory()->create(['name' => 'Zwing']);
    $application = SfApplication::factory()->create(['name' => 'Zwing (Cloud POS)']);
    $module = SfModule::factory()->create(['name' => 'Zwing Console']);
    $type = SfType::factory()->create(['name' => 'Incident']);
    $group = SfGroup::factory()->create(['name' => 'Zwing-Tech']);
    $firstGroup = SfGroup::factory()->create(['name' => 'ERP Helpdesk-L1']);
    $agent = SfAgent::factory()->create([
        'sf_id' => '005xx000000AgentAAA',
        'name' => 'Himanshi',
    ]);

    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')
            ->atLeast()
            ->once()
            ->withArgs(function (string $soql): bool {
                expect($soql)->toContain('CreatedDate >=')
                    ->and($soql)->toContain('CreatedDate <')
                    ->and($soql)->not->toContain("Product__c = 'Zwing'");

                return true;
            })
            ->andReturn([
                salesforceCaseRecord(),
            ]);
    });

    $this->artisan('sf:pull-cases')
        ->expectsOutputToContain('Pulled 1 Salesforce cases.')
        ->assertSuccessful();

    $case = SfCase::query()->first();

    expect($case)->not->toBeNull()
        ->and($case->sf_id)->toBe('500xx000000CaseAAA')
        ->and($case->case_number)->toBe('00164426')
        ->and($case->subject)->toBe('Sync Not Working')
        ->and($case->status)->toBe('Resolved')
        ->and($case->is_closed)->toBeTrue()
        ->and($case->product)->toBe('Zwing')
        ->and($case->sf_product_id)->toBe($product->id)
        ->and($case->sf_application_id)->toBe($application->id)
        ->and($case->sf_module_id)->toBe($module->id)
        ->and($case->sf_type_id)->toBe($type->id)
        ->and($case->sf_group_id)->toBe($group->id)
        ->and($case->sf_first_assigned_group_id)->toBe($firstGroup->id)
        ->and($case->sf_agent_id)->toBe($agent->id)
        ->and($case->group_name)->toBe('Zwing-Tech')
        ->and($case->owner_name)->toBe('ZPOS Tech/Dev')
        ->and($case->agent_name)->toBe('Himanshi')
        ->and($case->sf_account_id)->toBe($account->id)
        ->and($case->requester_name)->toBe('Jayesh')
        ->and($case->created_at_sf?->equalTo('2026-09-21 06:34:12'))->toBeTrue()
        ->and($case->resolved_at_sf?->equalTo('2026-09-22 10:07:00'))->toBeTrue()
        ->and($case->account?->name)->toBe('Acme Retail');
});

test('artisan command upserts existing cases by sf id', function () {
    $account = SfAccount::factory()->create(['sf_id' => '001xx000000AcmeAAA']);

    $existing = SfCase::factory()->create([
        'sf_id' => '500xx000000CaseAAA',
        'case_number' => '00164426',
        'subject' => 'Old subject',
        'status' => 'Open',
        'sf_account_id' => $account->id,
    ]);

    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->andReturn([
            salesforceCaseRecord(['Subject' => 'Sync Not Working', 'Status' => 'Resolved']),
        ]);
    });

    $this->artisan('sf:pull-cases')
        ->expectsOutputToContain('Pulled 1 Salesforce cases.')
        ->assertSuccessful();

    expect(SfCase::query()->count())->toBe(1);

    $existing->refresh();

    expect($existing->subject)->toBe('Sync Not Working')
        ->and($existing->status)->toBe('Resolved');
});

test('artisan command dry run does not write cases', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->andReturn([
            salesforceCaseRecord(['AccountId' => null]),
        ]);
    });

    $this->artisan('sf:pull-cases', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run: 1 Salesforce cases (not saved).')
        ->assertSuccessful();

    expect(SfCase::query()->count())->toBe(0);
});

test('artisan command nulls account when local account is missing', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->andReturn([
            salesforceCaseRecord(['AccountId' => '001xx000000Missing']),
        ]);
    });

    $this->artisan('sf:pull-cases')
        ->assertSuccessful();

    expect(SfCase::query()->first()?->sf_account_id)->toBeNull();
});

test('case pull soql starts at first march 2026 and has no zwing filter', function () {
    $windows = app(SalesforceCasePuller::class)->soqlWindows();

    expect($windows)->not->toBeEmpty()
        ->and($windows[0])->toContain(SalesforceCasePuller::CREATED_SINCE)
        ->and($windows[0])->not->toContain("Product__c = 'Zwing'");
});

test('case pull soql respects from and to date range', function () {
    $windows = app(SalesforceCasePuller::class)->soqlWindows(from: '2026-08-01', to: '2026-08-07');

    expect($windows)->toHaveCount(1)
        ->and($windows[0])->toContain('CreatedDate >= 2026-08-01T00:00:00+05:30')
        ->and($windows[0])->toContain('CreatedDate < 2026-08-08T00:00:00+05:30')
        ->and($windows[0])->not->toContain('2026-03-01');
});

test('case pull soql accepts unpadded dates', function () {
    $windows = app(SalesforceCasePuller::class)->soqlWindows(from: '2026-03-1', to: '2026-09-2');

    expect($windows)->not->toBeEmpty()
        ->and($windows[0])->toContain('CreatedDate >= 2026-03-01T00:00:00+05:30')
        ->and($windows[array_key_last($windows)])->toContain('CreatedDate < 2026-09-03T00:00:00+05:30');
});

test('artisan command pulls cases inside from to range', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')
            ->atLeast()
            ->once()
            ->withArgs(function (string $soql): bool {
                expect($soql)->toContain('CreatedDate >= 2026-08-01T00:00:00+05:30')
                    ->and($soql)->toContain('CreatedDate < 2026-08-08T00:00:00+05:30');

                return true;
            })
            ->andReturn([
                salesforceCaseRecord(['AccountId' => null]),
            ]);
    });

    $this->artisan('sf:pull-cases', ['--from' => '2026-08-01', '--to' => '2026-08-07'])
        ->expectsOutputToContain('2026-08-01 to 2026-08-07 IST')
        ->expectsOutputToContain('Pulled 1 Salesforce cases.')
        ->assertSuccessful();

    expect(SfCase::query()->count())->toBe(1);
});

test('artisan command rejects invalid from date', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldNotReceive('query');
    });

    $this->artisan('sf:pull-cases', ['--from' => '26-08-01'])
        ->expectsOutputToContain('Invalid date [26-08-01]. Use Y-m-d (IST).')
        ->assertFailed();
});

test('artisan command rejects from after to', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldNotReceive('query');
    });

    $this->artisan('sf:pull-cases', ['--from' => '2026-08-10', '--to' => '2026-08-01'])
        ->expectsOutputToContain('The --from date must be on or before --to.')
        ->assertFailed();
});

test('artisan command pulls non-zwing products since march', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->andReturn([
            salesforceCaseRecord([
                'Id' => '500TZ00000pRYwPYAW',
                'CaseNumber' => '00158223',
                'Subject' => 'Unable to login the Ginesys ERP all the users - Urgent',
                'Status' => 'Open',
                'IsClosed' => false,
                'Product__c' => 'Ginesys',
                'Product_Name__c' => null,
                'Application__c' => 'Ginesys (ERP)',
                'Group__c' => 'ERP Tech',
                'AccountId' => null,
                'Resolved_Date_Time__c' => null,
                'ClosedDate' => null,
            ]),
        ]);
    });

    $this->artisan('sf:pull-cases')
        ->expectsOutputToContain('Pulled 1 Salesforce cases.')
        ->assertSuccessful();

    $case = SfCase::query()->first();

    expect($case)->not->toBeNull()
        ->and($case->sf_id)->toBe('500TZ00000pRYwPYAW')
        ->and($case->case_number)->toBe('00158223')
        ->and($case->product)->toBe('Ginesys')
        ->and($case->application)->toBe('Ginesys (ERP)')
        ->and($case->group_name)->toBe('ERP Tech');
});

test('artisan command skips records without case id or number', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->andReturn([
            ['Subject' => 'No id'],
            salesforceCaseRecord(['Id' => '500xx000000CaseBBB', 'CaseNumber' => '00163975', 'AccountId' => null]),
        ]);
    });

    $this->artisan('sf:pull-cases')
        ->expectsOutputToContain('Pulled 1 Salesforce cases.')
        ->assertSuccessful();

    expect(SfCase::query()->count())->toBe(1)
        ->and(SfCase::query()->first()?->case_number)->toBe('00163975');
});

test('artisan command fails when salesforce query fails', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')
            ->once()
            ->andThrow(new SalesforceQueryException('Salesforce query failed: org not found'));
    });

    $this->artisan('sf:pull-cases')
        ->expectsOutputToContain('Salesforce query failed: org not found')
        ->assertFailed();

    expect(SfCase::query()->count())->toBe(0);
});
