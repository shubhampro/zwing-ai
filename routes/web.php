<?php

use App\Http\Controllers\StockTransactionReconciliationController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

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
});

require __DIR__.'/settings.php';
