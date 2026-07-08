<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function sampleEjsDocument(): string
{
    return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
<style>
body { font-family: Arial; font-size: 11px; }
table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #333; padding: 4px; }
</style>
</head>
<body>
<div class="container">
  <div class="header-section">
    <div class="header-row">
      <div><%= printData.header.organizationDetails.legalName %></div>
      <div><%= printData.header.invoice.invoiceHeader.invoiceNo %></div>
    </div>
  </div>
  <table>
    <thead><tr><th>#</th><th>HSN</th><th>Amount</th></tr></thead>
    <tbody>
    <% (printData.header.invoice.invoiceDetails.productList || []).forEach(function(item, index) { %>
      <tr>
        <td><%= index + 1 %></td>
        <td><%= item.hsnCode %></td>
        <td><%= item.amount %></td>
      </tr>
    <% }); %>
    </tbody>
  </table>
</div>
</body>
</html>
HTML;
}

function tinyPngUpload(): UploadedFile
{
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        true,
    );

    return UploadedFile::fake()->createWithContent('invoice.png', $png);
}

test('guests cannot import template from vision', function () {
    $this->postJson(route('template-builder.import-vision'), [])
        ->assertUnauthorized();
});

test('vision import requires an image file', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('template-builder.import-vision'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);
});

test('vision import rejects invalid mime types', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('template-builder.import-vision'), [
            'image' => UploadedFile::fake()->create('template.txt', 10, 'text/plain'),
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);
});

test('vision import returns 503 when api key is missing', function () {
    config([
        'template-vision.provider' => 'openai',
        'template-vision.openai.api_key' => null,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('template-builder.import-vision'), [
            'image' => tinyPngUpload(),
        ], ['Accept' => 'application/json'])
        ->assertStatus(503)
        ->assertJson([
            'success' => false,
        ]);
});

test('vision import proxies image to openai and returns ejs', function () {
    config([
        'template-vision.provider' => 'openai',
        'template-vision.openai.api_key' => 'test-openai-key',
        'template-vision.openai.model' => 'gpt-4o',
    ]);

    $user = User::factory()->create();
    $ejs = sampleEjsDocument();

    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => $ejs,
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->post(route('template-builder.import-vision'), [
            'image' => tinyPngUpload(),
            'refinement' => 'Match table borders exactly',
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'provider' => 'openai',
            'model' => 'gpt-4o',
        ]);

    expect($response->json('ejs'))->toContain('<html');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-openai-key')
            && is_array($request['messages'])
            && count($request['messages']) === 2;
    });
});

test('vision import returns error when openai fails', function () {
    config([
        'template-vision.provider' => 'openai',
        'template-vision.openai.api_key' => 'test-openai-key',
    ]);

    $user = User::factory()->create();

    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response([
            'error' => ['message' => 'Invalid API key'],
        ], 401),
    ]);

    $this->actingAs($user)
        ->post(route('template-builder.import-vision'), [
            'image' => tinyPngUpload(),
        ], ['Accept' => 'application/json'])
        ->assertStatus(502)
        ->assertJson([
            'success' => false,
        ]);
});

test('vision import works with anthropic provider', function () {
    config([
        'template-vision.provider' => 'anthropic',
        'template-vision.anthropic.api_key' => 'test-anthropic-key',
        'template-vision.anthropic.model' => 'claude-sonnet-4-20250514',
    ]);

    $user = User::factory()->create();
    $ejs = sampleEjsDocument();

    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => $ejs],
            ],
        ], 200),
    ]);

    $this->actingAs($user)
        ->post(route('template-builder.import-vision'), [
            'image' => tinyPngUpload(),
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'provider' => 'anthropic',
        ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'test-anthropic-key');
    });
});

test('vision import works with gemini provider', function () {
    config([
        'template-vision.provider' => 'gemini',
        'template-vision.gemini.api_key' => 'test-gemini-key',
        'template-vision.gemini.model' => 'gemini-2.5-flash',
    ]);

    $user = User::factory()->create();
    $ejs = sampleEjsDocument();

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $ejs],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->actingAs($user)
        ->post(route('template-builder.import-vision'), [
            'image' => tinyPngUpload(),
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
        ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'generativelanguage.googleapis.com')
            && $request->hasHeader('x-goog-api-key', 'test-gemini-key');
    });
});
