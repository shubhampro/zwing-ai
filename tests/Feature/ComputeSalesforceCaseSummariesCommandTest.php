<?php

use App\Models\SfCase;
use App\Models\SfCaseActivity;
use App\Models\SfCaseGroupHold;
use App\Services\Salesforce\SalesforceCaseSummaryComputer;
use Illuminate\Support\Carbon;

test('artisan command writes rule-based summary from activity and holds', function () {
    $case = SfCase::factory()->create([
        'case_number' => '00164163',
        'subject' => 'Integration with Paytm',
        'description' => "Dear team,\n\nWe required POS (Zwing) integrate with paytm payment solution\n\nBest Regards,\nPrasad K",
        'first_assigned_group' => 'ERP Helpdesk-L1',
        'group_name' => 'Zwing-Tech',
        'created_at_sf' => '2026-08-12 09:00:00',
        'resolved_at_sf' => '2026-08-23 10:00:00',
        'closed_at_sf' => '2026-08-23 10:00:00',
        'is_closed' => true,
        'resolution_minutes' => 15840,
        'zwing_resolution_minutes' => 3024,
    ]);

    SfCaseGroupHold::factory()->create([
        'sf_case_id' => $case->id,
        'group_name' => 'ERP Helpdesk-L1',
        'started_at' => '2026-08-12 09:00:00',
        'ended_at' => '2026-08-21 09:00:00',
        'held_minutes' => 12960,
        'is_open' => false,
    ]);
    SfCaseGroupHold::factory()->create([
        'sf_case_id' => $case->id,
        'group_name' => 'Zwing-Tech',
        'started_at' => '2026-08-21 09:00:00',
        'ended_at' => '2026-08-23 10:00:00',
        'held_minutes' => 3024,
        'is_open' => false,
    ]);

    SfCaseActivity::factory()->create([
        'sf_case_id' => $case->id,
        'source' => SfCaseActivity::SOURCE_EMAIL,
        'type' => 'Incoming',
        'body' => "Hi,\nThe requested information is currently unavailable.\n\nOn Wed, Sep 23, 2026 at 12:07 PM Ginesys Care wrote:\n> Dear Valued Customer",
        'author_name' => 'accounts9@popees.com',
        'is_incoming' => true,
        'occurred_at' => '2026-08-22 07:32:00',
    ]);
    SfCaseActivity::factory()->create([
        'sf_case_id' => $case->id,
        'source' => SfCaseActivity::SOURCE_FEED,
        'type' => 'TextPost',
        'body' => '<p>As conversed with the client over call.</p><p>Marking this ticket as resolved.</p>',
        'author_name' => 'Sujit Kumar Jha',
        'is_incoming' => false,
        'occurred_at' => '2026-08-23 08:00:00',
    ]);

    $this->artisan('sf:compute-case-summaries')
        ->expectsOutputToContain('Computed 1 case summaries.')
        ->assertSuccessful();

    $case->refresh();

    expect($case->activity_summary)->toBe(implode("\n", [
        'Closed. Opened 12 Aug 2026. Closed 23 Aug 2026. 11.0d total / 2.1d Zwing.',
        'Path: ERP Helpdesk-L1 (9.0d) → Zwing-Tech (2.1d)',
        'Ask: Dear team, We required POS (Zwing) integrate with paytm payment solution Best Regards, Prasad K',
        'Customer: Hi, The requested information is currently unavailable.',
        'Note: As conversed with the client over call. Marking this ticket as resolved.',
        'Mail: 1 in / 0 out. Notes: 1.',
    ]))
        ->and($case->activity_summarized_at)->not->toBeNull()
        ->and(app(SalesforceCaseSummaryComputer::class)->parse($case->activity_summary))->toMatchArray([
            'status' => 'Closed. Opened 12 Aug 2026. Closed 23 Aug 2026. 11.0d total / 2.1d Zwing.',
            'path' => ['ERP Helpdesk-L1 (9.0d)', 'Zwing-Tech (2.1d)'],
            'ask' => 'Dear team, We required POS (Zwing) integrate with paytm payment solution Best Regards, Prasad K',
            'customer' => 'Hi, The requested information is currently unavailable.',
            'note' => 'As conversed with the client over call. Marking this ticket as resolved.',
            'mail' => '1 in / 0 out. Notes: 1.',
        ]);
});

test('artisan command skips noisy feed posts and ginesys care templates', function () {
    $case = SfCase::factory()->create([
        'description' => null,
        'created_at_sf' => '2026-08-12 09:00:00',
        'resolved_at_sf' => null,
        'closed_at_sf' => null,
        'is_closed' => false,
        'first_assigned_group' => 'Zwing-Tech',
        'group_name' => 'Zwing-Tech',
    ]);

    SfCaseActivity::factory()->create([
        'sf_case_id' => $case->id,
        'source' => SfCaseActivity::SOURCE_FEED,
        'type' => 'EmailMessageEvent',
        'body' => null,
        'occurred_at' => '2026-08-12 10:00:00',
    ]);
    SfCaseActivity::factory()->create([
        'sf_case_id' => $case->id,
        'source' => SfCaseActivity::SOURCE_FEED,
        'type' => 'TextPost',
        'body' => 'Track: Agent: Sujit Kumar Jha created a Timelog record',
        'occurred_at' => '2026-08-12 11:00:00',
    ]);
    SfCaseActivity::factory()->create([
        'sf_case_id' => $case->id,
        'source' => SfCaseActivity::SOURCE_EMAIL,
        'type' => 'Incoming',
        'body' => "Dear Valued Customer,\n\nGreetings from Ginesys Care!\n\nCase Number - 00164163",
        'author_name' => 'care@ginesys.in',
        'is_incoming' => true,
        'occurred_at' => '2026-08-12 12:00:00',
    ]);
    SfCaseActivity::factory()->create([
        'sf_case_id' => $case->id,
        'source' => SfCaseActivity::SOURCE_EMAIL,
        'type' => 'Outgoing',
        'body' => "Dear Valued Customer,\n\nGreetings from Ginesys Care!\nWe are marking this ticket as below.",
        'author_name' => 'care@ginesys.in',
        'is_incoming' => false,
        'occurred_at' => '2026-08-12 12:05:00',
    ]);

    $this->artisan('sf:compute-case-summaries')
        ->assertSuccessful();

    $case->refresh();

    expect($case->activity_summary)->toBe("Open. Opened 12 Aug 2026.\nPath: Zwing-Tech")
        ->and($case->activity_summary)->not->toContain('Ask:')
        ->and($case->activity_summary)->not->toContain('Customer:')
        ->and($case->activity_summary)->not->toContain('Note:')
        ->and($case->activity_summary)->not->toContain('Mail:');
});

test('artisan command dry run does not write summaries', function () {
    $case = SfCase::factory()->create([
        'activity_summary' => null,
        'created_at_sf' => '2026-08-12 09:00:00',
    ]);

    $this->artisan('sf:compute-case-summaries', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run: 1 case summaries (not saved).')
        ->assertSuccessful();

    $case->refresh();

    expect($case->activity_summary)->toBeNull()
        ->and($case->activity_summarized_at)->toBeNull();
});

test('artisan command overwrites previous summary', function () {
    $case = SfCase::factory()->create([
        'description' => 'Old ask',
        'activity_summary' => 'stale',
        'activity_summarized_at' => '2026-08-01 00:00:00',
        'created_at_sf' => '2026-08-12 09:00:00',
        'first_assigned_group' => 'Zwing-Tech',
        'group_name' => 'Zwing-Tech',
    ]);

    $this->travelTo(Carbon::parse('2026-09-23 12:00:00'));

    $this->artisan('sf:compute-case-summaries')
        ->assertSuccessful();

    $case->refresh();

    expect($case->activity_summary)->toContain('Ask: Old ask')
        ->and($case->activity_summary)->not->toBe('stale')
        ->and($case->activity_summarized_at?->equalTo('2026-09-23 12:00:00'))->toBeTrue();
});

test('artisan command warns when no local cases exist', function () {
    $this->artisan('sf:compute-case-summaries')
        ->expectsOutputToContain('No local cases. Run php artisan sf:pull-cases first.')
        ->assertSuccessful();
});
