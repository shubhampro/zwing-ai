<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('guests cannot access print invoice page', function () {
    $this->get(route('print-invoice.index'))
        ->assertRedirect('/login');
});

test('authenticated user can view print invoice page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('print-invoice.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('print-invoice/index')
            ->has('defaultPayload')
            ->where('defaultPayload.url', 'https://aks-prod.api.gozwing.com/pos/print/invoice')
            ->where('defaultPayload.tenant_id', '151')
            ->has('defaultPayload.bodyJson')
        );
});

test('guests cannot preview print invoice', function () {
    $this->postJson(route('print-invoice.preview'), [
        'url' => 'https://aks-prod.api.gozwing.com/pos/print/invoice',
        'tenant_id' => '151',
        'token' => 'test-token',
        'body' => [
            'invoiceId' => 'RA03592600034',
            'orderId' => 'RA03592600034',
            'storeId' => 1,
            'vId' => 151,
            'terminalId' => 2679,
            'transaction' => 'INVOICE',
            'transFrom' => 'CLOUD_TAB_WEB',
            'trim' => true,
            'timezone' => '+05:30',
            'trans_from' => 'CLOUD_TAB_WEB',
            'udidtoken' => 'token',
            'terminal_id' => 2679,
            'session_id' => 306,
        ],
    ])->assertUnauthorized();
});

test('preview requires valid payload', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('print-invoice.preview'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url', 'tenant_id', 'token', 'body']);
});

test('preview proxies request to print invoice api', function () {
    $user = User::factory()->create();

    $html = '<center><div>Invoice preview</div></center>';

    Http::fake([
        'aks-prod.api.gozwing.com/pos/print/invoice' => Http::response($html, 201),
    ]);

    $this->actingAs($user)
        ->postJson(route('print-invoice.preview'), [
            'url' => 'https://aks-prod.api.gozwing.com/pos/print/invoice',
            'tenant_id' => '151',
            'token' => 'test-token',
            'body' => [
                'invoiceId' => 'RA03592600034',
                'orderId' => 'RA03592600034',
                'storeId' => 1,
                'vId' => 151,
                'terminalId' => 2679,
                'transaction' => 'INVOICE',
                'transFrom' => 'CLOUD_TAB_WEB',
                'trim' => true,
                'timezone' => '+05:30',
                'trans_from' => 'CLOUD_TAB_WEB',
                'udidtoken' => 'token',
                'terminal_id' => 2679,
                'session_id' => 306,
            ],
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'html' => $html,
            'status' => 201,
        ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://aks-prod.api.gozwing.com/pos/print/invoice'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->hasHeader('x-tenant-id', '151')
            && $request['invoiceId'] === 'RA03592600034'
            && $request['transaction'] === 'INVOICE';
    });
});

test('preview returns error when external api fails', function () {
    $user = User::factory()->create();

    Http::fake([
        'aks-prod.api.gozwing.com/pos/print/invoice' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $this->actingAs($user)
        ->postJson(route('print-invoice.preview'), [
            'url' => 'https://aks-prod.api.gozwing.com/pos/print/invoice',
            'tenant_id' => '151',
            'token' => 'test-token',
            'body' => [
                'invoiceId' => 'RA03592600034',
                'orderId' => 'RA03592600034',
                'storeId' => 1,
                'vId' => 151,
                'terminalId' => 2679,
                'transaction' => 'INVOICE',
                'transFrom' => 'CLOUD_TAB_WEB',
                'trim' => true,
                'timezone' => '+05:30',
                'trans_from' => 'CLOUD_TAB_WEB',
                'udidtoken' => 'token',
                'terminal_id' => 2679,
                'session_id' => 306,
            ],
        ])
        ->assertStatus(502)
        ->assertJson([
            'success' => false,
            'status' => 401,
        ]);
});
