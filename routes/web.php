<?php

use App\Http\Controllers\DatabaseConnectionController;
use App\Http\Controllers\DatabaseSessionContextController;
use App\Http\Controllers\QueryTableController;
use App\Http\Controllers\SavedQueryController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::inertia('stock-transaction-reconciliation', 'stock-transaction-reconciliation/index')
        ->name('stock-transaction-reconciliation.index');

    Route::get('query-table', [QueryTableController::class, 'index'])->name('query-table.index');
    Route::post('query-table/run', [QueryTableController::class, 'run'])->name('query-table.run');
    Route::post('query-table/saved-queries', [SavedQueryController::class, 'store'])->name('query-table.saved-queries.store');
    Route::put('query-table/saved-queries/{saved_query}', [SavedQueryController::class, 'update'])->name('query-table.saved-queries.update');
    Route::delete('query-table/saved-queries/{saved_query}', [SavedQueryController::class, 'destroy'])->name('query-table.saved-queries.destroy');

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
