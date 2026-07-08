<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseCashReconciliationController;
use App\Http\Controllers\InboundEventsRunnerController;
use App\Http\Controllers\InvoiceReconciliationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationThirdPartyApiController;
use App\Http\Controllers\OutboundSyncController;
use App\Http\Controllers\PrintInvoiceController;
use App\Http\Controllers\SqlQueryController;
use App\Http\Controllers\StockTransactionReconciliationController;
use App\Http\Controllers\TemplateBuilderController;
use App\Http\Controllers\TemplateBuilderVisionImportController;
use App\Http\Controllers\ThirdPartyApiBatchController;
use App\Http\Controllers\ThirdPartyApiController;
use App\Http\Controllers\TransactionCheckerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('organizations', OrganizationController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);

    Route::post('organizations/{organization}/api-connections', [OrganizationThirdPartyApiController::class, 'storeForOrganization'])
        ->name('organizations.api-connections.store');
    Route::put('organizations/{organization}/api-connections/{organizationThirdPartyApi}', [OrganizationThirdPartyApiController::class, 'updateForOrganization'])
        ->name('organizations.api-connections.update');
    Route::delete('organizations/{organization}/api-connections/{organizationThirdPartyApi}', [OrganizationThirdPartyApiController::class, 'destroyForOrganization'])
        ->name('organizations.api-connections.destroy');

    Route::resource('third-party-apis', ThirdPartyApiController::class)
        ->except(['show']);

    Route::post('third-party-apis/{thirdPartyApi}/connections', [OrganizationThirdPartyApiController::class, 'store'])
        ->name('third-party-apis.connections.store');
    Route::put('third-party-apis/{thirdPartyApi}/connections/{organizationThirdPartyApi}', [OrganizationThirdPartyApiController::class, 'update'])
        ->name('third-party-apis.connections.update');
    Route::delete('third-party-apis/{thirdPartyApi}/connections/{organizationThirdPartyApi}', [OrganizationThirdPartyApiController::class, 'destroy'])
        ->name('third-party-apis.connections.destroy');

    Route::get('third-party-api-batches', [ThirdPartyApiBatchController::class, 'index'])
        ->name('third-party-api-batches.index');
    Route::get('third-party-api-batches/create', [ThirdPartyApiBatchController::class, 'create'])
        ->name('third-party-api-batches.create');
    Route::post('third-party-api-batches/csv', [ThirdPartyApiBatchController::class, 'uploadCsv'])
        ->name('third-party-api-batches.csv');
    Route::get('third-party-api-batches/{thirdPartyApiBatch}', [ThirdPartyApiBatchController::class, 'show'])
        ->name('third-party-api-batches.show');
    Route::get('third-party-api-batches/{thirdPartyApiBatch}/report', [ThirdPartyApiBatchController::class, 'report'])
        ->name('third-party-api-batches.report');
    Route::get('third-party-api-batches/{thirdPartyApiBatch}/report/export', [ThirdPartyApiBatchController::class, 'exportReport'])
        ->name('third-party-api-batches.report.export');
    Route::post('third-party-api-batches/{thirdPartyApiBatch}/retry-failed', [ThirdPartyApiBatchController::class, 'retryFailed'])
        ->name('third-party-api-batches.retry-failed');
    Route::post('third-party-api-batches/{thirdPartyApiBatch}/items/{thirdPartyApiBatchItem}/retry', [ThirdPartyApiBatchController::class, 'retryItem'])
        ->name('third-party-api-batches.items.retry');
    Route::delete('third-party-api-batches/{thirdPartyApiBatch}', [ThirdPartyApiBatchController::class, 'destroy'])
        ->name('third-party-api-batches.destroy');

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

    Route::get('expense-cash-reconciliation', [ExpenseCashReconciliationController::class, 'index'])
        ->name('expense-cash-reconciliation.index');
    Route::get('expense-cash-reconciliation/create', [ExpenseCashReconciliationController::class, 'create'])
        ->name('expense-cash-reconciliation.create');
    Route::post('expense-cash-reconciliation/csv', [ExpenseCashReconciliationController::class, 'uploadCsv'])
        ->name('expense-cash-reconciliation.csv');
    Route::get('expense-cash-reconciliation/{expenseCashReconSession}', [ExpenseCashReconciliationController::class, 'show'])
        ->name('expense-cash-reconciliation.show');
    Route::get('expense-cash-reconciliation/{expenseCashReconSession}/report', [ExpenseCashReconciliationController::class, 'report'])
        ->name('expense-cash-reconciliation.report');
    Route::get('expense-cash-reconciliation/{expenseCashReconSession}/report/export', [ExpenseCashReconciliationController::class, 'exportReport'])
        ->name('expense-cash-reconciliation.report.export');
    Route::delete('expense-cash-reconciliation/{expenseCashReconSession}', [ExpenseCashReconciliationController::class, 'destroy'])
        ->name('expense-cash-reconciliation.destroy');

    Route::get('inbound-events-runner', [InboundEventsRunnerController::class, 'index'])
        ->name('inbound-events-runner.index');
    Route::post('inbound-events-runner/retry', [InboundEventsRunnerController::class, 'retry'])
        ->name('inbound-events-runner.retry');

    Route::get('outbound-sync', [OutboundSyncController::class, 'index'])
        ->name('outbound-sync.index');
    Route::post('outbound-sync/fetch', [OutboundSyncController::class, 'fetch'])
        ->name('outbound-sync.fetch');

    Route::get('print-invoice', [PrintInvoiceController::class, 'index'])
        ->name('print-invoice.index');
    Route::post('print-invoice/preview', [PrintInvoiceController::class, 'preview'])
        ->name('print-invoice.preview');

    Route::get('template-builder', TemplateBuilderController::class)
        ->name('template-builder.index');

    Route::post('template-builder/import-vision', TemplateBuilderVisionImportController::class)
        ->middleware('throttle:5,1')
        ->name('template-builder.import-vision');

    Route::get('sql-queries', [SqlQueryController::class, 'index'])
        ->name('sql-queries.index');
    Route::post('sql-queries', [SqlQueryController::class, 'store'])
        ->name('sql-queries.store');
    Route::put('sql-queries/{savedSqlQuery}', [SqlQueryController::class, 'update'])
        ->name('sql-queries.update');
    Route::delete('sql-queries/{savedSqlQuery}', [SqlQueryController::class, 'destroy'])
        ->name('sql-queries.destroy');
    Route::get('sql-queries/{savedSqlQuery}/export', [SqlQueryController::class, 'export'])
        ->name('sql-queries.export');
    Route::post('sql-queries/import', [SqlQueryController::class, 'import'])
        ->name('sql-queries.import');
});

require __DIR__.'/settings.php';
