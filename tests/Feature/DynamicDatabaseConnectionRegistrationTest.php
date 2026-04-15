<?php

use App\Models\DatabaseConnection;
use App\Services\DatabaseConnectionRegistrar;

test('registrar exposes active slugs on laravel database config', function () {
    DatabaseConnection::factory()->create([
        'slug' => 'acme_read',
        'connection_group' => 'acme',
    ]);

    DatabaseConnectionRegistrar::register();

    expect(config('database.connections.acme_read'))
        ->toBeArray()
        ->and(config('database.connections.acme_read.driver'))->toBe('mysql');
});
