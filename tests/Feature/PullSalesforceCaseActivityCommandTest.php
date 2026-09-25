<?php

use App\Exceptions\SalesforceQueryException;
use App\Models\SfCase;
use App\Models\SfCaseActivity;
use App\Services\Salesforce\SalesforceCaseActivityPuller;
use App\Services\Salesforce\SalesforceSoqlClient;

function salesforceCommentRecord(array $overrides = []): array
{
    return array_merge([
        'Id' => '00axx000000CommAAA',
        'ParentId' => '500xx000000CaseAAA',
        'CommentBody' => 'Waiting on client screenshot.',
        'IsPublished' => true,
        'CreatedDate' => '2026-08-12T09:00:00.000+0000',
        'CreatedBy' => ['Name' => 'Himanshi'],
    ], $overrides);
}

function salesforceFeedRecord(array $overrides = []): array
{
    return array_merge([
        'Id' => '0D5xx000000FeedAAA',
        'ParentId' => '500xx000000CaseAAA',
        'Type' => 'TextPost',
        'Title' => null,
        'Body' => 'Moved this to Zwing-Tech.',
        'CreatedDate' => '2026-08-12T10:00:00.000+0000',
        'CreatedBy' => ['Name' => 'Amit'],
    ], $overrides);
}

function salesforceEmailRecord(array $overrides = []): array
{
    return array_merge([
        'Id' => '02sxx000000MailAAA',
        'ParentId' => '500xx000000CaseAAA',
        'Subject' => 'Re: POS sync failing',
        'TextBody' => 'Still seeing the lag at store open.',
        'FromAddress' => 'client@example.com',
        'Incoming' => true,
        'MessageDate' => '2026-08-12T11:00:00.000+0000',
        'CreatedDate' => '2026-08-12T11:00:05.000+0000',
        'CreatedBy' => ['Name' => 'System'],
    ], $overrides);
}

test('feed soql uses CaseFeed because FeedItem blocks ParentId filters', function () {
    $soql = (new SalesforceCaseActivityPuller(app(SalesforceSoqlClient::class)))
        ->feedSoqlForCaseIds(['500xx000000CaseAAA']);

    expect($soql)->toContain('FROM CaseFeed')
        ->and($soql)->not->toContain('FeedItem');
});

test('artisan command pulls comment feed and email linked to local case id', function () {
    $case = SfCase::factory()->create(['sf_id' => '500xx000000CaseAAA']);
    $puller = new SalesforceCaseActivityPuller(app(SalesforceSoqlClient::class));

    $this->mock(SalesforceSoqlClient::class, function ($mock) use ($case, $puller) {
        $mock->shouldReceive('query')
            ->once()
            ->with($puller->commentSoqlForCaseIds([$case->sf_id]))
            ->andReturn([salesforceCommentRecord()]);
        $mock->shouldReceive('query')
            ->once()
            ->with($puller->feedSoqlForCaseIds([$case->sf_id]))
            ->andReturn([salesforceFeedRecord()]);
        $mock->shouldReceive('query')
            ->once()
            ->with($puller->emailSoqlForCaseIds([$case->sf_id]))
            ->andReturn([salesforceEmailRecord()]);
    });

    $this->artisan('sf:pull-case-activity')
        ->expectsOutputToContain('Pulled 3 activity rows.')
        ->assertSuccessful();

    expect(SfCaseActivity::query()->count())->toBe(3);

    $comment = SfCaseActivity::query()->where('source', SfCaseActivity::SOURCE_COMMENT)->first();
    $feed = SfCaseActivity::query()->where('source', SfCaseActivity::SOURCE_FEED)->first();
    $email = SfCaseActivity::query()->where('source', SfCaseActivity::SOURCE_EMAIL)->first();

    expect($comment)->not->toBeNull()
        ->and($comment->sf_case_id)->toBe($case->id)
        ->and($comment->type)->toBe('Public')
        ->and($comment->body)->toBe('Waiting on client screenshot.')
        ->and($comment->author_name)->toBe('Himanshi')
        ->and($comment->is_incoming)->toBeFalse()
        ->and($comment->occurred_at?->equalTo('2026-08-12 09:00:00'))->toBeTrue()
        ->and($comment->case?->sf_id)->toBe('500xx000000CaseAAA')
        ->and($feed?->type)->toBe('TextPost')
        ->and($feed?->body)->toBe('Moved this to Zwing-Tech.')
        ->and($feed?->author_name)->toBe('Amit')
        ->and($email?->type)->toBe('Incoming')
        ->and($email?->subject)->toBe('Re: POS sync failing')
        ->and($email?->body)->toBe('Still seeing the lag at store open.')
        ->and($email?->author_name)->toBe('client@example.com')
        ->and($email?->is_incoming)->toBeTrue()
        ->and($email?->occurred_at?->equalTo('2026-08-12 11:00:00'))->toBeTrue();
});

test('artisan command upserts activity by sf id', function () {
    $case = SfCase::factory()->create(['sf_id' => '500xx000000CaseAAA']);
    $existing = SfCaseActivity::factory()->create([
        'sf_id' => '00axx000000CommAAA',
        'sf_case_id' => $case->id,
        'body' => 'Old comment',
    ]);

    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->times(3)->andReturn(
            [salesforceCommentRecord(['CommentBody' => 'Updated comment'])],
            [],
            [],
        );
    });

    $this->artisan('sf:pull-case-activity')
        ->assertSuccessful();

    expect(SfCaseActivity::query()->count())->toBe(1);

    $existing->refresh();

    expect($existing->body)->toBe('Updated comment');
});

test('artisan command dry run does not write activity', function () {
    SfCase::factory()->create(['sf_id' => '500xx000000CaseAAA']);

    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->times(3)->andReturn(
            [salesforceCommentRecord()],
            [],
            [],
        );
    });

    $this->artisan('sf:pull-case-activity', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run: 1 activity rows (not saved).')
        ->assertSuccessful();

    expect(SfCaseActivity::query()->count())->toBe(0);
});

test('artisan command skips activity for unknown cases', function () {
    SfCase::factory()->create(['sf_id' => '500xx000000CaseAAA']);

    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->times(3)->andReturn(
            [
                salesforceCommentRecord(['ParentId' => '500xx000000Missing']),
                salesforceCommentRecord(['Id' => '00axx000000CommCCC']),
            ],
            [],
            [],
        );
    });

    $this->artisan('sf:pull-case-activity')
        ->expectsOutputToContain('Pulled 1 activity rows.')
        ->assertSuccessful();

    expect(SfCaseActivity::query()->count())->toBe(1)
        ->and(SfCaseActivity::query()->first()?->sf_id)->toBe('00axx000000CommCCC');
});

test('artisan command warns when no local cases exist', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldNotReceive('query');
    });

    $this->artisan('sf:pull-case-activity')
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

    $this->artisan('sf:pull-case-activity')
        ->expectsOutputToContain('Salesforce query failed: org not found')
        ->assertFailed();

    expect(SfCaseActivity::query()->count())->toBe(0);
});
