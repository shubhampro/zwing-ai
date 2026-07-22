<?php

use App\Enums\ExternalQueryJobType;
use App\Enums\ExternalQueryStatus;
use App\Jobs\SyncStockReconReportRowJob;
use App\Models\ExternalQueryLog;
use App\Models\Organization;
use App\Models\StockReconSession;
use App\Models\User;
use App\Support\ExternalQueryQueue;
use Illuminate\Support\Facades\Queue;

it('only owner can poll external query log', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $log = ExternalQueryLog::factory()->create([
        'user_id' => $owner->id,
        'status' => ExternalQueryStatus::Pending,
    ]);

    $this->actingAs($other)
        ->getJson(route('external-query-logs.show', $log))
        ->assertForbidden();

    $this->actingAs($owner)
        ->getJson(route('external-query-logs.show', $log))
        ->assertOk()
        ->assertJsonPath('id', $log->id)
        ->assertJsonPath('status', 'pending');
});

it('queues sync row job on external-query', function () {
    Queue::fake();

    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 11,
        'db_name' => 'zw_mn_11_demo',
    ]);

    $session = StockReconSession::query()->create([
        'user_id' => $user->id,
        'name' => 'queue-sync',
        'v_id' => $organization->vendor_id,
        'source' => 'connection',
        'organization_id' => $organization->id,
        'zwing_file_name' => 'mysql_ssh',
        'status' => 'completed',
    ]);

    $this->actingAs($user)
        ->postJson(route('stock-transaction-reconciliation.report.sync-row', $session), [
            'site_code' => '702',
            'icode' => 'MCV1454',
            'batch_no' => '',
            'sprefcode' => '1',
        ])
        ->assertStatus(202)
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('job_type', ExternalQueryJobType::SyncRow->value);

    Queue::assertPushedOn(
        ExternalQueryQueue::NAME,
        SyncStockReconReportRowJob::class,
    );

    expect(ExternalQueryLog::query()->where('job_type', ExternalQueryJobType::SyncRow)->count())->toBe(1);
});
