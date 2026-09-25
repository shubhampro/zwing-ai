<?php

use App\Models\SfCase;
use App\Models\SfCaseGroupHistory;
use App\Models\SfCaseGroupHold;
use Illuminate\Support\Carbon;

test('artisan command derives group holds from history hops', function () {
    $this->travelTo(Carbon::parse('2026-09-23 12:00:00'));

    $case = SfCase::factory()->create([
        'first_assigned_group' => 'ERP Helpdesk-L1',
        'group_name' => 'Zwing-Tech',
        'created_at_sf' => '2026-09-21 06:34:00',
        'resolved_at_sf' => '2026-09-22 10:07:00',
        'closed_at_sf' => '2026-09-22 10:07:00',
        'is_closed' => true,
    ]);

    SfCaseGroupHistory::factory()->create([
        'sf_case_id' => $case->id,
        'field' => 'Group__c',
        'old_value' => 'ERP Helpdesk-L1',
        'new_value' => 'ERP Helpdesk-L2',
        'changed_at' => '2026-09-22 08:06:00',
    ]);
    SfCaseGroupHistory::factory()->create([
        'sf_case_id' => $case->id,
        'field' => 'Group__c',
        'old_value' => 'ERP Helpdesk-L2',
        'new_value' => 'Zwing-Tech',
        'changed_at' => '2026-09-22 08:38:00',
    ]);
    SfCaseGroupHistory::factory()->create([
        'sf_case_id' => $case->id,
        'field' => 'Owner',
        'old_value' => 'Himanshi',
        'new_value' => 'ZPOS Tech/Dev',
        'changed_at' => '2026-09-22 08:38:00',
    ]);

    $this->artisan('sf:compute-case-holds')
        ->expectsOutputToContain('Computed 3 group holds.')
        ->assertSuccessful();

    $holds = SfCaseGroupHold::query()->orderBy('started_at')->get();

    expect($holds)->toHaveCount(3)
        ->and($holds[0]->sf_case_id)->toBe($case->id)
        ->and($holds[0]->group_name)->toBe('ERP Helpdesk-L1')
        ->and($holds[0]->started_at?->equalTo('2026-09-21 06:34:00'))->toBeTrue()
        ->and($holds[0]->ended_at?->equalTo('2026-09-22 08:06:00'))->toBeTrue()
        ->and($holds[0]->held_minutes)->toBe(1532)
        ->and($holds[0]->is_open)->toBeFalse()
        ->and($holds[1]->group_name)->toBe('ERP Helpdesk-L2')
        ->and($holds[1]->held_minutes)->toBe(32)
        ->and($holds[2]->group_name)->toBe('Zwing-Tech')
        ->and($holds[2]->ended_at?->equalTo('2026-09-22 10:07:00'))->toBeTrue()
        ->and($holds[2]->held_minutes)->toBe(89)
        ->and($holds[2]->is_open)->toBeFalse()
        ->and($holds[2]->case?->id)->toBe($case->id);

    $case->refresh();

    expect($case->resolution_minutes)->toBe(1653)
        ->and($case->zwing_resolution_minutes)->toBe(89);
});

test('artisan command marks last stint open when case is still open', function () {
    $this->travelTo(Carbon::parse('2026-09-23 12:00:00'));

    $case = SfCase::factory()->create([
        'first_assigned_group' => 'Zwing-Tech',
        'group_name' => 'Zwing-Tech',
        'created_at_sf' => '2026-09-22 10:00:00',
        'resolved_at_sf' => null,
        'closed_at_sf' => null,
        'is_closed' => false,
    ]);

    $this->artisan('sf:compute-case-holds')
        ->expectsOutputToContain('Computed 1 group holds.')
        ->assertSuccessful();

    $hold = SfCaseGroupHold::query()->first();

    expect($hold)->not->toBeNull()
        ->and($hold->sf_case_id)->toBe($case->id)
        ->and($hold->group_name)->toBe('Zwing-Tech')
        ->and($hold->ended_at?->equalTo('2026-09-23 12:00:00'))->toBeTrue()
        ->and($hold->held_minutes)->toBe(1560)
        ->and($hold->is_open)->toBeTrue();

    $case->refresh();

    expect($case->resolution_minutes)->toBeNull()
        ->and($case->zwing_resolution_minutes)->toBe(1560);
});

test('artisan command uses first assigned group when no history exists', function () {
    SfCase::factory()->create([
        'first_assigned_group' => 'ERP Helpdesk-L1',
        'group_name' => 'ERP Helpdesk-L1',
        'created_at_sf' => '2026-09-20 08:00:00',
        'closed_at_sf' => '2026-09-20 10:00:00',
        'resolved_at_sf' => '2026-09-20 10:00:00',
        'is_closed' => true,
    ]);

    $this->artisan('sf:compute-case-holds')
        ->assertSuccessful();

    $hold = SfCaseGroupHold::query()->first();

    expect(SfCaseGroupHold::query()->count())->toBe(1)
        ->and($hold?->group_name)->toBe('ERP Helpdesk-L1')
        ->and($hold?->held_minutes)->toBe(120)
        ->and($hold?->is_open)->toBeFalse();

    expect(SfCase::query()->first()?->resolution_minutes)->toBe(120)
        ->and(SfCase::query()->first()?->zwing_resolution_minutes)->toBe(0);
});

test('artisan command skips salesforce ids and same-group hops', function () {
    $case = SfCase::factory()->create([
        'first_assigned_group' => 'ERP Helpdesk-L1',
        'created_at_sf' => '2026-09-21 08:00:00',
        'closed_at_sf' => '2026-09-21 10:00:00',
        'resolved_at_sf' => '2026-09-21 10:00:00',
        'is_closed' => true,
    ]);

    SfCaseGroupHistory::factory()->create([
        'sf_case_id' => $case->id,
        'field' => 'Group__c',
        'old_value' => '00Gxx0000000000AAA',
        'new_value' => '00Gxx0000000000BBB',
        'changed_at' => '2026-09-21 09:00:00',
    ]);
    SfCaseGroupHistory::factory()->create([
        'sf_case_id' => $case->id,
        'field' => 'Group__c',
        'old_value' => 'ERP Helpdesk-L1',
        'new_value' => 'ERP Helpdesk-L1',
        'changed_at' => '2026-09-21 09:30:00',
    ]);

    $this->artisan('sf:compute-case-holds')
        ->expectsOutputToContain('Computed 1 group holds.')
        ->assertSuccessful();

    $hold = SfCaseGroupHold::query()->first();

    expect($hold?->group_name)->toBe('ERP Helpdesk-L1')
        ->and($hold?->started_at?->equalTo('2026-09-21 08:00:00'))->toBeTrue()
        ->and($hold?->ended_at?->equalTo('2026-09-21 10:00:00'))->toBeTrue();
});

test('artisan command dry run does not write holds', function () {
    SfCase::factory()->create([
        'first_assigned_group' => 'Zwing-Tech',
        'created_at_sf' => '2026-09-21 08:00:00',
        'closed_at_sf' => '2026-09-21 09:00:00',
    ]);

    $this->artisan('sf:compute-case-holds', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run: 1 group holds (not saved).')
        ->assertSuccessful();

    expect(SfCaseGroupHold::query()->count())->toBe(0)
        ->and(SfCase::query()->first()?->resolution_minutes)->toBeNull()
        ->and(SfCase::query()->first()?->zwing_resolution_minutes)->toBeNull();
});

test('artisan command rebuilds holds on rerun', function () {
    $case = SfCase::factory()->create([
        'first_assigned_group' => 'Zwing-Tech',
        'created_at_sf' => '2026-09-21 08:00:00',
        'closed_at_sf' => '2026-09-21 09:00:00',
        'resolved_at_sf' => '2026-09-21 09:00:00',
        'is_closed' => true,
    ]);
    SfCaseGroupHold::factory()->create([
        'sf_case_id' => $case->id,
        'group_name' => 'Stale Group',
        'held_minutes' => 1,
    ]);

    $this->artisan('sf:compute-case-holds')
        ->assertSuccessful();

    expect(SfCaseGroupHold::query()->count())->toBe(1)
        ->and(SfCaseGroupHold::query()->first()?->group_name)->toBe('Zwing-Tech')
        ->and(SfCaseGroupHold::query()->first()?->held_minutes)->toBe(60);
});

test('artisan command uses resolved date when case is not closed', function () {
    SfCase::factory()->create([
        'first_assigned_group' => 'Zwing-Tech',
        'created_at_sf' => '2026-09-21 08:00:00',
        'resolved_at_sf' => '2026-09-21 08:45:00',
        'closed_at_sf' => null,
        'is_closed' => false,
    ]);

    $this->artisan('sf:compute-case-holds')
        ->assertSuccessful();

    $hold = SfCaseGroupHold::query()->first();

    expect($hold?->ended_at?->equalTo('2026-09-21 08:45:00'))->toBeTrue()
        ->and($hold?->held_minutes)->toBe(45)
        ->and($hold?->is_open)->toBeFalse();
});

test('artisan command sums zwing visits and skips erp bounce', function () {
    $case = SfCase::factory()->create([
        'first_assigned_group' => 'Zwing-Tech',
        'group_name' => 'Zwing-Tech',
        'created_at_sf' => '2026-09-21 08:00:00',
        'resolved_at_sf' => '2026-09-21 12:00:00',
        'closed_at_sf' => '2026-09-21 12:00:00',
        'is_closed' => true,
    ]);

    SfCaseGroupHistory::factory()->create([
        'sf_case_id' => $case->id,
        'field' => 'Group__c',
        'old_value' => 'Zwing-Tech',
        'new_value' => 'ERP Tech',
        'changed_at' => '2026-09-21 09:00:00',
    ]);
    SfCaseGroupHistory::factory()->create([
        'sf_case_id' => $case->id,
        'field' => 'Group__c',
        'old_value' => 'ERP Tech',
        'new_value' => 'Zwing-Tech',
        'changed_at' => '2026-09-21 11:00:00',
    ]);

    $this->artisan('sf:compute-case-holds')
        ->assertSuccessful();

    $case->refresh();

    expect(SfCaseGroupHold::query()->count())->toBe(3)
        ->and($case->resolution_minutes)->toBe(240)
        ->and($case->zwing_resolution_minutes)->toBe(120);
});

test('artisan command warns when no local cases exist', function () {
    $this->artisan('sf:compute-case-holds')
        ->expectsOutputToContain('No local cases. Run php artisan sf:pull-cases first.')
        ->assertSuccessful();

    expect(SfCaseGroupHold::query()->count())->toBe(0);
});
