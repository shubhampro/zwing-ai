<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\ZwingVendorService;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('requires authentication for zwing vendors endpoint', function () {
    getJson('/organizations/zwing-vendors')->assertUnauthorized();
});

it('lists zwing vendors and attached vendor ids', function () {
    Organization::factory()->create(['vendor_id' => 100]);

    $this->mock(ZwingVendorService::class, function ($mock) {
        $mock->shouldReceive('list')->once()->andReturn([
            [
                'id' => 100,
                'name' => 'Already Attached',
                'ba_code' => '100',
                'db_name' => 'zw_mn_100_already',
            ],
            [
                'id' => 293,
                'name' => 'SAREE SANSAR',
                'ba_code' => '928',
                'db_name' => 'zw_mn_293_saree_sa',
            ],
        ]);
    });

    actingAs($this->user)
        ->getJson('/organizations/zwing-vendors')
        ->assertOk()
        ->assertJsonPath('vendors.0.id', 100)
        ->assertJsonPath('vendors.1.name', 'SAREE SANSAR')
        ->assertJsonPath('attached_vendor_ids', [100]);
});

it('attaches an organization from a zwing vendor', function () {
    $this->mock(ZwingVendorService::class, function ($mock) {
        $mock->shouldReceive('find')->once()->with(293)->andReturn([
            'id' => 293,
            'name' => 'SAREE SANSAR',
            'ba_code' => '928',
            'db_name' => 'zw_mn_293_saree_sa',
        ]);
    });

    actingAs($this->user)
        ->post('/organizations/attach-zwing-vendor', ['vendor_id' => 293])
        ->assertRedirect('/organizations');

    assertDatabaseHas('organizations', [
        'vendor_id' => 293,
        'name' => 'SAREE SANSAR',
        'ba_code' => '928',
    ]);

    $organization = Organization::query()->where('vendor_id', 293)->firstOrFail();
    $raw = DB::table('organizations')->where('id', $organization->id)->value('db_name');

    expect($organization->db_name)->toBe('zw_mn_293_saree_sa')
        ->and($raw)->not->toBe('zw_mn_293_saree_sa');
});

it('rejects attaching a duplicate vendor id', function () {
    Organization::factory()->create(['vendor_id' => 293, 'ba_code' => 'OLD']);

    $this->mock(ZwingVendorService::class, function ($mock) {
        $mock->shouldNotReceive('find');
    });

    actingAs($this->user)
        ->post('/organizations/attach-zwing-vendor', ['vendor_id' => 293])
        ->assertSessionHasErrors(['vendor_id']);
});

it('rejects attaching an unknown remote vendor', function () {
    $this->mock(ZwingVendorService::class, function ($mock) {
        $mock->shouldReceive('find')->once()->with(999)->andReturn(null);
    });

    actingAs($this->user)
        ->post('/organizations/attach-zwing-vendor', ['vendor_id' => 999])
        ->assertSessionHasErrors(['vendor_id']);
});

it('rejects attaching when ba code already exists', function () {
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
        ->post('/organizations/attach-zwing-vendor', ['vendor_id' => 293])
        ->assertSessionHasErrors(['vendor_id']);
});

it('requires authentication to attach a zwing vendor', function () {
    post('/organizations/attach-zwing-vendor', ['vendor_id' => 293])
        ->assertRedirect('/login');
});
