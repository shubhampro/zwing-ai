<?php

namespace App\Http\Controllers;

use App\Models\ChatAssistantSession;
use App\Services\ErpToZwing\InboundSyncQueryService;
use App\Services\ModelAi\ModelAiService;
use App\Services\ReconciliationSummaryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ReconciliationSummaryService $reconciliationSummary,
        private readonly InboundSyncQueryService $inboundSyncQuery,
        private readonly ModelAiService $modelAi,
    ) {}

    public function __invoke(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        $userId = $request->user()->id;

        return Inertia::render('dashboard', [
            'stockSummary' => $this->reconciliationSummary->latestStockSummaryForUser($userId),
            'invoiceSummary' => $this->reconciliationSummary->latestInvoiceSummaryForUser($userId),
            'moduleHub' => $this->moduleHub($userId),
        ]);
    }

    /**
     * @return array{
     *     assistant: array{title: string, description: string, href: string, ready: bool, status_label: string, highlights: list<string>},
     *     inbound_sync: array{title: string, description: string, href: string, ready: bool, status_label: string, highlights: list<string>},
     *     outbound_unsync: array{title: string, description: string, href: string, ready: bool, status_label: string, highlights: list<string>},
     *     model_training: array{title: string, description: string, href: string, ready: bool, status_label: string, highlights: list<string>}
     * }
     */
    private function moduleHub(int $userId): array
    {
        $chatSession = ChatAssistantSession::query()
            ->where('user_id', $userId)
            ->latest()
            ->first();

        $userMessages = collect($chatSession?->messages ?? [])
            ->where('role', 'user')
            ->count();

        $mongoConfigured = $this->inboundSyncQuery->isConfigured();
        $outboundConfigured = $this->isOutboundApiConfigured();
        $modelAiConfigured = $this->modelAi->isConfigured();
        $datasets = $modelAiConfigured ? $this->modelAi->listDatasets() : [];
        $models = $modelAiConfigured ? $this->modelAi->listModels() : [];
        $datasetCount = count($datasets);
        $modelCount = count($models);

        return [
            'assistant' => [
                'title' => 'AI Assistant',
                'description' => 'Chat with Zwing AI to debug sync issues, explore reconciliation data, and run model predictions.',
                'href' => route('assistant.index'),
                'ready' => true,
                'status_label' => $userMessages > 0 ? "{$userMessages} chats" : 'Ready',
                'highlights' => [
                    'Natural-language help for operations and support',
                    'Guided flows for predictions and reconciliation',
                    'Conversation history saved per user',
                ],
            ],
            'inbound_sync' => [
                'title' => 'Inbound Sync',
                'description' => 'Inspect ERP → Zwing inbound API events from MongoDB with success and failure breakdowns.',
                'href' => route('inbound-sync.index'),
                'ready' => $mongoConfigured,
                'status_label' => $mongoConfigured ? 'MongoDB connected' : 'Setup required',
                'highlights' => [
                    'Filter by vendor, client event name, and unique code',
                    'Failed events show request & response JSON',
                    'Grouped stats with expandable document details',
                ],
            ],
            'outbound_unsync' => [
                'title' => 'Outbound Unsync',
                'description' => 'Check which Zwing outbound transactions still need to sync to ERP.',
                'href' => route('outbound-unsync.index'),
                'ready' => $outboundConfigured,
                'status_label' => $outboundConfigured ? 'API connected' : 'Setup required',
                'highlights' => [
                    'Vendor, partner code, and date-range filters',
                    'Per-event success, pending, and failed counts',
                    'Copy unsynced document IDs for follow-up',
                ],
            ],
            'model_training' => [
                'title' => 'Model training',
                'description' => 'Upload store credit note sheets to MongoDB and train prediction models for the AI Assistant.',
                'href' => route('model-training.index'),
                'ready' => $modelAiConfigured,
                'status_label' => match (true) {
                    ! $modelAiConfigured => 'Setup required',
                    $datasetCount === 0 => 'Upload sheet',
                    default => "{$modelCount} models • {$datasetCount} dataset(s)",
                },
                'highlights' => [
                    'CSV or Excel upload from Zwing Console exports',
                    'Choose target columns dynamically per sheet',
                    'Retrain uses the same targets saved with the dataset',
                ],
            ],
        ];
    }

    private function isOutboundApiConfigured(): bool
    {
        return config('services.zwing_to_erp.base_url') !== null
            && config('services.zwing_to_erp.base_url') !== ''
            && config('services.zwing_to_erp.username') !== null
            && config('services.zwing_to_erp.username') !== ''
            && config('services.zwing_to_erp.password') !== null
            && config('services.zwing_to_erp.password') !== '';
    }
}
