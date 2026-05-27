<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseConnectionController;
use App\Http\Controllers\DatabaseSessionContextController;
use App\Http\Controllers\InvoiceReconciliationController;
use App\Http\Controllers\StockTransactionReconciliationController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

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
    Route::delete('stock-transaction-reconciliation/{stockReconSession}', [StockTransactionReconciliationController::class, 'destroy'])
        ->name('stock-transaction-reconciliation.destroy');

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

    Route::get('database-session-context/databases', [DatabaseSessionContextController::class, 'databases'])
        ->name('database-session-context.databases');
    Route::put('database-session-context', [DatabaseSessionContextController::class, 'update'])
        ->name('database-session-context.update');

    Route::get('database-connections/activity-logs', [DatabaseConnectionController::class, 'activityLogs'])
        ->name('database-connections.activity-logs');

    Route::post('database-connections/test', [DatabaseConnectionController::class, 'testConnection'])
        ->name('database-connections.test');

    Route::resource('database-connections', DatabaseConnectionController::class)->except(['show', 'destroy']);
});

require __DIR__.'/settings.php';
