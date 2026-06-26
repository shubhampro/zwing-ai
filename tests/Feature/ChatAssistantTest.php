<?php

use App\Models\ChatAssistantSession;
use App\Models\User;
use App\Services\ChatAssistant\ChatAssistantService;
use Illuminate\Support\Facades\Http;

function fakeModelCatalog(): void
{
    Http::fake([
        '*/models/catalog' => Http::response([
            'models' => [
                [
                    'model_name' => 'model_is_redeemed_classification.pkl',
                    'target_column' => 'is_redeemed',
                    'schema_csv' => 'store_credit_notes.csv',
                    'problem_type' => 'classification',
                    'field_schema' => [
                        ['key' => 'store_name', 'label' => 'Store Name', 'type' => 'enum', 'options' => ['Delhi Store']],
                        ['key' => 'credit_note_amt', 'label' => 'Credit Note Amt', 'type' => 'number', 'options' => [1800]],
                        ['key' => 'payment_mode', 'label' => 'Payment Mode', 'type' => 'enum', 'options' => ['UPI']],
                        ['key' => 'validity_days', 'label' => 'Validity Days', 'type' => 'number', 'options' => [30]],
                    ],
                ],
            ],
        ]),
    ]);
}

beforeEach(function () {
    $this->service = app(ChatAssistantService::class);
    $this->user = User::factory()->create();
});

it('creates a welcome session', function () {
    fakeModelCatalog();
    $session = $this->service->createSession($this->user->id);

    expect($session->messages)->toHaveCount(1)
        ->and($session->messages[0]['role'])->toBe('assistant')
        ->and($session->messages[0]['content'])->toContain('Invoice details');
});

it('starts predict flow from target keyword', function () {
    fakeModelCatalog();
    $session = $this->service->createSession($this->user->id);

    $session = $this->service->handleMessage($session, 'predict is_redeemed');

    expect($session->context['intent'])->toBe('predict')
        ->and($session->context['model']['target_column'])->toBe('is_redeemed')
        ->and($session->messages[2]['content'])->toContain('Store Name');
});

it('resolves predict customer name with spaces', function () {
    Http::fake([
        '*/models/catalog' => Http::response([
            'models' => [
                [
                    'model_name' => 'model_customer_name_classification.pkl',
                    'target_column' => 'customer_name',
                    'schema_csv' => 'store_credit_notes.csv',
                    'field_schema' => [
                        ['key' => 'store_name', 'label' => 'Store Name', 'type' => 'enum', 'options' => ['Pune Store']],
                    ],
                ],
                [
                    'model_name' => 'model_is_redeemed_classification.pkl',
                    'target_column' => 'is_redeemed',
                    'schema_csv' => 'store_credit_notes.csv',
                    'field_schema' => [],
                ],
            ],
        ]),
    ]);

    $session = $this->service->createSession($this->user->id);
    $session = $this->service->handleMessage($session, 'Predict customer name');

    expect($session->context['intent'])->toBe('predict')
        ->and($session->context['model']['target_column'])->toBe('customer_name');
});

it('resets conversation on reset command', function () {
    fakeModelCatalog();
    $session = $this->service->createSession($this->user->id);
    $session = $this->service->handleMessage($session, 'predict is_redeemed');

    $session = $this->service->handleMessage($session, 'reset');

    expect($session->context['intent'])->toBeNull()
        ->and($session->messages[count($session->messages) - 1]['content'])->toContain('Invoice details');
});

it('returns prediction when model-ai responds', function () {
    Http::fake([
        '*/models/catalog' => Http::response([
            'models' => [
                [
                    'model_name' => 'model_is_redeemed_classification.pkl',
                    'target_column' => 'is_redeemed',
                    'schema_csv' => 'store_credit_notes.csv',
                    'field_schema' => [
                        ['key' => 'store_name', 'label' => 'Store Name', 'type' => 'enum', 'options' => ['Delhi Store']],
                        ['key' => 'credit_note_amt', 'label' => 'Credit Note Amt', 'type' => 'number', 'options' => [1800]],
                        ['key' => 'payment_mode', 'label' => 'Payment Mode', 'type' => 'enum', 'options' => ['UPI']],
                        ['key' => 'validity_days', 'label' => 'Validity Days', 'type' => 'number', 'options' => [30]],
                    ],
                ],
            ],
        ]),
        '*/predict/record' => Http::response([
            'prediction' => ['yes'],
            'target_column' => 'is_redeemed',
            'source' => 'training_record',
        ]),
    ]);

    $session = $this->service->createSession($this->user->id);
    $session = $this->service->handleMessage($session, 'predict is_redeemed');
    $session = $this->service->handleMessage($session, 'Delhi Store');
    $session = $this->service->handleMessage($session, '1800');
    $session = $this->service->handleMessage($session, 'UPI');
    $session = $this->service->handleMessage($session, '30');

    $last = $session->messages[count($session->messages) - 1]['content'];

    expect($last)->toContain('Prediction: is redeemed')
        ->and($last)->toContain('yes');
});

it('allows authenticated users to send chat messages', function () {
    fakeModelCatalog();

    $session = ChatAssistantSession::create([
        'user_id' => $this->user->id,
        'title' => 'Test',
        'messages' => [['role' => 'assistant', 'content' => 'Hi', 'sent_at' => now()->toIso8601String()]],
        'context' => ['intent' => null, 'model' => null, 'step' => 0, 'fields' => []],
    ]);

    $this->actingAs($this->user)
        ->postJson(route('assistant.messages.send'), [
            'session_id' => $session->id,
            'message' => 'help',
        ])
        ->assertSuccessful()
        ->assertJsonPath('session.id', $session->id);
});

it('returns bootstrap payload for the floating assistant widget', function () {
    fakeModelCatalog();

    $this->actingAs($this->user)
        ->getJson(route('assistant.bootstrap'))
        ->assertSuccessful()
        ->assertJsonStructure([
            'session' => ['id', 'title', 'messages'],
            'models',
            'engineOptions',
        ])
        ->assertJsonPath('session.messages.0.role', 'assistant');
});
