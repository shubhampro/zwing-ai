<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\ZwingVendorService;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('updates an existing organization from zwing master', function () {
    $organization = Organization::factory()->create([
        'vendor_id' => 293,
        'name' => 'Old Name',
        'ba_code' => 'OLD',
        'db_name' => 'old_db',
    ]);

    $this->mock(ZwingVendorService::class, function ($mock) {
        $mock->shouldReceive('find')->once()->with(293)->andReturn([
            'id' => 293,
            'name' => 'SAREE SANSAR',
            'ba_code' => '928',
            'db_name' => 'zw_mn_293_saree_sa',
        ]);
    });

    actingAs($this->user)
        ->post('/organizations/update-zwing-vendor', ['vendor_id' => 293])
        ->assertRedirect('/organizations');

    $organization->refresh();

    expect($organization->name)->toBe('SAREE SANSAR')
        ->and($organization->ba_code)->toBe('928')
        ->and($organization->db_name)->toBe('zw_mn_293_saree_sa');

    $raw = DB::table('organizations')->where('id', $organization->id)->value('db_name');

    expect($raw)->not->toBe('zw_mn_293_saree_sa');
});

it('rejects update when vendor is not attached', function () {
    $this->mock(ZwingVendorService::class, function ($mock) {
        $mock->shouldNotReceive('find');
    });

    actingAs($this->user)
        ->post('/organizations/update-zwing-vendor', ['vendor_id' => 999])
        ->assertSessionHasErrors(['vendor_id']);
});

it('rejects update when remote vendor is missing', function () {
    Organization::factory()->create(['vendor_id' => 50, 'ba_code' => '50']);

    $this->mock(ZwingVendorService::class, function ($mock) {
        $mock->shouldReceive('find')->once()->with(50)->andReturn(null);
    });

    actingAs($this->user)
        ->post('/organizations/update-zwing-vendor', ['vendor_id' => 50])
        ->assertSessionHasErrors(['vendor_id']);
});

it('rejects update when ba code belongs to another organization', function () {
    Organization::factory()->create(['vendor_id' => 293, 'ba_code' => 'OLD']);
    Organization::factory()->create(['vendor_id' => 1, 'ba_code' => '928']);

    $this->mock(ZwingVendorService::class, function ($mock) {
        $mock->shouldReceive('find')->once()->with(293)->andReturn([
            'id' => 293,
            'name' => 'SAREE SANSAR',
            'ba_code' => '928',
            'db_name' => 'zw_mn_293_saree_sa',
        ]);
    });

    actingAs($this->user)
        ->post('/organizations/update-zwing-vendor', ['vendor_id' => 293])
        ->assertSessionHasErrors(['vendor_id']);
});

it('requires authentication to update from zwing', function () {
    post('/organizations/update-zwing-vendor', ['vendor_id' => 293])
        ->assertRedirect('/login');
});
