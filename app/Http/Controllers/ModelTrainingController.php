<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewModelTrainingCsvRequest;
use App\Http\Requests\StoreModelTrainingCsvRequest;
use App\Services\ModelAi\ModelAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModelTrainingController extends Controller
{
    public function __construct(
        private readonly ModelAiService $modelAi,
    ) {}

    public function index(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        return Inertia::render('model-training/index', [
            'modelAiConfigured' => $this->modelAi->isConfigured(),
            'datasets' => $this->modelAi->listDatasets(),
            'models' => $this->modelAi->listModels(),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        return Inertia::render('model-training/create', [
            'modelAiConfigured' => $this->modelAi->isConfigured(),
            'defaultDatasetKey' => (string) config('services.model_ai.default_dataset_key'),
        ]);
    }

    public function preview(PreviewModelTrainingCsvRequest $request): JsonResponse
    {
        abort_if($request->user() === null, 403);

        if (! $this->modelAi->isConfigured()) {
            return response()->json([
                'message' => 'Model-AI service is not configured.',
            ], 503);
        }

        try {
            $preview = $this->modelAi->previewDataset($request->file('training_csv'));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($preview);
    }

    public function uploadCsv(StoreModelTrainingCsvRequest $request): RedirectResponse
    {
        abort_if($request->user() === null, 403);

        if (! $this->modelAi->isConfigured()) {
            return back()->withErrors([
                'training_csv' => 'Model-AI service is not configured. Set MODEL_AI_URL in .env.',
            ]);
        }

        $targetColumns = array_values(array_unique(array_map(
            'strval',
            $request->input('target_columns', []),
        )));

        try {
            $result = $this->modelAi->uploadDataset(
                file: $request->file('training_csv'),
                datasetKey: (string) $request->string('dataset_key'),
                targetColumns: $targetColumns,
                name: (string) $request->string('name'),
                uploadedBy: $request->user()->email,
                autoTrain: $request->boolean('auto_train', true),
            );
        } catch (\Throwable $e) {
            return back()->withErrors([
                'training_csv' => $e->getMessage(),
            ]);
        }

        $trainedCount = count($result['training']['models'] ?? []);
        $rowCount = $result['dataset']['row_count'] ?? 0;
        $targetLabel = implode(', ', $targetColumns);

        return redirect()
            ->route('model-training.index')
            ->with('success', "Uploaded {$rowCount} rows and trained {$trainedCount} model(s) for: {$targetLabel}.");
    }

    public function retrain(Request $request): RedirectResponse
    {
        abort_if($request->user() === null, 403);

        $validated = $request->validate([
            'dataset_key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_]+$/'],
        ]);

        if (! $this->modelAi->isConfigured()) {
            return back()->withErrors([
                'dataset_key' => 'Model-AI service is not configured.',
            ]);
        }

        $dataset = $this->modelAi->getDataset($validated['dataset_key']);
        $targetColumns = $dataset['target_columns'] ?? [];

        if ($targetColumns === []) {
            return back()->withErrors([
                'dataset_key' => 'No target columns saved for this dataset. Upload the sheet again and select targets.',
            ]);
        }

        try {
            $result = $this->modelAi->trainFromMongo(
                datasetKey: $validated['dataset_key'],
                targetColumns: $targetColumns,
            );
        } catch (\Throwable $e) {
            return back()->withErrors([
                'dataset_key' => $e->getMessage(),
            ]);
        }

        $trainedCount = count($result['models'] ?? []);

        return back()->with('success', "Retrained {$trainedCount} model(s) for: ".implode(', ', $targetColumns).'.');
    }
}
