<?php

use App\Models\SfAccount;
use App\Models\SfCase;
use App\Models\SfCaseActivity;
use App\Models\SfCaseGroupHold;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('guests are redirected away from the zwing mbr index', function () {
    $this->get(route('sf-mbr.index'))
        ->assertRedirect(route('login'));
});

test('guests are redirected away from a zwing mbr month', function () {
    $this->get(route('sf-mbr.show', ['month' => '2026-08']))
        ->assertRedirect(route('login'));
});

test('authenticated users see the zwing mbr month index', function () {
    SfCase::factory()->create([
        'created_at_sf' => '2026-07-04 10:00:00',
        'resolved_at_sf' => '2026-07-05 10:00:00',
        'closed_at_sf' => '2026-07-05 10:00:00',
        'is_closed' => true,
    ]);
    SfCase::factory()->create([
        'created_at_sf' => '2026-08-04 10:00:00',
        'resolved_at_sf' => '2026-08-05 10:00:00',
        'closed_at_sf' => '2026-08-05 10:00:00',
        'is_closed' => true,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sf-mbr.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sf-mbr/index')
            ->where('product', 'Zwing')
            ->has('months')
            ->where('months.0.key', '2026-08')
            ->where('months.0.new_tickets', 1)
            ->where('months.1.key', '2026-07')
            ->where('months.1.new_tickets', 1));
});

test('authenticated users see the zwing monthly support dashboard', function () {
    $anita = SfAccount::factory()->create(['name' => 'House of Anita Dongre Private Limited-New']);
    $demifine = SfAccount::factory()->create(['name' => 'DEMIFINE FASHION PRIVATE LIMITED']);
    $testAccount = SfAccount::factory()->create(['name' => 'Internal Test Org']);

    SfCase::factory()->create([
        'product' => 'ERP',
        'priority' => 'Urgent',
        'type' => 'Incident',
        'sf_account_id' => $anita->id,
        'first_assigned_group' => 'ERP Helpdesk-L1',
        'group_name' => 'ERP Helpdesk-L1',
        'created_at_sf' => '2026-08-03 10:00:00',
        'resolved_at_sf' => '2026-08-03 18:00:00',
        'closed_at_sf' => '2026-08-03 18:00:00',
        'is_closed' => true,
    ]);

    SfCase::factory()->create([
        'product' => 'Zwing',
        'is_spam' => true,
        'priority' => 'Urgent',
        'type' => 'Incident',
        'sf_account_id' => $anita->id,
        'first_assigned_group' => 'ERP Helpdesk-L1',
        'group_name' => 'ERP Helpdesk-L1',
        'created_at_sf' => '2026-08-03 10:00:00',
        'resolved_at_sf' => '2026-08-03 18:00:00',
        'closed_at_sf' => '2026-08-03 18:00:00',
        'is_closed' => true,
    ]);

    $carried = SfCase::factory()->create([
        'product' => 'Zwing',
        'priority' => 'Medium',
        'type' => 'Incident',
        'sf_account_id' => $anita->id,
        'first_assigned_group' => 'ERP Helpdesk-L1',
        'group_name' => 'Zwing-Tech',
        'created_at_sf' => '2026-07-20 09:00:00',
        'resolved_at_sf' => '2026-08-10 12:00:00',
        'closed_at_sf' => '2026-08-10 12:00:00',
        'is_closed' => true,
    ]);

    SfCaseGroupHold::factory()->create([
        'sf_case_id' => $carried->id,
        'group_name' => 'ERP Helpdesk-L1',
        'held_minutes' => 1440,
        'started_at' => '2026-07-20 09:00:00',
        'ended_at' => '2026-07-21 09:00:00',
        'is_open' => false,
        'computed_at' => now(),
    ]);
    SfCaseGroupHold::factory()->create([
        'sf_case_id' => $carried->id,
        'group_name' => 'Zwing-Tech',
        'held_minutes' => 2880,
        'started_at' => '2026-07-21 09:00:00',
        'ended_at' => '2026-08-10 12:00:00',
        'is_open' => false,
        'computed_at' => now(),
    ]);

    $l1Only = SfCase::factory()->create([
        'product' => 'Zwing',
        'priority' => 'Urgent',
        'type' => 'Incident',
        'sf_account_id' => $anita->id,
        'first_assigned_group' => 'ERP Helpdesk-L1',
        'group_name' => 'ERP Helpdesk-L1',
        'created_at_sf' => '2026-08-02 09:00:00',
        'resolved_at_sf' => '2026-08-02 16:00:00',
        'closed_at_sf' => '2026-08-02 16:00:00',
        'is_closed' => true,
    ]);
    SfCaseGroupHold::factory()->create([
        'sf_case_id' => $l1Only->id,
        'group_name' => 'ERP Helpdesk-L1',
        'held_minutes' => 420,
        'started_at' => '2026-08-02 09:00:00',
        'ended_at' => '2026-08-02 16:00:00',
        'is_open' => false,
        'computed_at' => now(),
    ]);

    $toL3 = SfCase::factory()->create([
        'product' => 'Zwing',
        'priority' => 'High',
        'type' => 'Incident',
        'sf_account_id' => $demifine->id,
        'first_assigned_group' => 'ERP Helpdesk-L1',
        'group_name' => 'Zwing-Tech',
        'created_at_sf' => '2026-08-01 08:00:00',
        'resolved_at_sf' => '2026-08-20 08:00:00',
        'closed_at_sf' => '2026-08-20 08:00:00',
        'is_closed' => true,
    ]);
    SfCaseGroupHold::factory()->create([
        'sf_case_id' => $toL3->id,
        'group_name' => 'Zwing-Tech',
        'held_minutes' => 7200,
        'started_at' => '2026-08-01 08:00:00',
        'ended_at' => '2026-08-20 08:00:00',
        'is_open' => false,
        'computed_at' => now(),
    ]);

    SfCase::factory()->create([
        'product' => 'Zwing',
        'priority' => 'Medium',
        'type' => 'Question',
        'sf_account_id' => $demifine->id,
        'first_assigned_group' => 'ERP Helpdesk-L1',
        'group_name' => 'ERP Helpdesk-L1',
        'created_at_sf' => '2026-08-15 10:00:00',
        'resolved_at_sf' => null,
        'closed_at_sf' => null,
        'is_closed' => false,
    ]);

    SfCase::factory()->create([
        'product' => 'Zwing',
        'priority' => 'Medium',
        'type' => 'Others',
        'sf_account_id' => $testAccount->id,
        'first_assigned_group' => 'Zwing BA/Product',
        'group_name' => 'Zwing BA/Product',
        'created_at_sf' => '2026-08-12 10:00:00',
        'resolved_at_sf' => '2026-08-12 18:00:00',
        'closed_at_sf' => '2026-08-12 18:00:00',
        'is_closed' => true,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sf-mbr.show', ['month' => '2026-08']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sf-mbr/show')
            ->where('product', 'Zwing')
            ->where('month', '2026-08')
            ->where('highlights.new_tickets', 4)
            ->where('highlights.carry_forward', 1)
            ->where('highlights.resolved_pct', 0.8)
            ->where('highlights.active_customers', 2)
            ->where('highlights.urgent_sla_breach_pct', 0)
            ->where('highlights.zwing_urgent_sla_breach_pct', 0)
            ->where('highlights.integration', 0)
            ->where('highlights.fcr_pct', 0.5)
            ->where('owner', 'Customer Support Team')
            ->where('quality.csat_available', false)
            ->where('quality.repeat_customers', 1)
            ->where('waterfall.received', 4)
            ->where('waterfall.to_ba', 1)
            ->where('waterfall.resolved_l1', 2)
            ->where('waterfall.to_l3', 1)
            ->where('speed.sla_by_priority.0.priority', 'Urgent')
            ->where('speed.frt_median_hours', null)
            ->where('top_customers.0.account_name', 'DEMIFINE FASHION PRIVATE LIMITED')
            ->where('top_customers.0.tickets', 2)
            ->where('holds.0.group_name', 'Zwing-Tech')
            ->has('volume')
            ->has('demand.type_mix')
            ->has('demand.channel_mix')
            ->has('people')
            ->has('product_mix')
            ->has('risks')
            ->has('actions')
            ->has('concentration', 3));
});

test('zwing urgent sla uses zwing hold minutes not overall clock', function () {
    $account = SfAccount::factory()->create(['name' => 'Acme Retail']);

    SfCase::factory()->create([
        'priority' => 'Urgent',
        'type' => 'Incident',
        'sf_account_id' => $account->id,
        'created_at_sf' => '2026-08-01 08:00:00',
        'resolved_at_sf' => '2026-08-10 08:00:00',
        'closed_at_sf' => '2026-08-10 08:00:00',
        'is_closed' => true,
        'zwing_resolution_minutes' => 600,
    ]);

    SfCase::factory()->create([
        'priority' => 'Urgent',
        'type' => 'Incident',
        'sf_account_id' => $account->id,
        'created_at_sf' => '2026-08-01 08:00:00',
        'resolved_at_sf' => '2026-08-10 08:00:00',
        'closed_at_sf' => '2026-08-10 08:00:00',
        'is_closed' => true,
        'zwing_resolution_minutes' => 3000,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sf-mbr.show', ['month' => '2026-08']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('highlights.urgent_sla_breach_pct', 1)
            ->where('highlights.zwing_urgent_sla_breach_pct', 0.5)
            ->where('speed.sla_by_priority.0.priority', 'Urgent')
            ->where('speed.sla_by_priority.0.sla_breach_pct', 1)
            ->where('speed.sla_by_priority.0.zwing_sla_breach_pct', 0.5));
});

test('zwing urgent sla falls back to zwing-tech hold minutes', function () {
    $account = SfAccount::factory()->create(['name' => 'Hold Fallback']);

    $case = SfCase::factory()->create([
        'priority' => 'Urgent',
        'type' => 'Incident',
        'sf_account_id' => $account->id,
        'created_at_sf' => '2026-08-02 09:00:00',
        'resolved_at_sf' => '2026-08-02 16:00:00',
        'closed_at_sf' => '2026-08-02 16:00:00',
        'is_closed' => true,
        'zwing_resolution_minutes' => null,
    ]);

    SfCaseGroupHold::factory()->create([
        'sf_case_id' => $case->id,
        'group_name' => 'Zwing-Tech',
        'held_minutes' => 2000,
        'started_at' => '2026-08-02 09:00:00',
        'ended_at' => '2026-08-03 18:20:00',
        'is_open' => false,
        'computed_at' => now(),
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sf-mbr.show', ['month' => '2026-08']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('highlights.urgent_sla_breach_pct', 0)
            ->where('highlights.zwing_urgent_sla_breach_pct', 1));
});

test('first response time uses first outbound activity', function () {
    $account = SfAccount::factory()->create(['name' => 'FRT Co']);

    $case = SfCase::factory()->create([
        'priority' => 'Medium',
        'sf_account_id' => $account->id,
        'created_at_sf' => '2026-08-01 10:00:00',
        'resolved_at_sf' => '2026-08-01 18:00:00',
        'closed_at_sf' => '2026-08-01 18:00:00',
        'is_closed' => true,
    ]);

    SfCaseActivity::factory()->create([
        'sf_case_id' => $case->id,
        'is_incoming' => true,
        'occurred_at' => '2026-08-01 10:30:00',
    ]);

    SfCaseActivity::factory()->create([
        'sf_case_id' => $case->id,
        'is_incoming' => false,
        'occurred_at' => '2026-08-01 14:00:00',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sf-mbr.show', ['month' => '2026-08']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('speed.frt_median_hours', 4)
            ->where('speed.frt_coverage_pct', 1)
            ->where('highlights.frt_median_hours', 4));
});

test('unknown month formats return not found', function () {
    $this->actingAs(User::factory()->create())
        ->get('/sf-mbr/august')
        ->assertNotFound();
});
