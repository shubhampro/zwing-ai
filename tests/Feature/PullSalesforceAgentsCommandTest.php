<?php

use App\Exceptions\SalesforceQueryException;
use App\Models\SfAgent;
use App\Services\Salesforce\SalesforceAgentPuller;
use App\Services\Salesforce\SalesforceSoqlClient;

test('artisan command pulls case agents from salesforce users', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')
            ->once()
            ->with(SalesforceAgentPuller::AGENT_SOQL)
            ->andReturn([
                [
                    'Id' => '005xx000000AgentAAA',
                    'Name' => 'Arindam Banerjee',
                    'IsActive' => true,
                ],
                [
                    'Id' => '005xx000000AgentBBB',
                    'Name' => 'Sujit Kumar Jha',
                    'IsActive' => false,
                ],
            ]);
    });

    $this->artisan('sf:pull-agents')
        ->expectsOutputToContain('Pulled 2 Salesforce agents.')
        ->assertSuccessful();

    expect(SfAgent::query()->count())->toBe(2);

    $agent = SfAgent::query()->where('sf_id', '005xx000000AgentAAA')->first();

    expect($agent)->not->toBeNull()
        ->and($agent->name)->toBe('Arindam Banerjee')
        ->and($agent->is_active)->toBeTrue()
        ->and($agent->synced_at)->not->toBeNull();
});

test('artisan command upserts existing agents by sf id', function () {
    $existing = SfAgent::factory()->create([
        'sf_id' => '005xx000000AgentAAA',
        'name' => 'Old Name',
        'is_active' => false,
    ]);

    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->once()->andReturn([
            [
                'Id' => '005xx000000AgentAAA',
                'Name' => 'Arindam Banerjee',
                'IsActive' => true,
            ],
        ]);
    });

    $this->artisan('sf:pull-agents')
        ->expectsOutputToContain('Pulled 1 Salesforce agents.')
        ->assertSuccessful();

    $existing->refresh();

    expect(SfAgent::query()->count())->toBe(1)
        ->and($existing->name)->toBe('Arindam Banerjee')
        ->and($existing->is_active)->toBeTrue();
});

test('artisan command dry run does not write agents', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->once()->andReturn([
            [
                'Id' => '005xx000000AgentAAA',
                'Name' => 'Arindam Banerjee',
                'IsActive' => true,
            ],
        ]);
    });

    $this->artisan('sf:pull-agents', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run: 1 Salesforce agents (not saved).')
        ->assertSuccessful();

    expect(SfAgent::query()->count())->toBe(0);
});

test('artisan command skips records without a salesforce id', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')->once()->andReturn([
            ['Name' => 'Missing Id'],
            [
                'Id' => '005xx000000AgentAAA',
                'Name' => 'Arindam Banerjee',
                'IsActive' => true,
            ],
        ]);
    });

    $this->artisan('sf:pull-agents')
        ->expectsOutputToContain('Pulled 1 Salesforce agents.')
        ->assertSuccessful();

    expect(SfAgent::query()->count())->toBe(1)
        ->and(SfAgent::query()->value('sf_id'))->toBe('005xx000000AgentAAA');
});

test('artisan command fails when salesforce query fails', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')
            ->once()
            ->andThrow(new SalesforceQueryException('Salesforce query failed: org not found'));
    });

    $this->artisan('sf:pull-agents')
        ->expectsOutputToContain('Salesforce query failed: org not found')
        ->assertFailed();

    expect(SfAgent::query()->count())->toBe(0);
});
