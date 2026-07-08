<?php

namespace App\Services\TemplateBuilder;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class VisionTemplateImportService
{
    /**
     * @return array{ejs: string, warnings: array<int, string>, provider: string, model: string}
     */
    public function importFromImage(string $imageBytes, string $mimeType, ?string $refinement = null): array
    {
        $provider = (string) config('template-vision.provider', 'openai');
        $normalizedMime = $this->normalizeMimeType($mimeType);
        $base64 = base64_encode($imageBytes);
        $systemPrompt = $this->loadSystemPrompt();
        $userPrompt = $this->buildUserPrompt($refinement);

        $rawContent = match ($provider) {
            'anthropic' => $this->callAnthropic($base64, $normalizedMime, $systemPrompt, $userPrompt),
            'gemini' => $this->callGemini($base64, $normalizedMime, $systemPrompt, $userPrompt),
            'openai' => $this->callOpenAi($base64, $normalizedMime, $systemPrompt, $userPrompt),
            default => throw new RuntimeException("Unsupported vision provider [{$provider}]."),
        };

        $ejs = $this->extractHtmlDocument($rawContent);
        $warnings = $this->validateEjs($ejs);

        return [
            'ejs' => $ejs,
            'warnings' => $warnings,
            'provider' => $provider,
            'model' => $this->resolveModel($provider),
        ];
    }

    public function isConfigured(): bool
    {
        $provider = (string) config('template-vision.provider', 'openai');

        return match ($provider) {
            'anthropic' => filled(config('template-vision.anthropic.api_key')),
            'gemini' => filled(config('template-vision.gemini.api_key')),
            'openai' => filled(config('template-vision.openai.api_key')),
            default => false,
        };
    }

    private function resolveModel(string $provider): string
    {
        return match ($provider) {
            'anthropic' => (string) config('template-vision.anthropic.model'),
            'gemini' => (string) config('template-vision.gemini.model'),
            default => (string) config('template-vision.openai.model'),
        };
    }

    private function loadSystemPrompt(): string
    {
        $path = resource_path('prompts/template-vision-import.md');

        if (! is_readable($path)) {
            throw new RuntimeException('Vision import prompt file is missing.');
        }

        return (string) file_get_contents($path);
    }

    private function buildUserPrompt(?string $refinement): string
    {
        $prompt = 'Analyze the attached invoice/receipt image and generate the complete EJS HTML document.';

        if ($refinement !== null && trim($refinement) !== '') {
            $prompt .= "\n\nRefinement instructions from the user:\n".trim($refinement);
        }

        return $prompt;
    }

    private function normalizeMimeType(string $mimeType): string
    {
        $mime = strtolower(trim(explode(';', $mimeType)[0]));

        return match ($mime) {
            'image/jpg' => 'image/jpeg',
            'image/png', 'image/jpeg', 'image/webp' => $mime,
            default => throw new RuntimeException("Unsupported image type [{$mimeType}]."),
        };
    }

    private function callOpenAi(string $base64, string $mimeType, string $systemPrompt, string $userPrompt): string
    {
        $apiKey = config('template-vision.openai.api_key');

        if (! filled($apiKey)) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $response = $this->httpClient()
            ->withToken($apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('template-vision.openai.model'),
                'max_tokens' => config('template-vision.openai.max_tokens'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $userPrompt,
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$base64}",
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        return $this->parseOpenAiResponse($response);
    }

    private function callAnthropic(string $base64, string $mimeType, string $systemPrompt, string $userPrompt): string
    {
        $apiKey = config('template-vision.anthropic.api_key');

        if (! filled($apiKey)) {
            throw new RuntimeException('Anthropic API key is not configured.');
        }

        $response = $this->httpClient()
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => config('template-vision.anthropic.model'),
                'max_tokens' => config('template-vision.anthropic.max_tokens'),
                'system' => $systemPrompt,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $mimeType,
                                    'data' => $base64,
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => $userPrompt,
                            ],
                        ],
                    ],
                ],
            ]);

        return $this->parseAnthropicResponse($response);
    }

    private function callGemini(string $base64, string $mimeType, string $systemPrompt, string $userPrompt): string
    {
        $apiKey = config('template-vision.gemini.api_key');

        if (! filled($apiKey)) {
            throw new RuntimeException('Gemini API key is not configured.');
        }

        $model = (string) config('template-vision.gemini.model', 'gemini-2.5-flash');
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            .rawurlencode($model)
            .':generateContent';

        $response = $this->httpClient()
            ->withHeaders([
                'x-goog-api-key' => $apiKey,
            ])
            ->post($url, [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $systemPrompt],
                    ],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $base64,
                                ],
                            ],
                            [
                                'text' => $userPrompt,
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'maxOutputTokens' => (int) config('template-vision.gemini.max_tokens', 16384),
                    'temperature' => 0.2,
                ],
            ]);

        return $this->parseGeminiResponse($response);
    }

    private function httpClient(): PendingRequest
    {
        return Http::timeout((int) config('template-vision.timeout', 180))
            ->connectTimeout(15)
            ->acceptJson();
    }

    private function parseOpenAiResponse(Response $response): string
    {
        if (! $response->successful()) {
            throw new RuntimeException($this->formatApiError('OpenAI', $response));
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('OpenAI returned an empty response.');
        }

        return $content;
    }

    private function parseAnthropicResponse(Response $response): string
    {
        if (! $response->successful()) {
            throw new RuntimeException($this->formatApiError('Anthropic', $response));
        }

        $blocks = $response->json('content');

        if (! is_array($blocks)) {
            throw new RuntimeException('Anthropic returned an invalid response.');
        }

        $text = '';

        foreach ($blocks as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $text .= $block['text'];
            }
        }

        if (trim($text) === '') {
            throw new RuntimeException('Anthropic returned an empty response.');
        }

        return $text;
    }

    private function parseGeminiResponse(Response $response): string
    {
        if (! $response->successful()) {
            throw new RuntimeException($this->formatApiError('Gemini', $response));
        }

        $parts = $response->json('candidates.0.content.parts');

        if (! is_array($parts)) {
            $blockReason = $response->json('promptFeedback.blockReason')
                ?? $response->json('candidates.0.finishReason');

            throw new RuntimeException(
                'Gemini returned an invalid response'
                .($blockReason ? " ({$blockReason})" : '')
                .'.'
            );
        }

        $text = '';

        foreach ($parts as $part) {
            if (is_array($part) && is_string($part['text'] ?? null)) {
                $text .= $part['text'];
            }
        }

        if (trim($text) === '') {
            throw new RuntimeException('Gemini returned an empty response.');
        }

        return $text;
    }

    private function formatApiError(string $provider, Response $response): string
    {
        $message = $response->json('error.message') ?? $response->json('message') ?? $response->body();

        return "{$provider} API error ({$response->status()}): {$message}";
    }

    private function extractHtmlDocument(string $rawContent): string
    {
        $content = trim($rawContent);

        if (preg_match('/```(?:html|ejs)?\s*([\s\S]*?)```/i', $content, $matches)) {
            $content = trim($matches[1]);
        }

        if (! preg_match('/<html[\s>]/i', $content)) {
            if (preg_match('/<!DOCTYPE[\s\S]*<\/html>/i', $content, $docMatch)) {
                $content = trim($docMatch[0]);
            } elseif (preg_match('/<html[\s\S]*<\/html>/i', $content, $htmlMatch)) {
                $content = trim($htmlMatch[0]);
            }
        }

        if (! preg_match('/<html[\s>]/i', $content)) {
            throw new RuntimeException('Vision model did not return a valid HTML document.');
        }

        return $content;
    }

    /**
     * @return array<int, string>
     */
    private function validateEjs(string $ejs): array
    {
        $warnings = [];

        if (! preg_match('/<style[\s>]/i', $ejs)) {
            $warnings[] = 'Generated document has no <style> block — layout may be incomplete.';
        }

        if (! preg_match('/<%=/', $ejs) && ! preg_match('/<%\s/', $ejs)) {
            $warnings[] = 'No EJS tags detected — dynamic fields may be missing.';
        }

        if (! preg_match('/printData/i', $ejs)) {
            $warnings[] = 'No printData variable paths found — check variable mapping.';
        }

        if (! preg_match('/<table[\s>]/i', $ejs)) {
            $warnings[] = 'No table elements found — product/tax tables may be missing.';
        }

        return $warnings;
    }
}
