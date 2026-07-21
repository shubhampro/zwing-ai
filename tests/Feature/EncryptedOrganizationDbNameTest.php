<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\ZwingVendorService;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

it('stores organization db_name encrypted and decrypts on read', function () {
    $plain = 'zw_mn_293_saree_sa';

    $organization = Organization::factory()->create([
        'db_name' => $plain,
    ]);

    $raw = DB::table('organizations')->where('id', $organization->id)->value('db_name');

    expect($raw)->not->toBe($plain)
        ->and($raw)->not->toBeNull()
        ->and($organization->fresh()->db_name)->toBe($plain);
});

it('saves encrypted db_name when attaching a zwing vendor', function () {
    $plain = 'zw_mn_293_saree_sa';

    $this->mock(ZwingVendorService::class, function ($mock) use ($plain) {
        $mock->shouldReceive('find')->once()->with(293)->andReturn([
            'id' => 293,
            'name' => 'SAREE SANSAR',
            'ba_code' => '928',
            'db_name' => $plain,
        ]);
    });

    actingAs(User::factory()->create())
        ->post('/organizations/attach-zwing-vendor', ['vendor_id' => 293])
        ->assertRedirect('/organizations');

    $organization = Organization::query()->where('vendor_id', 293)->first();

    expect($organization)->not->toBeNull()
        ->and($organization->db_name)->toBe($plain);

    $raw = DB::table('organizations')->where('id', $organization->id)->value('db_name');

    expect($raw)->not->toBe($plain);
});

it('stores null db_name when zwing vendor has empty db name', function () {
    $this->mock(ZwingVendorService::class, function ($mock) {
        $mock->shouldReceive('find')->once()->with(50)->andReturn([
            'id' => 50,
            'name' => 'Empty DB Vendor',
            'ba_code' => '50',
            'db_name' => '',
        ]);
    });

    actingAs(User::factory()->create())
        ->post('/organizations/attach-zwing-vendor', ['vendor_id' => 50])
        ->assertRedirect('/organizations');

    $organization = Organization::query()->where('vendor_id', 50)->first();

    expect($organization)->not->toBeNull()
        ->and($organization->db_name)->toBeNull();
});
