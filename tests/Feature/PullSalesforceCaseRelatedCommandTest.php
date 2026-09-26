<?php

use App\Exceptions\SalesforceQueryException;
use App\Models\SfCase;
use App\Models\SfCaseActivity;
use App\Models\SfCaseGroupHistory;
use App\Services\Salesforce\SalesforceSoqlClient;

test('artisan command pulls cases history and activity in one run', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->andReturnUsing(function (string $soql): array {
            return match (relatedPullSoqlKind($soql)) {
                'case' => [relatedPullCaseRecord()],
                'history' => [relatedPullHistoryRecord()],
                'comment' => [relatedPullCommentRecord()],
                'feed', 'email' => [],
                default => [],
            };
        });
    });

    $this->artisan('sf:pull-case-related')
        ->expectsOutputToContain('sf_cases')
        ->expectsOutputToContain('sf_case_group_histories')
        ->expectsOutputToContain('sf_case_activities')
        ->expectsOutputToContain('Pulled 3 Salesforce case-related rows.')
        ->assertSuccessful();

    expect(SfCase::query()->count())->toBe(1)
        ->and(SfCaseGroupHistory::query()->count())->toBe(1)
        ->and(SfCaseActivity::query()->count())->toBe(1)
        ->and(SfCase::query()->value('case_number'))->toBe('00164426')
        ->and(SfCaseGroupHistory::query()->value('new_value'))->toBe('Zwing-Tech')
        ->and(SfCaseActivity::query()->value('body'))->toBe('Waiting on client screenshot.');
});

test('artisan command pulls cases inside from to range', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->andReturnUsing(function (string $soql): array {
            return match (relatedPullSoqlKind($soql)) {
                'case' => tap([relatedPullCaseRecord()], function () use ($soql): void {
                    expect($soql)->toContain('CreatedDate >= 2026-')
                        ->and($soql)->toContain('CreatedDate < 2026-')
                        ->and($soql)->not->toContain('CreatedDate >= 2026-02-')
                        ->and($soql)->not->toContain('CreatedDate >= 2026-09-03');
                }),
                'history' => [relatedPullHistoryRecord()],
                'comment' => [relatedPullCommentRecord()],
                'feed', 'email' => [],
                default => [],
            };
        });
    });

    $this->artisan('sf:pull-case-related', ['--from' => '2026-03-1', '--to' => '2026-09-2'])
        ->expectsOutputToContain('Pulled 3 Salesforce case-related rows.')
        ->assertSuccessful();

    expect(SfCase::query()->count())->toBe(1)
        ->and(SfCaseGroupHistory::query()->count())->toBe(1)
        ->and(SfCaseActivity::query()->count())->toBe(1);
});

test('artisan command rejects invalid case date range', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldNotReceive('query');
    });

    $this->artisan('sf:pull-case-related', ['--from' => '2026-08-10', '--to' => '2026-08-01'])
        ->expectsOutputToContain('The --from date must be on or before --to.')
        ->assertFailed();
});

test('artisan command dry run does not write case-related rows', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->andReturnUsing(function (string $soql): array {
            expect(relatedPullSoqlKind($soql))->toBe('case');

            return [relatedPullCaseRecord()];
        });
    });

    $this->artisan('sf:pull-case-related', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run: 1 Salesforce case-related rows (not saved).')
        ->assertSuccessful();

    expect(SfCase::query()->count())->toBe(0)
        ->and(SfCaseGroupHistory::query()->count())->toBe(0)
        ->and(SfCaseActivity::query()->count())->toBe(0);
});

test('artisan command skips history and activity when no cases exist', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->andReturnUsing(function (string $soql): array {
            expect(relatedPullSoqlKind($soql))->toBe('case');

            return [];
        });
    });

    $this->artisan('sf:pull-case-related')
        ->expectsOutputToContain('Pulled 0 Salesforce case-related rows.')
        ->assertSuccessful();

    expect(SfCase::query()->count())->toBe(0)
        ->and(SfCaseGroupHistory::query()->count())->toBe(0)
        ->and(SfCaseActivity::query()->count())->toBe(0);
});

test('artisan command fails when salesforce query fails', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')
            ->once()
            ->andThrow(new SalesforceQueryException('Salesforce query failed: org not found'));
    });

    $this->artisan('sf:pull-case-related')
        ->expectsOutputToContain('Salesforce query failed: org not found')
        ->assertFailed();

    expect(SfCase::query()->count())->toBe(0)
        ->and(SfCaseGroupHistory::query()->count())->toBe(0)
        ->and(SfCaseActivity::query()->count())->toBe(0);
});

/**
 * @return array<string, mixed>
 */
function relatedPullCaseRecord(): array
{
    return [
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
        'AccountId' => null,
        'Requester_Name__c' => 'Jayesh',
        'Tags__c' => 'PostgreSQL',
        'Size__c' => 'XS',
        'Jira_ID__c' => null,
        'Jira_Status__c' => null,
        'CreatedDate' => '2026-09-21T06:34:12.000+0000',
        'Resolved_Date_Time__c' => '2026-09-22T10:07:00.000+0000',
        'ClosedDate' => '2026-09-22T10:07:00.000+0000',
        'LastModifiedDate' => '2026-09-22T10:08:00.000+0000',
    ];
}

/**
 * @return array<string, mixed>
 */
function relatedPullHistoryRecord(): array
{
    return [
        'Id' => '017xx000000HistAAA',
        'CaseId' => '500xx000000CaseAAA',
        'Field' => 'Group__c',
        'OldValue' => 'ERP Helpdesk-L1',
        'NewValue' => 'Zwing-Tech',
        'CreatedDate' => '2026-09-21T07:00:00.000+0000',
        'CreatedBy' => ['Name' => 'Himanshi'],
    ];
}

/**
 * @return array<string, mixed>
 */
function relatedPullCommentRecord(): array
{
    return [
        'Id' => '00axx000000CommAAA',
        'ParentId' => '500xx000000CaseAAA',
        'CommentBody' => 'Waiting on client screenshot.',
        'IsPublished' => true,
        'CreatedDate' => '2026-08-12T09:00:00.000+0000',
        'CreatedBy' => ['Name' => 'Himanshi'],
    ];
}

function relatedPullSoqlKind(string $soql): string
{
    return match (true) {
        str_contains($soql, 'FROM CaseHistory') => 'history',
        str_contains($soql, 'FROM CaseComment') => 'comment',
        str_contains($soql, 'FROM CaseFeed') => 'feed',
        str_contains($soql, 'FROM EmailMessage') => 'email',
        str_contains($soql, 'FROM Case') => 'case',
        default => 'other',
    };
}
