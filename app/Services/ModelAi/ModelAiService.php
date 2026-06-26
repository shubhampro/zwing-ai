<?php

namespace App\Services\ModelAi;

use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class ModelAiService
{
    public function baseUrl(): string
    {
        return rtrim((string) config('services.model_ai.url'), '/');
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '';
    }

    /** @return list<array<string, mixed>> */
    public function listDatasets(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $response = Http::timeout(15)->get("{$this->baseUrl()}/datasets");

            return $response->successful() ? $response->json('datasets', []) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    public function listModels(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $response = Http::timeout(15)->get("{$this->baseUrl()}/models/catalog");

            return $response->successful() ? $response->json('models', []) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function previewDataset(UploadedFile $file): array
    {
        $response = $this->postMultipart('/datasets/preview', ['file' => $file]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->extractError($response));
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDataset(string $datasetKey): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::timeout(15)->get("{$this->baseUrl()}/datasets/{$datasetKey}");

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<string>  $targetColumns
     * @return array<string, mixed>
     */
    public function uploadDataset(
        UploadedFile $file,
        string $datasetKey,
        array $targetColumns = [],
        ?string $name = null,
        ?string $uploadedBy = null,
        bool $autoTrain = true,
    ): array {
        $response = $this->postMultipart(
            '/datasets/upload',
            [
                'file' => $file,
            ],
            [
                'dataset_key' => $datasetKey,
                'name' => $name,
                'uploaded_by' => $uploadedBy,
                'auto_train' => $autoTrain ? 'true' : 'false',
                'target_columns' => implode(',', $targetColumns),
            ],
        );

        if (! $response->successful()) {
            throw new \RuntimeException($this->extractError($response));
        }

        return $response->json();
    }

    /**
     * @param  list<string>  $targetColumns
     * @return array<string, mixed>
     */
    public function trainFromMongo(
        string $datasetKey,
        array $targetColumns,
        float $testSize = 0.2,
    ): array {
        $response = Http::timeout(120)
            ->post("{$this->baseUrl()}/train/mongodb", [
                'dataset_key' => $datasetKey,
                'target_columns' => $targetColumns,
                'test_size' => $testSize,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->extractError($response));
        }

        return $response->json();
    }

    /**
     * @param  array<string, UploadedFile|string|null>  $multipart
     * @param  array<string, scalar|null>  $query
     */
    private function postMultipart(string $path, array $multipart, array $query = []): Response
    {
        $url = $this->baseUrl().$path;
        if ($query !== []) {
            $url .= '?'.http_build_query(array_filter(
                $query,
                fn ($value) => $value !== null && $value !== '',
            ));
        }

        $request = Http::timeout(120);

        foreach ($multipart as $key => $value) {
            if ($value instanceof UploadedFile) {
                $request = $request->attach(
                    $key,
                    file_get_contents($value->getRealPath()),
                    $value->getClientOriginalName(),
                );
            }
        }

        return $request->post($url);
    }

    private function extractError(Response $response): string
    {
        $body = $response->json();
        if (is_array($body)) {
            if (isset($body['detail']) && is_string($body['detail'])) {
                return $body['detail'];
            }
            if (isset($body['message']) && is_string($body['message'])) {
                return $body['message'];
            }
        }

        return $response->body() ?: 'Model-AI request failed';
    }
}
