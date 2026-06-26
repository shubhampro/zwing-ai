<?php

namespace App\Services\ChatAssistant;

use Illuminate\Support\Str;

class MessageIntentAnalyzer
{
    /** @var array<string, list<string>> */
    private const TARGET_KEYWORDS = [
        'is_redeemed' => [
            'redeem', 'redemption', 'redeemed', 'credit note', 'store credit',
            'partial', 'refund', 'will it be used', 'use ho', 'redeem hoga',
        ],
        'customer_name' => [
            'customer', 'customer name', 'who is', 'buyer', 'person name',
            'kaun hai', 'name of', 'whose', 'kon hai',
        ],
    ];

    /** @var array<string, list<string>> */
    private const HELP_KEYWORDS = [
        'transaction' => [
            'transaction checker', 'transaction check', 'grn', 'grt', 'sst',
            'goods receipt', 'goods return', 'stock transfer',
        ],
        'reconciliation' => [
            'reconciliation', 'recon', 'stock recon', 'invoice recon',
            'stock reconciliation', 'invoice reconciliation', 'mismatch', 'erp',
        ],
        'model_training' => [
            'model training', 'train model', 'upload sheet', 'upload csv',
            'training data', 'mongodb', 'store credit note', 'credit note report',
            'retrain', 'ml model', 'prediction model',
        ],
        'help' => ['help', 'hello', 'hi', 'hey', 'namaste', 'options', 'what can you'],
    ];

    /**
     * @param  list<array<string, mixed>>  $models
     * @return array{
     *   type: 'predict'|'help'|'unknown',
     *   model: ?array,
     *   help_topic: ?string,
     *   fields: array<string, mixed>,
     *   score: int
     * }
     */
    public function analyze(string $message, array $models): array
    {
        $lower = Str::lower(trim($message));
        $normalized = $this->normalize($lower);

        $helpScores = $this->scoreHelpTopics($lower);
        $bestHelp = collect($helpScores)->sortDesc()->keys()->first();
        $bestHelpScore = $helpScores[$bestHelp] ?? 0;

        $bestModel = null;
        $bestModelScore = 0;
        $bestFields = [];

        foreach ($models as $model) {
            $target = (string) ($model['target_column'] ?? '');
            $score = $this->scoreModel($lower, $normalized, $target);
            $fields = $this->extractFields($message, $model);
            $score += min(count($fields) * 2, 6);

            if ($score > $bestModelScore) {
                $bestModelScore = $score;
                $bestModel = $model;
                $bestFields = $fields;
            }
        }

        if ($bestModel !== null && ($bestModelScore >= 2 || count($bestFields) >= 2)) {
            if ($bestModelScore >= $bestHelpScore) {
                return [
                    'type' => 'predict',
                    'model' => $bestModel,
                    'help_topic' => null,
                    'fields' => $bestFields,
                    'score' => $bestModelScore,
                ];
            }
        }

        if ($bestHelpScore >= 1) {
            return [
                'type' => 'help',
                'model' => null,
                'help_topic' => $bestHelp,
                'fields' => [],
                'score' => $bestHelpScore,
            ];
        }

        return [
            'type' => 'unknown',
            'model' => null,
            'help_topic' => null,
            'fields' => [],
            'score' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $model
     * @return array<string, mixed>
     */
    public function extractFields(string $message, array $model): array
    {
        $fields = [];
        $lower = Str::lower($message);
        $usedNumbers = [];

        foreach ($model['field_schema'] ?? [] as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }

            if (($field['type'] ?? '') === 'enum') {
                $option = $this->findEnumInText($lower, $field['options'] ?? []);
                if ($option !== null) {
                    $fields[$key] = $option;
                }

                continue;
            }

            $number = $this->findNumberInText($message, $field['options'] ?? [], $usedNumbers);
            if ($number !== null) {
                $fields[$key] = $number;
                $usedNumbers[] = (float) $number;
            }
        }

        return $fields;
    }

    private function normalize(string $text): string
    {
        $normalized = str_replace(['-', ' '], '_', $text);

        return preg_replace('/_+/', '_', $normalized) ?? $text;
    }

    private function scoreModel(string $lower, string $normalized, string $target): int
    {
        if ($target === '') {
            return 0;
        }

        $score = 0;
        $targetLower = Str::lower($target);
        $targetHuman = str_replace('_', ' ', $targetLower);

        if (
            str_contains($lower, $targetHuman)
            || str_contains($normalized, $targetLower)
            || str_contains($normalized, "predict_{$targetLower}")
        ) {
            $score += 4;
        }

        foreach (self::TARGET_KEYWORDS[$targetLower] ?? [] as $keyword) {
            if (str_contains($lower, $keyword)) {
                $score += 2;
            }
        }

        foreach (['predict', 'find', 'tell', 'check', 'what', 'kya', 'kaun', 'will'] as $word) {
            if (str_contains($lower, $word)) {
                $score += 1;
                break;
            }
        }

        foreach (explode('_', $targetLower) as $part) {
            if (strlen($part) > 2 && str_contains($lower, $part)) {
                $score += 1;
            }
        }

        return $score;
    }

    /** @return array<string, int> */
    private function scoreHelpTopics(string $lower): array
    {
        $scores = [];
        foreach (self::HELP_KEYWORDS as $topic => $keywords) {
            $scores[$topic] = 0;
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    $scores[$topic] += Str::length($keyword) > 5 ? 2 : 1;
                }
            }
        }

        return $scores;
    }

    /**
     * @param  list<mixed>  $options
     */
    private function findEnumInText(string $lower, array $options): ?string
    {
        $sorted = collect($options)->sortByDesc(fn ($option) => strlen((string) $option));

        foreach ($sorted as $option) {
            $optionLower = Str::lower((string) $option);
            if ($optionLower !== '' && str_contains($lower, $optionLower)) {
                return (string) $option;
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $options
     * @param  list<float>  $usedNumbers
     */
    private function findNumberInText(string $message, array $options, array $usedNumbers): int|float|null
    {
        preg_match_all('/\b\d+(?:\.\d+)?\b/', $message, $matches);
        $optionSet = collect($options)->map(fn ($v) => (float) $v)->all();

        foreach ($matches[0] ?? [] as $raw) {
            $value = str_contains($raw, '.') ? (float) $raw : (int) $raw;
            if (in_array((float) $value, $usedNumbers, true)) {
                continue;
            }
            foreach ($optionSet as $option) {
                if ((float) $option === (float) $value) {
                    return $value;
                }
            }
        }

        return null;
    }
}
