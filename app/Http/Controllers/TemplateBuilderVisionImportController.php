<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportTemplateFromVisionRequest;
use App\Services\TemplateBuilder\VisionTemplateImportService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class TemplateBuilderVisionImportController extends Controller
{
    public function __invoke(
        ImportTemplateFromVisionRequest $request,
        VisionTemplateImportService $service,
    ): JsonResponse {
        if (! $service->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Vision import is not configured. Set GEMINI_API_KEY, OPENAI_API_KEY, or ANTHROPIC_API_KEY in .env for the selected provider.',
            ], 503);
        }

        $file = $request->file('image');

        if ($file === null) {
            return response()->json([
                'success' => false,
                'message' => 'No image file was uploaded.',
            ], 422);
        }

        try {
            $result = $service->importFromImage(
                (string) file_get_contents($file->getRealPath()),
                (string) $file->getMimeType(),
                $request->validated('refinement'),
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 502);
        }

        return response()->json([
            'success' => true,
            'ejs' => $result['ejs'],
            'warnings' => $result['warnings'],
            'provider' => $result['provider'],
            'model' => $result['model'],
        ]);
    }
}
