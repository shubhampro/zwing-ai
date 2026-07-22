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
        ->postJson('/organizations/update-zwing-vendor', ['vendor_id' => 293])
        ->assertStatus(202)
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('result.organization_id', $organization->id);

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
        ->postJson('/organizations/update-zwing-vendor', ['vendor_id' => 999])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['vendor_id']);
});

it('rejects update when remote vendor is missing', function () {
    Organization::factory()->create(['vendor_id' => 50, 'ba_code' => '50']);

    $this->mock(ZwingVendorService::class, function ($mock) {
        $mock->shouldReceive('find')->once()->with(50)->andReturn(null);
    });

    actingAs($this->user)
        ->postJson('/organizations/update-zwing-vendor', ['vendor_id' => 50])
        ->assertStatus(202)
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('failure_reason', 'Vendor not found in Zwing Master.');
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
        ->postJson('/organizations/update-zwing-vendor', ['vendor_id' => 293])
        ->assertStatus(202)
        ->assertJsonPath('status', 'failed');
});

it('requires authentication to update from zwing', function () {
    post('/organizations/update-zwing-vendor', ['vendor_id' => 293])
        ->assertRedirect('/login');
});
