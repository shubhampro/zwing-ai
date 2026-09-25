<?php

use App\Exceptions\SalesforceQueryException;
use App\Models\SfCase;
use App\Models\SfCaseGroupHistory;
use App\Services\Salesforce\SalesforceCaseHistoryPuller;
use App\Services\Salesforce\SalesforceSoqlClient;

function salesforceHistoryRecord(array $overrides = []): array
{
    return array_merge([
        'Id' => '017xx000000HistAAA',
        'CaseId' => '500xx000000CaseAAA',
        'Field' => 'Group__c',
        'OldValue' => 'ERP Helpdesk-L1',
        'NewValue' => 'Zwing-Tech',
        'CreatedDate' => '2026-09-21T07:00:00.000+0000',
        'CreatedBy' => ['Name' => 'Himanshi'],
    ], $overrides);
}

test('artisan command pulls group history linked to local case id', function () {
    $case = SfCase::factory()->create(['sf_id' => '500xx000000CaseAAA']);

    $this->mock(SalesforceSoqlClient::class, function ($mock) use ($case) {
        $mock->shouldReceive('query')
            ->once()
            ->with((new SalesforceCaseHistoryPuller(app(SalesforceSoqlClient::class)))->soqlForCaseIds([$case->sf_id]))
            ->andReturn([
                salesforceHistoryRecord(),
                salesforceHistoryRecord([
                    'Id' => '017xx000000HistBBB',
                    'Field' => 'Owner',
                    'OldValue' => 'ERP Helpdesk-L1',
                    'NewValue' => 'ZPOS Tech/Dev',
                ]),
            ]);
    });

    $this->artisan('sf:pull-case-history')
        ->expectsOutputToContain('Pulled 2 CaseHistory rows.')
        ->assertSuccessful();

    expect(SfCaseGroupHistory::query()->count())->toBe(2);

    $group = SfCaseGroupHistory::query()->where('field', 'Group__c')->first();

    expect($group)->not->toBeNull()
        ->and($group->sf_case_id)->toBe($case->id)
        ->and($group->old_value)->toBe('ERP Helpdesk-L1')
        ->and($group->new_value)->toBe('Zwing-Tech')
        ->and($group->changed_by)->toBe('Himanshi')
        ->and($group->changed_at?->equalTo('2026-09-21 07:00:00'))->toBeTrue()
        ->and($group->case?->sf_id)->toBe('500xx000000CaseAAA');
});

test('artisan command upserts history by sf id', function () {
    $case = SfCase::factory()->create(['sf_id' => '500xx000000CaseAAA']);
    $existing = SfCaseGroupHistory::factory()->create([
        'sf_id' => '017xx000000HistAAA',
        'sf_case_id' => $case->id,
        'new_value' => 'Old Group',
    ]);

    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->once()->andReturn([
            salesforceHistoryRecord(['NewValue' => 'Zwing-Tech']),
        ]);
    });

    $this->artisan('sf:pull-case-history')
        ->assertSuccessful();

    expect(SfCaseGroupHistory::query()->count())->toBe(1);

    $existing->refresh();

    expect($existing->new_value)->toBe('Zwing-Tech');
});

test('artisan command dry run does not write history', function () {
    SfCase::factory()->create(['sf_id' => '500xx000000CaseAAA']);

    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->once()->andReturn([
            salesforceHistoryRecord(),
        ]);
    });

    $this->artisan('sf:pull-case-history', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run: 1 CaseHistory rows (not saved).')
        ->assertSuccessful();

    expect(SfCaseGroupHistory::query()->count())->toBe(0);
});

test('artisan command skips history for unknown cases', function () {
    SfCase::factory()->create(['sf_id' => '500xx000000CaseAAA']);

    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->once()->andReturn([
            salesforceHistoryRecord(['CaseId' => '500xx000000Missing']),
            salesforceHistoryRecord(['Id' => '017xx000000HistCCC']),
        ]);
    });

    $this->artisan('sf:pull-case-history')
        ->expectsOutputToContain('Pulled 1 CaseHistory rows.')
        ->assertSuccessful();

    expect(SfCaseGroupHistory::query()->count())->toBe(1)
        ->and(SfCaseGroupHistory::query()->first()?->sf_id)->toBe('017xx000000HistCCC');
});

test('artisan command warns when no local cases exist', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldNotReceive('query');
    });

    $this->artisan('sf:pull-case-history')
        ->expectsOutputToContain('No local cases. Run php artisan sf:pull-cases first.')
        ->assertSuccessful();
});

test('artisan command fails when salesforce query fails', function () {
    SfCase::factory()->create();

    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')
            ->once()
            ->andThrow(new SalesforceQueryException('Salesforce query failed: org not found'));
    });

    $this->artisan('sf:pull-case-history')
        ->expectsOutputToContain('Salesforce query failed: org not found')
        ->assertFailed();

    expect(SfCaseGroupHistory::query()->count())->toBe(0);
});
