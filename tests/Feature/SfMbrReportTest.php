<?php

use App\Enums\SfMbrReportSection;
use App\Enums\SfMbrReportStatus;
use App\Jobs\SegregateSfMbrAccountsJob;
use App\Models\SfAccount;
use App\Models\SfApplication;
use App\Models\SfCase;
use App\Models\SfMbrAccount;
use App\Models\SfMbrReport;
use App\Models\SfModule;
use App\Models\User;
use App\Services\Salesforce\SfMbrAccountSegregator;
use App\Support\IndiaDateTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function istUtc(string $datetime): string
{
    return Carbon::parse($datetime, IndiaDateTime::TIMEZONE)->utc()->toDateTimeString();
}

test('guests are redirected away from mbr reports', function () {
    $this->get(route('sf-mbr.index'))->assertRedirect(route('login'));
    $this->get(route('sf-mbr.create'))->assertRedirect(route('login'));

    $report = SfMbrReport::factory()->create();

    $this->get(route('sf-mbr.show', $report))->assertRedirect(route('login'));
    $this->get(route('sf-mbr.summary', $report))->assertRedirect(route('login'));
    $this->get(route('sf-mbr.details', $report))->assertRedirect(route('login'));
    $this->delete(route('sf-mbr.destroy', $report))->assertRedirect(route('login'));
});

test('authenticated users see the mbr report index', function () {
    $user = User::factory()->create(['name' => 'Anita']);
    $application = SfApplication::factory()->create(['name' => 'Zwing (Cloud POS)']);

    $report = SfMbrReport::factory()->create([
        'user_id' => $user->id,
        'title' => 'Zwing (Cloud POS) · 1 Mar 2026 – 2 Sep 2026',
        'starts_on' => '2026-03-01',
        'ends_on' => '2026-09-02',
    ]);
    $report->applications()->attach($application);

    $this->actingAs($user)
        ->get(route('sf-mbr.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sf-mbr/index')
            ->where('reports.0.title', 'Zwing (Cloud POS) · 1 Mar 2026 – 2 Sep 2026')
            ->where('reports.0.applications.0', 'Zwing (Cloud POS)')
            ->where('reports.0.all_applications', false)
            ->where('reports.0.created_by', 'Anita'));
});

test('create page lists active applications', function () {
    SfApplication::factory()->create(['name' => 'Zwing (Cloud POS)', 'is_active' => true]);
    SfApplication::factory()->create(['name' => 'Retired App', 'is_active' => false]);

    $this->actingAs(User::factory()->create())
        ->get(route('sf-mbr.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sf-mbr/create')
            ->where('applications.0.name', 'Zwing (Cloud POS)')
            ->has('applications', 1)
            ->has('defaults.starts_on')
            ->has('defaults.ends_on'));
});

test('store auto generates a title and keeps the selected applications', function () {
    Queue::fake();

    $application = SfApplication::factory()->create(['name' => 'Zwing (Cloud POS)']);

    $this->actingAs(User::factory()->create())
        ->post(route('sf-mbr.store'), [
            'starts_on' => '2026-03-01',
            'ends_on' => '2026-09-2',
            'application_ids' => [$application->id],
        ])
        ->assertRedirect();

    $report = SfMbrReport::query()->first();

    expect($report)->not->toBeNull()
        ->and($report->title)->toBe('Zwing (Cloud POS) · 1 Mar 2026 – 2 Sep 2026')
        ->and($report->starts_on->toDateString())->toBe('2026-03-01')
        ->and($report->ends_on->toDateString())->toBe('2026-09-02')
        ->and($report->status)->toBe(SfMbrReportStatus::Generating)
        ->and($report->current_section)->toBe(SfMbrReportSection::SegregateAccounts);

    $this->assertTrue($report->applications->contains($application));

    Queue::assertPushed(
        SegregateSfMbrAccountsJob::class,
        fn (SegregateSfMbrAccountsJob $job): bool => $job->sfMbrReportId === $report->id,
    );
});

test('store with no applications means all applications', function () {
    Queue::fake();

    SfApplication::factory()->create(['name' => 'Zwing (Cloud POS)']);

    $this->actingAs(User::factory()->create())
        ->post(route('sf-mbr.store'), [
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-31',
        ])
        ->assertRedirect();

    $report = SfMbrReport::query()->first();

    expect($report->title)->toBe('All applications · 1 Aug 2026 – 31 Aug 2026')
        ->and($report->applications)->toHaveCount(0);
});

test('store rejects inactive applications', function () {
    $inactive = SfApplication::factory()->create(['is_active' => false]);

    $this->actingAs(User::factory()->create())
        ->post(route('sf-mbr.store'), [
            'starts_on' => '2026-03-01',
            'ends_on' => '2026-03-31',
            'application_ids' => [$inactive->id],
        ])
        ->assertSessionHasErrors('application_ids.0');

    expect(SfMbrReport::query()->count())->toBe(0);
});

test('store rejects a from date after to', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('sf-mbr.store'), [
            'starts_on' => '2026-09-02',
            'ends_on' => '2026-03-01',
        ])
        ->assertSessionHasErrors('ends_on');

    expect(SfMbrReport::query()->count())->toBe(0);
});

test('show page exposes generation progress without old summary props', function () {
    $report = SfMbrReport::factory()->create([
        'title' => 'All applications · 1 Mar 2026 – 31 Mar 2026',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sf-mbr.show', $report))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sf-mbr/show')
            ->where('report.title', 'All applications · 1 Mar 2026 – 31 Mar 2026')
            ->where('report.all_applications', true)
            ->where('report.status', 'ready')
            ->where('generation.sections.0.key', 'segregate_accounts')
            ->where('generation.sections.0.label', 'Segregate Accounts')
            ->where('generation.sections.0.status', 'done')
            ->missing('generation.sections.0.totals')
            ->missing('accounts')
            ->missing('summary')
            ->missing('highlights')
            ->missing('speed')
            ->missing('quality')
            ->missing('customer_load'));
});

test('details page lists segregated account rows', function () {
    $report = SfMbrReport::factory()->create([
        'status' => SfMbrReportStatus::Ready,
    ]);

    SfMbrAccount::factory()->create([
        'sf_mbr_report_id' => $report->id,
        'backlog_ticket_count' => 2,
        'created_ticket_count' => 5,
        'resolved_or_closed_count' => 4,
        'open_ticket_count' => 3,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sf-mbr.details', $report))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sf-mbr/details')
            ->where('report.status', 'ready')
            ->where('accounts.0.backlog_ticket_count', 2)
            ->where('accounts.0.created_ticket_count', 5)
            ->where('accounts.0.resolved_or_closed_count', 4)
            ->where('accounts.0.open_ticket_count', 3));
});

test('show page exposes running section status without counts', function () {
    $report = SfMbrReport::factory()->create([
        'status' => SfMbrReportStatus::Generating,
        'current_section' => SfMbrReportSection::SegregateAccounts,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sf-mbr.show', $report))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sf-mbr/show')
            ->where('generation.sections.0.status', 'running')
            ->missing('accounts'));
});

test('overall summary page rolls account counts into the report widget', function () {
    $report = SfMbrReport::factory()->create([
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
        'status' => SfMbrReportStatus::Ready,
    ]);

    SfMbrAccount::factory()->create([
        'sf_mbr_report_id' => $report->id,
        'backlog_ticket_count' => 877,
        'created_ticket_count' => 4375,
        'resolved_or_closed_count' => 4518,
        'open_ticket_count' => 734,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sf-mbr.summary', $report))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sf-mbr/summary')
            ->where('report.title', $report->title)
            ->where('summary.title', 'Ticket Volume, Closure and Carry-forward')
            ->where('summary.period_label', 'August 2026')
            ->where('summary.new_tickets', 4375)
            ->where('summary.workable_pool', 5252)
            ->where('summary.closed_or_resolved', 4518)
            ->where('summary.resolved_percent', 86.02)
            ->where('summary.carry_forward', 734)
            ->where('summary.help.new_tickets', 'Tickets created during this report IST range. Spam and tickets without an account or application are excluded.')
            ->where('summary.help.workable_pool', 'Opening backlog (still open at range start) plus new tickets. This is the full set the team could work, including carry-forward.')
            ->where('summary.help.closed_or_resolved', 'Distinct tickets resolved or closed during the range, including backlog that closed in this period.')
            ->where('summary.help.resolved_percent', 'Closed/Resolved divided by Workable pool. Empty when the pool is zero.')
            ->where('summary.help.carry_forward', 'Tickets still open at range end. These become next period backlog.')
            ->where('sla.title', 'SLA Breach, Priority Wise, Application Wise')
            ->where('sla.period_label', 'August 2026')
            ->where('sla.groups.0.priority', 'Urgent')
            ->where('sla.groups.0.totals.pool', 0)
            ->where('sla.groups.1.priority', 'High')
            ->where('sla.groups.2.priority', 'Medium')
            ->where('filters.all_applications', true)
            ->where('filters.all_modules', true)
            ->where('filters.include_no_module', true)
            ->has('filter_options.applications')
            ->has('filter_options.modules'));
});

test('overall summary page uses a range label across months', function () {
    $report = SfMbrReport::factory()->create([
        'starts_on' => '2026-03-01',
        'ends_on' => '2026-09-02',
        'status' => SfMbrReportStatus::Ready,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sf-mbr.summary', $report))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sf-mbr/summary')
            ->where('summary.period_label', '1 Mar 2026 – 2 Sep 2026')
            ->where('summary.resolved_percent', null));
});

test('overall summary page waits while the report is generating', function () {
    $report = SfMbrReport::factory()->create([
        'status' => SfMbrReportStatus::Generating,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sf-mbr.summary', $report))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sf-mbr/summary')
            ->where('summary', null)
            ->where('sla', null)
            ->where('report.status', 'generating'));
});

test('segregate job counts created, resolved or closed, and still-open tickets', function () {
    $pos = SfApplication::factory()->create(['name' => 'Zwing (Cloud POS)']);
    $console = SfApplication::factory()->create(['name' => 'Zwing Console']);
    $other = SfApplication::factory()->create(['name' => 'Other App']);
    $acme = SfAccount::factory()->create(['name' => 'Acme']);
    $beta = SfAccount::factory()->create(['name' => 'Beta']);

    $report = SfMbrReport::factory()->create([
        'starts_on' => '2026-03-01',
        'ends_on' => '2026-03-31',
        'status' => SfMbrReportStatus::Generating,
        'current_section' => SfMbrReportSection::SegregateAccounts,
    ]);
    $report->applications()->attach([$pos->id, $console->id]);

    SfCase::factory()->create([
        'sf_account_id' => $acme->id,
        'sf_application_id' => $pos->id,
        'created_at_sf' => istUtc('2026-03-15 10:00:00'),
        'resolved_at_sf' => null,
        'closed_at_sf' => null,
    ]);

    SfCase::factory()->create([
        'sf_account_id' => $acme->id,
        'sf_application_id' => $pos->id,
        'created_at_sf' => istUtc('2026-02-10 10:00:00'),
        'resolved_at_sf' => istUtc('2026-03-20 16:00:00'),
        'closed_at_sf' => istUtc('2026-03-20 16:30:00'),
        'is_closed' => true,
    ]);

    SfCase::factory()->create([
        'sf_account_id' => $acme->id,
        'sf_application_id' => $console->id,
        'created_at_sf' => istUtc('2026-01-05 09:00:00'),
        'resolved_at_sf' => null,
        'closed_at_sf' => null,
    ]);

    SfCase::factory()->create([
        'sf_account_id' => $beta->id,
        'sf_application_id' => $pos->id,
        'created_at_sf' => istUtc('2026-03-02 11:00:00'),
        'resolved_at_sf' => istUtc('2026-03-04 11:00:00'),
        'closed_at_sf' => istUtc('2026-03-04 11:30:00'),
        'is_closed' => true,
    ]);

    SfCase::factory()->create([
        'sf_account_id' => $acme->id,
        'sf_application_id' => $pos->id,
        'is_spam' => true,
        'created_at_sf' => istUtc('2026-03-12 10:00:00'),
    ]);

    SfCase::factory()->create([
        'sf_account_id' => $acme->id,
        'sf_application_id' => $other->id,
        'created_at_sf' => istUtc('2026-03-12 10:00:00'),
    ]);

    SfCase::factory()->create([
        'sf_account_id' => $acme->id,
        'sf_application_id' => $pos->id,
        'created_at_sf' => istUtc('2026-01-01 10:00:00'),
        'resolved_at_sf' => istUtc('2026-02-01 10:00:00'),
        'closed_at_sf' => istUtc('2026-02-01 10:30:00'),
        'is_closed' => true,
    ]);

    SfCase::factory()->create([
        'sf_account_id' => $acme->id,
        'sf_application_id' => $pos->id,
        'created_at_sf' => istUtc('2026-04-01 00:00:00'),
    ]);

    (new SegregateSfMbrAccountsJob($report->id))->handle(app(SfMbrAccountSegregator::class));

    $report->refresh();

    expect($report->status)->toBe(SfMbrReportStatus::Ready)
        ->and($report->accounts)->toHaveCount(3);

    $acmePos = SfMbrAccount::query()
        ->where('sf_mbr_report_id', $report->id)
        ->where('sf_account_id', $acme->id)
        ->where('sf_application_id', $pos->id)
        ->first();

    $acmeConsole = SfMbrAccount::query()
        ->where('sf_mbr_report_id', $report->id)
        ->where('sf_account_id', $acme->id)
        ->where('sf_application_id', $console->id)
        ->first();

    $betaPos = SfMbrAccount::query()
        ->where('sf_mbr_report_id', $report->id)
        ->where('sf_account_id', $beta->id)
        ->where('sf_application_id', $pos->id)
        ->first();

    expect($acmePos)->not->toBeNull()
        ->and($acmePos->backlog_ticket_count)->toBe(1)
        ->and($acmePos->created_ticket_count)->toBe(1)
        ->and($acmePos->resolved_or_closed_count)->toBe(1)
        ->and($acmePos->open_ticket_count)->toBe(1)
        ->and($acmeConsole->backlog_ticket_count)->toBe(1)
        ->and($acmeConsole->created_ticket_count)->toBe(0)
        ->and($acmeConsole->resolved_or_closed_count)->toBe(0)
        ->and($acmeConsole->open_ticket_count)->toBe(1)
        ->and($betaPos->backlog_ticket_count)->toBe(0)
        ->and($betaPos->created_ticket_count)->toBe(1)
        ->and($betaPos->resolved_or_closed_count)->toBe(1)
        ->and($betaPos->open_ticket_count)->toBe(0)
        ->and($acmePos->backlog_ticket_count + $acmePos->created_ticket_count)
        ->toBe($acmePos->resolved_or_closed_count + $acmePos->open_ticket_count)
        ->and($acmeConsole->backlog_ticket_count + $acmeConsole->created_ticket_count)
        ->toBe($acmeConsole->resolved_or_closed_count + $acmeConsole->open_ticket_count)
        ->and($betaPos->backlog_ticket_count + $betaPos->created_ticket_count)
        ->toBe($betaPos->resolved_or_closed_count + $betaPos->open_ticket_count);
});

test('overall summary sla table groups workable pool by priority and application', function () {
    $pos = SfApplication::factory()->create([
        'name' => 'Ginesys (Desktop POS)',
        'sort_order' => 1,
    ]);
    $erp = SfApplication::factory()->create([
        'name' => 'Ginesys (ERP)',
        'sort_order' => 0,
    ]);
    $other = SfApplication::factory()->create(['name' => 'Qlik Report (BI)']);
    $account = SfAccount::factory()->create();

    $report = SfMbrReport::factory()->create([
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
        'status' => SfMbrReportStatus::Ready,
    ]);
    $report->applications()->attach([$pos->id, $erp->id]);

    SfCase::factory()->create([
        'sf_account_id' => $account->id,
        'sf_application_id' => $pos->id,
        'priority' => 'Urgent',
        'created_at_sf' => istUtc('2026-07-01 10:00:00'),
        'resolved_at_sf' => null,
        'closed_at_sf' => null,
        'resolution_minutes' => null,
    ]);

    SfCase::factory()->create([
        'sf_account_id' => $account->id,
        'sf_application_id' => $pos->id,
        'priority' => 'Urgent',
        'created_at_sf' => istUtc('2026-08-02 10:00:00'),
        'resolved_at_sf' => istUtc('2026-08-02 10:10:00'),
        'closed_at_sf' => istUtc('2026-08-02 10:10:00'),
        'resolution_minutes' => 10,
    ]);

    SfCase::factory()->create([
        'sf_account_id' => $account->id,
        'sf_application_id' => $erp->id,
        'priority' => 'Urgent',
        'created_at_sf' => istUtc('2026-08-10 09:00:00'),
        'resolved_at_sf' => istUtc('2026-08-12 10:00:00'),
        'closed_at_sf' => null,
        'resolution_minutes' => 2940,
    ]);

    SfCase::factory()->create([
        'sf_account_id' => $account->id,
        'sf_application_id' => $erp->id,
        'priority' => 'High',
        'created_at_sf' => istUtc('2026-08-01 10:00:00'),
        'resolved_at_sf' => null,
        'closed_at_sf' => null,
    ]);

    SfCase::factory()->create([
        'sf_account_id' => $account->id,
        'sf_application_id' => $pos->id,
        'priority' => 'Low',
        'created_at_sf' => istUtc('2026-08-05 10:00:00'),
    ]);

    SfCase::factory()->create([
        'sf_account_id' => $account->id,
        'sf_application_id' => $pos->id,
        'priority' => 'Urgent',
        'is_spam' => true,
        'created_at_sf' => istUtc('2026-08-05 10:00:00'),
    ]);

    SfCase::factory()->create([
        'sf_account_id' => null,
        'sf_application_id' => $pos->id,
        'priority' => 'Urgent',
        'created_at_sf' => istUtc('2026-08-05 10:00:00'),
    ]);

    SfCase::factory()->create([
        'sf_account_id' => $account->id,
        'sf_application_id' => $other->id,
        'priority' => 'Urgent',
        'created_at_sf' => istUtc('2026-08-05 10:00:00'),
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sf-mbr.summary', $report))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sf-mbr/summary')
            ->where('sla.title', 'SLA Breach, Priority Wise, Application Wise')
            ->where('sla.help.pool', 'Workable pool for this priority and application: opening backlog plus tickets created in the report IST range. Spam, Low priority, and tickets without an account or application are excluded.')
            ->where('sla.groups.0.priority', 'Urgent')
            ->where('sla.groups.0.sla_label', 'Urgent (24 Hrs)')
            ->where('sla.groups.0.rows.0.application', 'Ginesys (ERP)')
            ->where('sla.groups.0.rows.0.pool', 1)
            ->where('sla.groups.0.rows.0.sla_breach', 1)
            ->where('sla.groups.0.rows.0.breach_percent', 100)
            ->where('sla.groups.0.rows.1.application', 'Ginesys (Desktop POS)')
            ->where('sla.groups.0.rows.1.pool', 2)
            ->where('sla.groups.0.rows.1.sla_breach', 1)
            ->where('sla.groups.0.rows.1.breach_percent', 50)
            ->where('sla.groups.0.totals.pool', 3)
            ->where('sla.groups.0.totals.sla_breach', 2)
            ->where('sla.groups.0.totals.breach_percent', 66.67)
            ->where('sla.groups.1.priority', 'High')
            ->where('sla.groups.1.rows.0.application', 'Ginesys (ERP)')
            ->where('sla.groups.1.rows.0.pool', 1)
            ->where('sla.groups.1.rows.0.sla_breach', 1)
            ->where('sla.groups.1.totals.pool', 1)
            ->where('sla.groups.2.priority', 'Medium')
            ->where('sla.groups.2.rows', [])
            ->where('sla.groups.2.totals.pool', 0)
            ->where('sla.groups.2.totals.breach_percent', null)
            ->missing('sla.groups.0.rows.2'));
});

test('overall summary application and module filters recompute volume and sla from cases', function () {
    $zwing = SfApplication::factory()->create([
        'name' => 'Zwing (Cloud POS)',
        'sort_order' => 0,
    ]);
    $erp = SfApplication::factory()->create([
        'name' => 'Ginesys (ERP)',
        'sort_order' => 1,
    ]);
    $sso = SfModule::factory()->create(['name' => 'SSO Service', 'sort_order' => 0]);
    $console = SfModule::factory()->create(['name' => 'Zwing Console', 'sort_order' => 1]);
    $account = SfAccount::factory()->create();

    $report = SfMbrReport::factory()->create([
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
        'status' => SfMbrReportStatus::Ready,
    ]);

    SfMbrAccount::factory()->create([
        'sf_mbr_report_id' => $report->id,
        'created_ticket_count' => 99,
        'backlog_ticket_count' => 1,
        'resolved_or_closed_count' => 50,
        'open_ticket_count' => 50,
    ]);

    SfCase::factory()->create([
        'sf_account_id' => $account->id,
        'sf_application_id' => $zwing->id,
        'sf_module_id' => $sso->id,
        'priority' => 'Urgent',
        'created_at_sf' => istUtc('2026-08-02 10:00:00'),
        'resolved_at_sf' => istUtc('2026-08-02 10:10:00'),
        'closed_at_sf' => istUtc('2026-08-02 10:10:00'),
        'resolution_minutes' => 10,
    ]);

    SfCase::factory()->create([
        'sf_account_id' => $account->id,
        'sf_application_id' => $zwing->id,
        'sf_module_id' => $console->id,
        'priority' => 'Urgent',
        'created_at_sf' => istUtc('2026-08-03 10:00:00'),
        'resolved_at_sf' => null,
        'closed_at_sf' => null,
    ]);

    SfCase::factory()->create([
        'sf_account_id' => $account->id,
        'sf_application_id' => $erp->id,
        'priority' => 'High',
        'created_at_sf' => istUtc('2026-08-04 10:00:00'),
        'resolved_at_sf' => null,
        'closed_at_sf' => null,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sf-mbr.summary', [
            'sfMbrReport' => $report,
            'application_ids' => [$zwing->id],
            'module_ids' => [$sso->id],
            'include_no_module' => 0,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sf-mbr/summary')
            ->where('filters.application_ids', [$zwing->id])
            ->where('filters.module_ids', [$sso->id])
            ->where('filters.include_no_module', false)
            ->where('filters.all_applications', false)
            ->where('filters.all_modules', false)
            ->where('filter_options.applications.0.name', 'Zwing (Cloud POS)')
            ->where('filter_options.modules.0.name', 'SSO Service')
            ->where('filter_options.modules.1.name', 'Zwing Console')
            ->where('summary.new_tickets', 1)
            ->where('summary.workable_pool', 1)
            ->where('summary.closed_or_resolved', 1)
            ->where('summary.carry_forward', 0)
            ->where('sla.groups.0.rows.0.application', 'Zwing (Cloud POS)')
            ->where('sla.groups.0.rows.0.pool', 1)
            ->where('sla.groups.0.rows.0.sla_breach', 0)
            ->where('sla.groups.0.totals.pool', 1)
            ->where('sla.groups.1.totals.pool', 0)
            ->missing('sla.groups.0.rows.1'));
});

test('overall summary no-module filter excludes tickets that have a module', function () {
    $zwing = SfApplication::factory()->create(['name' => 'Zwing (Cloud POS)']);
    $sso = SfModule::factory()->create(['name' => 'SSO Service']);
    $account = SfAccount::factory()->create();

    $report = SfMbrReport::factory()->create([
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
        'status' => SfMbrReportStatus::Ready,
    ]);

    SfCase::factory()->create([
        'sf_account_id' => $account->id,
        'sf_application_id' => $zwing->id,
        'sf_module_id' => $sso->id,
        'priority' => 'Urgent',
        'created_at_sf' => istUtc('2026-08-02 10:00:00'),
    ]);

    SfCase::factory()->create([
        'sf_account_id' => $account->id,
        'sf_application_id' => $zwing->id,
        'sf_module_id' => null,
        'priority' => 'Urgent',
        'created_at_sf' => istUtc('2026-08-03 10:00:00'),
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sf-mbr.summary', [
            'sfMbrReport' => $report,
            'module_ids' => [],
            'include_no_module' => 1,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sf-mbr/summary')
            ->where('filters.module_ids', [])
            ->where('filters.include_no_module', true)
            ->where('filters.all_modules', false)
            ->where('summary.new_tickets', 1)
            ->where('summary.workable_pool', 1)
            ->where('sla.groups.0.totals.pool', 1));
});

test('overall summary rejects an application outside the report scope', function () {
    $pos = SfApplication::factory()->create(['name' => 'Zwing (Cloud POS)']);
    $other = SfApplication::factory()->create(['name' => 'Ginesys (ERP)']);

    $report = SfMbrReport::factory()->create([
        'status' => SfMbrReportStatus::Ready,
    ]);
    $report->applications()->attach($pos);

    $this->actingAs(User::factory()->create())
        ->get(route('sf-mbr.summary', [
            'sfMbrReport' => $report,
            'application_ids' => [$other->id],
        ]))
        ->assertSessionHasErrors('application_ids');
});

test('failed segregate job marks the report failed', function () {
    $report = SfMbrReport::factory()->create([
        'status' => SfMbrReportStatus::Generating,
        'current_section' => SfMbrReportSection::SegregateAccounts,
    ]);

    (new SegregateSfMbrAccountsJob($report->id))->failed(new RuntimeException('boom'));

    $report->refresh();

    expect($report->status)->toBe(SfMbrReportStatus::Failed)
        ->and($report->failed_reason)->toBe('boom');
});

test('authenticated users can delete an mbr report and its account rows', function () {
    $report = SfMbrReport::factory()->create();
    $accountRow = SfMbrAccount::factory()->create([
        'sf_mbr_report_id' => $report->id,
    ]);

    $this->actingAs(User::factory()->create())
        ->delete(route('sf-mbr.destroy', $report))
        ->assertRedirect(route('sf-mbr.index'));

    $this->assertModelMissing($report);
    $this->assertModelMissing($accountRow);
});
