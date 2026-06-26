<?php

namespace App\Services\ChatAssistant;

use App\Models\ChatAssistantSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ChatAssistantService
{
    public function __construct(
        private readonly ModelAiCatalog $catalog,
    ) {}

    /** @return list<array{value: string, label: string}> */
    public function engineOptions(): array
    {
        return [
            ['value' => 'groq', 'label' => 'Groq'],
            ['value' => 'gemini', 'label' => 'Gemini 2.5 Flash'],
            ['value' => 'gemini-embedding', 'label' => 'Gemini Embedding 1'],
            ['value' => 'zwing', 'label' => 'ZWING Model'],
        ];
    }

    public function createSession(int $userId): ChatAssistantSession
    {
        return ChatAssistantSession::create([
            'user_id' => $userId,
            'title' => 'New chat',
            'messages' => [
                $this->message('assistant', $this->welcomeMessage()),
            ],
            'context' => $this->emptyContext(),
        ]);
    }

    public function handleMessage(
        ChatAssistantSession $session,
        string $message,
        string $engine = 'groq',
    ): ChatAssistantSession {
        $message = trim($message);
        $messages = $session->messages ?? [];
        $context = array_merge($this->emptyContext(), $session->context ?? []);
        $context['engine'] = $this->normalizeEngine($engine);

        $messages[] = $this->message('user', $message);

        if ($this->isResetCommand($message)) {
            $context = $this->emptyContext();
            $context['engine'] = $this->normalizeEngine($engine);
            $messages[] = $this->message('assistant', $this->welcomeMessage());
        } else {
            [$reply, $context] = $this->queryModelAi($message, $messages, $context);
            $messages[] = $this->message('assistant', $reply);
        }

        $session->update([
            'messages' => $messages,
            'context' => $context,
            'title' => $this->deriveTitle($messages),
        ]);

        return $session->refresh();
    }

    /** @return list<array<string, mixed>> */
    public function availableModels(): array
    {
        return $this->catalog->models();
    }

    /** @return array{intent: null, needs_input: bool, fields_used: array<string, mixed>, provider: null, engine: string} */
    private function emptyContext(): array
    {
        return [
            'intent' => null,
            'needs_input' => false,
            'fields_used' => [],
            'provider' => null,
            'engine' => 'groq',
        ];
    }

    private function normalizeEngine(string $engine): string
    {
        return in_array($engine, ['groq', 'gemini', 'gemini-embedding', 'zwing'], true) ? $engine : 'groq';
    }

    /**
     * @param  list<array{role: string, content: string, sent_at: string}>  $messages
     * @param  array{intent: ?string, needs_input: bool, fields_used: array<string, mixed>, provider: ?string, engine: string}  $context
     * @return array{0: string, 1: array{intent: ?string, needs_input: bool, fields_used: array<string, mixed>, provider: ?string, engine: string}}
     */
    private function queryModelAi(string $message, array $messages, array $context): array
    {
        $baseUrl = rtrim((string) config('services.model_ai.url'), '/');

        if ($baseUrl === '') {
            return [
                'Model-AI is not configured. Set `MODEL_AI_URL` in `.env`.',
                $context,
            ];
        }

        $history = collect($messages)
            ->slice(0, max(0, count($messages) - 1))
            ->slice(-10)
            ->map(fn (array $entry) => [
                'role' => $entry['role'],
                'content' => $entry['content'],
            ])
            ->values()
            ->all();

        try {
            $response = Http::timeout(60)->post("{$baseUrl}/chat/query", [
                'message' => $message,
                'conversation_history' => $history,
                'dataset_key' => config('services.model_ai.default_dataset_key'),
                'engine' => $context['engine'] ?? 'groq',
            ]);

            if ($response->status() === 503) {
                return [
                    'LLM middleware is not configured on Model-AI. '
                    .'Set `GROQ_API_KEY` or `GEMINI_API_KEY` in model-ai `.env`.',
                    $context,
                ];
            }

            if (! $response->successful()) {
                $detail = $response->json('detail', $response->body());
                $detailText = is_string($detail) ? $detail : json_encode($detail);

                if (str_contains(strtolower($detailText), 'rate limit')) {
                    return [
                        'AI services are temporarily busy. Please try again in a few minutes.',
                        $context,
                    ];
                }

                return [
                    'Could not get a response from Model-AI: '.$detailText,
                    $context,
                ];
            }

            $reply = (string) $response->json('reply', 'No response received.');
            $updatedContext = [
                'intent' => $response->json('intent'),
                'needs_input' => (bool) $response->json('needs_input', false),
                'fields_used' => $response->json('fields_used', []),
                'provider' => $response->json('provider'),
                'fallback' => (bool) $response->json('fallback', false),
                'engine' => $response->json('engine', $context['engine'] ?? 'groq'),
            ];

            return [$reply, $updatedContext];
        } catch (\Throwable $e) {
            return [
                'Could not connect to Model-AI: '.$e->getMessage(),
                $context,
            ];
        }
    }

    /**
     * @param  list<array{role: string, content: string, sent_at: string}>  $messages
     */
    private function deriveTitle(array $messages): string
    {
        foreach ($messages as $entry) {
            if ($entry['role'] === 'user') {
                return Str::limit($entry['content'], 40);
            }
        }

        return 'New chat';
    }

    private function welcomeMessage(): string
    {
        $models = $this->catalog->models();
        $modelLines = collect($models)
            ->map(fn (array $model) => '• **'.str_replace('_', ' ', (string) ($model['target_column'] ?? 'unknown')).'**')
            ->implode("\n");

        $invoiceLines = implode("\n", [
            '• **Invoice lookup** — sales, customer name, tax, discount',
            '• **E-invoice / e-way** — user provides compliance table name',
            '• Example: `table invoices` + `invoice_id INV-12345`',
            '• E-way example: `einvoice table einvoice_details` + `invoice_id INV-12345`',
        ]);

        return 'Hello how can we help you please you can get info about below'
            .($modelLines !== '' ? "\n\n**Store credit predictions**\n{$modelLines}" : '')
            ."\n\n**Invoice details (MySQL)**\n{$invoiceLines}"
            ."\n\n**AI engines:** Groq · Gemini 2.5 Flash · Gemini Embedding 1 · ZWING Model";
    }

    private function isResetCommand(string $message): bool
    {
        $lower = Str::lower(trim($message));

        return in_array($lower, ['reset', 'new chat', 'restart', 'clear', 'start over'], true)
            || str_contains($lower, 'start over');
    }

    /**
     * @return array{role: string, content: string, sent_at: string}
     */
    private function message(string $role, string $content): array
    {
        return [
            'role' => $role,
            'content' => $content,
            'sent_at' => now()->toIso8601String(),
        ];
    }
}
