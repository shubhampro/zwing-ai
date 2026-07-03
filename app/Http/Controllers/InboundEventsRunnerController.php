<?php

namespace App\Http\Controllers;

use App\Services\InboundEventsRetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InboundEventsRunnerController extends Controller
{
    public function index(): Response
    {
        abort_if(auth()->user() === null, 403);

        return Inertia::render('inbound-events-runner/index', [
            'queueNameList' => InboundEventsRetryService::QUEUE_NAME_LIST,
        ]);
    }

    public function retry(Request $request, InboundEventsRetryService $retryService): JsonResponse
    {
        abort_if($request->user() === null, 403);

        $validated = $request->validate([
            'log_id' => ['required', 'string', 'max:255'],
        ]);

        $response = $retryService->retry($validated['log_id']);

        return response()->json([
            'success' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->json() ?? $response->body(),
        ], $response->successful() ? 200 : 502);
    }
}
