<?php

use App\Models\SfAccount;
use App\Models\SfCase;
use App\Models\SfCaseActivity;
use App\Models\SfCaseGroupHistory;
use App\Models\SfCaseGroupHold;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('guests are redirected away from the sf case list', function () {
    $this->get(route('sf-cases.index'))
        ->assertRedirect(route('login'));
});

test('guests are redirected away from an sf case detail', function () {
    $case = SfCase::factory()->create();

    $this->get(route('sf-cases.show', $case))
        ->assertRedirect(route('login'));
});

test('authenticated users see the sf case list', function () {
    $account = SfAccount::factory()->create(['name' => 'House of Anita']);

    SfCase::factory()->create([
        'case_number' => '00123456',
        'subject' => 'POS freeze on checkout',
        'status' => 'Open',
        'priority' => 'Urgent',
        'sf_account_id' => $account->id,
        'created_at_sf' => '2026-08-04 10:00:00',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sf-cases.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sf-cases/index')
            ->where('pagination.total', 1)
            ->where('cases.0.case_number', '00123456')
            ->where('cases.0.subject', 'POS freeze on checkout')
            ->where('cases.0.account_name', 'House of Anita')
            ->where('cases.0.status', 'Open')
            ->has('statuses')
            ->has('priorities')
            ->where('filters.q', ''));
});

test('search matches case number subject and account name', function () {
    $anita = SfAccount::factory()->create(['name' => 'House of Anita Dongre']);
    $other = SfAccount::factory()->create(['name' => 'DEMIFINE FASHION']);

    SfCase::factory()->create([
        'case_number' => '00111111',
        'subject' => 'Printer jam',
        'sf_account_id' => $anita->id,
        'created_at_sf' => '2026-08-04 10:00:00',
    ]);
    SfCase::factory()->create([
        'case_number' => '00222222',
        'subject' => 'Login timeout',
        'sf_account_id' => $other->id,
        'owner_name' => 'Ravi Kumar',
        'created_at_sf' => '2026-08-05 10:00:00',
    ]);

    $this->actingAs(User::factory()->create());

    $this->get(route('sf-cases.index', ['q' => '00111111']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pagination.total', 1)
            ->where('cases.0.case_number', '00111111')
            ->where('filters.q', '00111111'));

    $this->get(route('sf-cases.index', ['q' => 'login timeout']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pagination.total', 1)
            ->where('cases.0.case_number', '00222222'));

    $this->get(route('sf-cases.index', ['q' => 'anita']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pagination.total', 1)
            ->where('cases.0.account_name', 'House of Anita Dongre'));

    $this->get(route('sf-cases.index', ['q' => 'ravi']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pagination.total', 1)
            ->where('cases.0.case_number', '00222222'));
});

test('status and priority filters narrow the list', function () {
    SfCase::factory()->create([
        'case_number' => '00333333',
        'status' => 'Open',
        'priority' => 'Urgent',
        'created_at_sf' => '2026-08-04 10:00:00',
    ]);
    SfCase::factory()->create([
        'case_number' => '00444444',
        'status' => 'Closed',
        'priority' => 'Medium',
        'created_at_sf' => '2026-08-05 10:00:00',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sf-cases.index', ['status' => 'Open', 'priority' => 'Urgent']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pagination.total', 1)
            ->where('cases.0.case_number', '00333333')
            ->where('filters.status', 'Open')
            ->where('filters.priority', 'Urgent'));
});

test('authenticated users see ticket detail with holds history and activity', function () {
    $account = SfAccount::factory()->create(['name' => 'House of Anita']);
    $case = SfCase::factory()->create([
        'case_number' => '00555555',
        'subject' => 'Sync lag',
        'description' => 'Orders stuck after 8pm',
        'activity_summary' => implode("\n", [
            'Open. Opened 4 Aug 2026.',
            'Path: ERP Helpdesk-L1 → Zwing-Tech',
            'Ask: Orders stuck after 8pm',
            'Note: Please share store id',
            'Mail: 0 in / 1 out. Notes: 1.',
        ]),
        'sf_account_id' => $account->id,
        'created_at_sf' => '2026-08-04 10:00:00',
        'resolved_at_sf' => '2026-08-05 13:00:00',
        'first_assigned_group' => 'ERP Helpdesk-L1',
        'resolution_minutes' => 1440,
        'zwing_resolution_minutes' => 180,
    ]);
    SfCaseGroupHold::factory()->create([
        'sf_case_id' => $case->id,
        'group_name' => 'Zwing-Tech',
        'started_at' => '2026-08-04 10:00:00',
        'ended_at' => '2026-08-05 13:00:00',
        'held_minutes' => 180,
    ]);
    SfCaseGroupHistory::factory()->create([
        'sf_case_id' => $case->id,
        'old_value' => 'ERP Helpdesk-L1',
        'new_value' => 'Zwing-Tech',
    ]);
    SfCaseActivity::factory()->create([
        'sf_case_id' => $case->id,
        'body' => '<p>Please share <b>store id</b></p><script>alert(1)</script>',
        'is_incoming' => false,
        'occurred_at' => '2026-08-05 09:00:00',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sf-cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sf-cases/show')
            ->where('ticket.case_number', '00555555')
            ->where('ticket.subject', 'Sync lag')
            ->where('ticket.description', 'Orders stuck after 8pm')
            ->where('ticket.account.name', 'House of Anita')
            ->where('ticket.brief.status', 'Open. Opened 4 Aug 2026.')
            ->where('ticket.brief.path', ['ERP Helpdesk-L1', 'Zwing-Tech'])
            ->where('ticket.brief.ask', 'Orders stuck after 8pm')
            ->where('ticket.brief.note', 'Please share store id')
            ->where('ticket.brief.mail', '0 in / 1 out. Notes: 1.')
            ->where('ticket.resolution_minutes', 1440)
            ->where('ticket.zwing_resolution_minutes', 180)
            ->has('ticket.holds', 1)
            ->where('ticket.holds.0.group_name', 'Zwing-Tech')
            ->has('ticket.histories', 1)
            ->where('ticket.histories.0.new_value', 'Zwing-Tech')
            ->has('ticket.activities', 1)
            ->where('ticket.activities.0.body', '<p>Please share <b>store id</b></p>')
            ->where('ticket.activities.0.preview', 'Please share store id')
            ->has('ticket.timeline', 4)
            ->where('ticket.timeline.0.kind', 'opened')
            ->where('ticket.timeline.0.body', 'Orders stuck after 8pm')
            ->where('ticket.timeline.1.kind', 'path')
            ->where('ticket.timeline.1.title', 'Zwing-Tech')
            ->where('ticket.timeline.2.kind', 'note')
            ->where('ticket.timeline.2.body', 'Please share store id')
            ->where('ticket.timeline.3.kind', 'closed'));
});

test('unknown case number returns not found', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('sf-cases.show', ['sfCase' => '00999999']))
        ->assertNotFound();
});
