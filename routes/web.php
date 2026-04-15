<?php

use App\Http\Controllers\DatabaseConnectionController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('database-connections/activity-logs', [DatabaseConnectionController::class, 'activityLogs'])
        ->name('database-connections.activity-logs');

    Route::resource('database-connections', DatabaseConnectionController::class)->except(['show', 'destroy']);
});

require __DIR__.'/settings.php';
