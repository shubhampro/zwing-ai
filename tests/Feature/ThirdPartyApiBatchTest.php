<?php

use App\HttpMethod;
use App\Jobs\ParseThirdPartyApiBatchCsv;
use App\Jobs\ProcessThirdPartyApiBatch;
use App\Jobs\ProcessThirdPartyApiBatchItemJob;
use App\Models\Organization;
use App\Models\OrganizationThirdPartyApi;
use App\Models\ThirdPartyApi;
use App\Models\ThirdPartyApiBatch;
use App\Models\ThirdPartyApiBatchItem;
use App\Models\ThirdPartyApiBatchItemAttempt;
use App\Models\User;
use App\Services\ThirdParty\ProcessThirdPartyApiBatchItem;
use App\Services\ThirdParty\ThirdPartyApiClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->api = ThirdPartyApi::factory()->create([
        'path' => '/api/v1/records',
        'method' => HttpMethod::Post,
        'params' => [
            ['key' => 'record_id', 'csv_column' => 'record_id', 'required' => true],
            ['key' => 'site_code', 'csv_column' => 'site_code', 'required' => true],
            ['key' => 'action_date', 'csv_column' => 'action_date', 'required' => false],
            ['key' => 'remarks', 'csv_column' => 'remarks', 'required' => false],
        ],
        'auth_header_name' => 'X-Api-Key',
    ]);
    $this->connection = OrganizationThirdPartyApi::factory()->create([
        'organization_id' => $this->organization->id,
        'third_party_api_id' => $this->api->id,
        'base_url' => 'https://api.vendor-a.example.com',
        'auth_token' => 'test-api-key',
    ]);
});

it('shows batch create page with organizations and connections', function () {
    actingAs($this->user)
        ->get(route('third-party-api-batches.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('third-party-api-batches/create')
            ->has('organizations', 1)
            ->has('connections', 1)
            ->where('connections.0.organization_id', $this->organization->id));
});

it('uploads csv and dispatches parse job', function () {
    Queue::fake();
    Storage::fake('local');

    $csv = "record_id,site_code,action_date,remarks\n128292,823,2026-06-16,Wrong Bill\n";

    actingAs($this->user)
        ->post(route('third-party-api-batches.csv'), [
            'name' => 'june-batch',
            'organization_third_party_api_id' => $this->connection->id,
            'csv' => UploadedFile::fake()->createWithContent('batch.csv', $csv),
        ])
        ->assertRedirect();

    $batch = ThirdPartyApiBatch::query()->firstOrFail();

    expect($batch->organization_third_party_api_id)->toBe($this->connection->id);

    Queue::assertPushed(ParseThirdPartyApiBatchCsv::class, fn ($job) => $job->batchId === $batch->id);
});

it('dispatches one queue job per csv row', function () {
    Queue::fake();

    $batch = ThirdPartyApiBatch::factory()->create([
        'user_id' => $this->user->id,
        'organization_third_party_api_id' => $this->connection->id,
        'status' => 'pending',
    ]);

    ThirdPartyApiBatchItem::factory()->count(3)->create([
        'third_party_api_batch_id' => $batch->id,
        'status' => 'pending',
    ]);

    (new ProcessThirdPartyApiBatch($batch->id))->handle();

    Queue::assertPushed(ProcessThirdPartyApiBatchItemJob::class, 3);
});

it('parses csv and calls org-specific endpoint', function () {
    Http::fake(['api.vendor-a.example.com/*' => Http::response(['status' => 'ok'], 200)]);

    $batch = ThirdPartyApiBatch::create([
        'user_id' => $this->user->id,
        'organization_third_party_api_id' => $this->connection->id,
        'name' => 'parse-test',
        'defaults' => ['action_date' => '2026-06-16', 'remarks' => 'Wrong Bill'],
        'status' => 'pending',
    ]);

    $csvPath = storage_path('app/testing-batch.csv');
    file_put_contents($csvPath, "record_id,site_code\n128292,823\n");

    (new ParseThirdPartyApiBatchCsv($batch->id, $csvPath))->handle();
    (new ProcessThirdPartyApiBatch($batch->id))->handle();

    $item = ThirdPartyApiBatchItem::query()->firstOrFail();
    (new ProcessThirdPartyApiBatchItemJob($item->id))->handle(app(ProcessThirdPartyApiBatchItem::class));

    $batch->refresh();

    expect($batch->status)->toBe('completed')
        ->and(ThirdPartyApiBatchItem::query()->first()->payload['record_id'])->toBe('128292')
        ->and(ThirdPartyApiBatchItemAttempt::query()->count())->toBe(1);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/records')
        && $request->hasHeader('X-Api-Key', 'test-api-key'));

    @unlink($csvPath);
});

it('sends request using org connection credentials', function () {
    Http::fake(['*' => Http::response([], 204)]);

    ThirdPartyApiClient::forConnection($this->connection)->send([
        'record_id' => '128292',
    ]);

    Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'test-api-key')
        && str_contains($request->url(), 'api.vendor-a.example.com/api/v1/records'));
});

it('reuses same api template for another org with different base url', function () {
    $otherOrg = Organization::factory()->create();
    $otherConnection = OrganizationThirdPartyApi::factory()->create([
        'organization_id' => $otherOrg->id,
        'third_party_api_id' => $this->api->id,
        'base_url' => 'https://api.vendor-b.example.com',
        'auth_token' => 'other-key',
    ]);

    expect($otherConnection->third_party_api_id)->toBe($this->api->id)
        ->and($otherConnection->endpointUrl())->toBe('https://api.vendor-b.example.com/api/v1/records');
});

it('shows batch details and attempt history on one page', function () {
    $batch = ThirdPartyApiBatch::factory()->create([
        'user_id' => $this->user->id,
        'organization_third_party_api_id' => $this->connection->id,
        'status' => 'completed',
    ]);

    $item = ThirdPartyApiBatchItem::factory()->create([
        'third_party_api_batch_id' => $batch->id,
        'status' => 'failed',
        'http_status' => 404,
        'error_message' => 'HTTP 404',
        'processed_at' => now(),
    ]);

    ThirdPartyApiBatchItemAttempt::create([
        'third_party_api_batch_item_id' => $item->id,
        'attempt_number' => 1,
        'request_method' => 'POST',
        'request_url' => 'https://api.vendor-a.example.com/api/v1/records',
        'request_headers' => ['X-Api-Key' => '••••••••'],
        'request_body' => ['record_id' => '1'],
        'http_status' => 404,
        'response_body' => 'not found',
        'error_message' => 'HTTP 404',
        'created_at' => now(),
    ]);

    actingAs($this->user)
        ->get(route('third-party-api-batches.show', $batch))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('third-party-api-batches/show')
            ->has('rows', 1)
            ->has('rows.0.attempts', 1)
            ->where('rows.0.attempts.0.request_url', 'https://api.vendor-a.example.com/api/v1/records'));
});

it('redirects legacy report route to batch show page', function () {
    $batch = ThirdPartyApiBatch::factory()->create([
        'user_id' => $this->user->id,
        'organization_third_party_api_id' => $this->connection->id,
    ]);

    actingAs($this->user)
        ->get(route('third-party-api-batches.report', ['thirdPartyApiBatch' => $batch, 'filter' => 'failed']))
        ->assertRedirect(route('third-party-api-batches.show', [
            'thirdPartyApiBatch' => $batch,
            'filter' => 'failed',
        ]));
});

it('retries a failed item and keeps attempt history', function () {
    Http::fake(['api.vendor-a.example.com/*' => Http::response(['ok' => true], 200)]);

    $batch = ThirdPartyApiBatch::factory()->create([
        'user_id' => $this->user->id,
        'organization_third_party_api_id' => $this->connection->id,
        'failed_count' => 1,
    ]);

    $item = ThirdPartyApiBatchItem::factory()->create([
        'third_party_api_batch_id' => $batch->id,
        'status' => 'failed',
        'payload' => ['record_id' => '99', 'site_code' => '1'],
    ]);

    ThirdPartyApiBatchItemAttempt::create([
        'third_party_api_batch_item_id' => $item->id,
        'attempt_number' => 1,
        'request_method' => 'POST',
        'request_url' => 'https://api.vendor-a.example.com/api/v1/records',
        'request_headers' => ['X-Api-Key' => '••••••••'],
        'request_body' => ['record_id' => '99'],
        'http_status' => 404,
        'error_message' => 'HTTP 404',
        'created_at' => now(),
    ]);

    actingAs($this->user)
        ->post(route('third-party-api-batches.items.retry', [$batch, $item]))
        ->assertRedirect();

    $item->refresh();
    $batch->refresh();

    expect($item->status)->toBe('success')
        ->and($item->attempts()->count())->toBe(2)
        ->and($batch->success_count)->toBe(1)
        ->and($batch->failed_count)->toBe(0);
});
