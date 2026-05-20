<?php

use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\StockTransactionReconciliationController;
use App\Http\Controllers\TransactionCheckerController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

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
});

require __DIR__.'/settings.php';
