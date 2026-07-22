<?php

use App\Enums\ExternalQueryJobType;
use App\Jobs\RunExternalQueryJob;
use App\Models\ExternalQueryLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\ExternalQueryDispatcher;
use App\Services\ZwingVendorService;
use App\Support\ExternalQueryQueue;
use Illuminate\Support\Facades\Queue;

it('queues zwing vendor list on external-query', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->getJson(route('organizations.zwing-vendors'))
        ->assertStatus(202)
        ->assertJsonPath('job_type', ExternalQueryJobType::ListZwingVendors->value)
        ->assertJsonPath('status', 'pending');

    Queue::assertPushedOn(ExternalQueryQueue::NAME, RunExternalQueryJob::class);
});

it('runs list zwing vendors through external query job', function () {
    $admin = User::factory()->admin()->create();

    $this->mock(ZwingVendorService::class, function ($mock) {
        $mock->shouldReceive('list')
            ->once()
            ->andReturn([
                ['id' => 1, 'name' => 'Acme', 'ba_code' => 'ACM'],
            ]);
    });

    $log = app(ExternalQueryDispatcher::class)->dispatch(
        jobType: ExternalQueryJobType::ListZwingVendors,
        user: $admin,
    );

    expect($log->fresh()->status->value)->toBe('completed')
        ->and($log->fresh()->result['vendors'][0]['name'])->toBe('Acme');
});

it('queues attach zwing vendor on external-query', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->postJson(route('organizations.attach-zwing-vendor'), [
            'vendor_id' => 99,
        ])
        ->assertStatus(202)
        ->assertJsonPath('job_type', ExternalQueryJobType::AttachZwingVendor->value);

    Queue::assertPushedOn(ExternalQueryQueue::NAME, RunExternalQueryJob::class);
});

it('queues txn checker database list on external-query', function () {
    Queue::fake();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['vendor_id' => 42]);

    $this->actingAs($user)
        ->getJson(route('transaction-checker.databases', ['org_id' => $organization->id]))
        ->assertStatus(202)
        ->assertJsonPath('job_type', ExternalQueryJobType::ListTxnCheckerDatabases->value);

    Queue::assertPushedOn(ExternalQueryQueue::NAME, RunExternalQueryJob::class);
    expect(ExternalQueryLog::query()->count())->toBe(1);
});
