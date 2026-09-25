<?php

namespace App\Http\Controllers;

use App\Services\Salesforce\SalesforceMonthlySupportReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesforceMonthlySupportController extends Controller
{
    public function index(Request $request, SalesforceMonthlySupportReport $report): Response
    {
        abort_if($request->user() === null, 403);

        return Inertia::render('sf-mbr/index', $report->monthIndex());
    }

    public function show(Request $request, string $month, SalesforceMonthlySupportReport $report): Response
    {
        abort_if($request->user() === null, 403);

        return Inertia::render('sf-mbr/show', $report->build($report->resolveMonth($month)));
    }
}
