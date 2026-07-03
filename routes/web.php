<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InboundEventsRunnerController;
use App\Http\Controllers\InvoiceReconciliationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\StockTransactionReconciliationController;
use App\Http\Controllers\TransactionCheckerController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('organizations', OrganizationController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

    Route::get('stock-transaction-reconciliation', [StockTransactionReconciliationController::class, 'index'])
        ->name('stock-transaction-reconciliation.index');
    Route::get('stock-transaction-reconciliation/create', [StockTransactionReconciliationController::class, 'create'])
        ->name('stock-transaction-reconciliation.create');
    Route::post('stock-transaction-reconciliation/csv', [StockTransactionReconciliationController::class, 'uploadCsv'])
        ->name('stock-transaction-reconciliation.csv');
    Route::get('stock-transaction-reconciliation/{stockReconSession}', [StockTransactionReconciliationController::class, 'show'])
        ->name('stock-transaction-reconciliation.show');
    Route::get('stock-transaction-reconciliation/{stockReconSession}/report', [StockTransactionReconciliationController::class, 'report'])
        ->name('stock-transaction-reconciliation.report');
    Route::get('stock-transaction-reconciliation/{stockReconSession}/report/export', [StockTransactionReconciliationController::class, 'exportReport'])
        ->name('stock-transaction-reconciliation.report.export');
    Route::get('stock-transaction-reconciliation/{stockReconSession}/report/log-details', [StockTransactionReconciliationController::class, 'reportLogDetails'])
        ->name('stock-transaction-reconciliation.report.log-details');
    Route::get('stock-transaction-reconciliation/{stockReconSession}/zwing-logs', [StockTransactionReconciliationController::class, 'zwingLogs'])
        ->name('stock-transaction-reconciliation.zwing-logs');
    Route::get('stock-transaction-reconciliation/{stockReconSession}/erp-logs', [StockTransactionReconciliationController::class, 'erpLogs'])
        ->name('stock-transaction-reconciliation.erp-logs');
    Route::delete('stock-transaction-reconciliation/{stockReconSession}', [StockTransactionReconciliationController::class, 'destroy'])
        ->name('stock-transaction-reconciliation.destroy');

    Route::get('transaction-checker', [TransactionCheckerController::class, 'index'])
        ->name('transaction-checker.index');
    Route::get('transaction-checker/databases', [TransactionCheckerController::class, 'databases'])
        ->name('transaction-checker.databases');
    Route::post('transaction-checker/check', [TransactionCheckerController::class, 'check'])
        ->name('transaction-checker.check');
    Route::delete('transaction-checker/sessions/{session}', [TransactionCheckerController::class, 'destroySession'])
        ->name('transaction-checker.sessions.destroy');
    Route::get('invoice-reconciliation', [InvoiceReconciliationController::class, 'index'])
        ->name('invoice-reconciliation.index');
    Route::get('invoice-reconciliation/create', [InvoiceReconciliationController::class, 'create'])
        ->name('invoice-reconciliation.create');
    Route::post('invoice-reconciliation/csv', [InvoiceReconciliationController::class, 'uploadCsv'])
        ->name('invoice-reconciliation.csv');
    Route::get('invoice-reconciliation/{invoiceReconSession}', [InvoiceReconciliationController::class, 'show'])
        ->name('invoice-reconciliation.show');
    Route::get('invoice-reconciliation/{invoiceReconSession}/report', [InvoiceReconciliationController::class, 'report'])
        ->name('invoice-reconciliation.report');
    Route::get('invoice-reconciliation/{invoiceReconSession}/report/export', [InvoiceReconciliationController::class, 'exportReport'])
        ->name('invoice-reconciliation.report.export');
    Route::delete('invoice-reconciliation/{invoiceReconSession}', [InvoiceReconciliationController::class, 'destroy'])
        ->name('invoice-reconciliation.destroy');

    Route::get('inbound-events-runner', [InboundEventsRunnerController::class, 'index'])
        ->name('inbound-events-runner.index');
    Route::post('inbound-events-runner/retry', [InboundEventsRunnerController::class, 'retry'])
        ->name('inbound-events-runner.retry');
});

require __DIR__.'/settings.php';
