<?php

namespace App\Http\Controllers;

use App\Services\ReconciliationSummaryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ReconciliationSummaryService $reconciliationSummary,
    ) {}

    public function __invoke(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        $userId = $request->user()->id;

        return Inertia::render('dashboard', [
            'stockSummary' => $this->reconciliationSummary->latestStockSummaryForUser($userId),
            'invoiceSummary' => $this->reconciliationSummary->latestInvoiceSummaryForUser($userId),
        ]);
    }
}
