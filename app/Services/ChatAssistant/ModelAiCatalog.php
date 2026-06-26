<?php

namespace App\Services\ChatAssistant;

use Illuminate\Support\Facades\Http;

class ModelAiCatalog
{
    /** @return list<array<string, mixed>> */
    public function models(): array
    {
        $baseUrl = rtrim((string) config('services.model_ai.url'), '/');
        if ($baseUrl === '') {
            return [];
        }

        try {
            $response = Http::timeout(10)->get("{$baseUrl}/models/catalog");

            return $response->successful() ? $response->json('models', []) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed>|null */
    public function findByTarget(string $target): ?array
    {
        foreach ($this->models() as $model) {
            if (strcasecmp((string) ($model['target_column'] ?? ''), $target) === 0) {
                return $model;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function resolveFromMessage(string $message): ?array
    {
        $lower = strtolower(trim($message));
        $normalized = str_replace(['-', ' '], '_', $lower);
        $normalized = preg_replace('/_+/', '_', $normalized) ?? $lower;

        $models = $this->models();
        usort($models, fn (array $a, array $b) => strlen((string) ($b['target_column'] ?? ''))
            <=> strlen((string) ($a['target_column'] ?? '')));

        foreach ($models as $model) {
            $target = strtolower((string) ($model['target_column'] ?? ''));
            if ($target === '') {
                continue;
            }

            $targetHuman = str_replace('_', ' ', $target);

            if (
                $lower === $target
                || $lower === $targetHuman
                || $lower === "predict {$target}"
                || $lower === "predict {$targetHuman}"
                || $normalized === $target
                || $normalized === "predict_{$target}"
                || str_starts_with($normalized, "predict_{$target}")
            ) {
                return $model;
            }
        }

        return null;
    }
}
