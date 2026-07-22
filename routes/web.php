<?php

use App\Enums\Role;
use App\Http\Controllers\Auth\InviteRegistrationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseCashReconciliationController;
use App\Http\Controllers\InboundEventsRunnerController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\InvoiceReconciliationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationDatabaseConnectionController;
use App\Http\Controllers\OrganizationThirdPartyApiController;
use App\Http\Controllers\OutboundSyncController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServerHealthController;
use App\Http\Controllers\SqlQueryController;
use App\Http\Controllers\StockTransactionReconciliationController;
use App\Http\Controllers\ThirdPartyApiBatchController;
use App\Http\Controllers\ThirdPartyApiController;
use App\Http\Controllers\TransactionCheckerController;
use App\Http\Controllers\UserController;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('invite/{token}', [InviteRegistrationController::class, 'create'])
        ->name('invites.register');
    Route::post('invite/{token}', [InviteRegistrationController::class, 'store'])
        ->name('invites.register.store');
});

Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('permission:'.Permissions::UsersManage)->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::put('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::delete('users/{user}/force', [UserController::class, 'forceDestroy'])->name('users.force-destroy');
        Route::post('users/{user}/restore', [UserController::class, 'restore'])->name('users.restore');
    });

    Route::middleware('permission:'.Permissions::InvitesManage)->group(function () {
        Route::get('invites', [InviteController::class, 'index'])->name('invites.index');
        Route::post('invites', [InviteController::class, 'store'])->name('invites.store');
    });

    Route::middleware('permission:'.Permissions::RolesManage)->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    Route::get('organizations/zwing-vendors', [OrganizationController::class, 'zwingVendors'])
        ->middleware('permission:'.Permissions::OrganizationsAttachZwing)
        ->name('organizations.zwing-vendors');
    Route::post('organizations/attach-zwing-vendor', [OrganizationController::class, 'attachZwingVendor'])
        ->middleware('permission:'.Permissions::OrganizationsAttachZwing)
        ->name('organizations.attach-zwing-vendor');
    Route::post('organizations/update-zwing-vendor', [OrganizationController::class, 'updateFromZwingVendor'])
        ->middleware('permission:'.Permissions::OrganizationsAttachZwing)
        ->name('organizations.update-zwing-vendor');

    Route::get('organizations', [OrganizationController::class, 'index'])
        ->middleware('permission:'.Permissions::OrganizationsView)
        ->name('organizations.index');
    Route::get('organizations/create', [OrganizationController::class, 'create'])
        ->middleware('permission:'.Permissions::OrganizationsCreate)
        ->name('organizations.create');
    Route::post('organizations', [OrganizationController::class, 'store'])
        ->middleware('permission:'.Permissions::OrganizationsCreate)
        ->name('organizations.store');
    Route::get('organizations/{organization}', [OrganizationController::class, 'show'])
        ->middleware('permission:'.Permissions::OrganizationsView)
        ->name('organizations.show');
    Route::get('organizations/{organization}/edit', [OrganizationController::class, 'edit'])
        ->middleware('permission:'.Permissions::OrganizationsUpdate)
        ->name('organizations.edit');
    Route::put('organizations/{organization}', [OrganizationController::class, 'update'])
        ->middleware('permission:'.Permissions::OrganizationsUpdate)
        ->name('organizations.update');
    Route::delete('organizations/{organization}', [OrganizationController::class, 'destroy'])
        ->middleware('permission:'.Permissions::OrganizationsDelete)
        ->name('organizations.destroy');

    Route::post('organizations/{organization}/api-connections', [OrganizationThirdPartyApiController::class, 'storeForOrganization'])
        ->middleware('permission:'.Permissions::OrganizationsUpdate)
        ->name('organizations.api-connections.store');
    Route::put('organizations/{organization}/api-connections/{organizationThirdPartyApi}', [OrganizationThirdPartyApiController::class, 'updateForOrganization'])
        ->middleware('permission:'.Permissions::OrganizationsUpdate)
        ->name('organizations.api-connections.update');
    Route::delete('organizations/{organization}/api-connections/{organizationThirdPartyApi}', [OrganizationThirdPartyApiController::class, 'destroyForOrganization'])
        ->middleware('permission:'.Permissions::OrganizationsUpdate)
        ->name('organizations.api-connections.destroy');

    Route::middleware('role:'.Role::Admin->value)->group(function () {
        Route::get('organizations/{organization}/database-connections', [OrganizationDatabaseConnectionController::class, 'index'])
            ->name('organizations.database-connections.index');
        Route::post('organizations/{organization}/database-connections', [OrganizationDatabaseConnectionController::class, 'store'])
            ->name('organizations.database-connections.store');
        Route::put('organizations/{organization}/database-connections/{organizationDatabaseConnection}', [OrganizationDatabaseConnectionController::class, 'update'])
            ->name('organizations.database-connections.update');
        Route::post('organizations/{organization}/database-connections/{organizationDatabaseConnection}/test', [OrganizationDatabaseConnectionController::class, 'test'])
            ->name('organizations.database-connections.test');
        Route::delete('organizations/{organization}/database-connections/{organizationDatabaseConnection}', [OrganizationDatabaseConnectionController::class, 'destroy'])
            ->name('organizations.database-connections.destroy');
    });

    Route::get('third-party-apis', [ThirdPartyApiController::class, 'index'])
        ->middleware('permission:'.Permissions::ThirdPartyApisView)
        ->name('third-party-apis.index');
    Route::get('third-party-apis/create', [ThirdPartyApiController::class, 'create'])
        ->middleware('permission:'.Permissions::ThirdPartyApisManage)
        ->name('third-party-apis.create');
    Route::post('third-party-apis', [ThirdPartyApiController::class, 'store'])
        ->middleware('permission:'.Permissions::ThirdPartyApisManage)
        ->name('third-party-apis.store');
    Route::get('third-party-apis/{thirdPartyApi}/edit', [ThirdPartyApiController::class, 'edit'])
        ->middleware('permission:'.Permissions::ThirdPartyApisManage)
        ->name('third-party-apis.edit');
    Route::put('third-party-apis/{thirdPartyApi}', [ThirdPartyApiController::class, 'update'])
        ->middleware('permission:'.Permissions::ThirdPartyApisManage)
        ->name('third-party-apis.update');
    Route::delete('third-party-apis/{thirdPartyApi}', [ThirdPartyApiController::class, 'destroy'])
        ->middleware('permission:'.Permissions::ThirdPartyApisManage)
        ->name('third-party-apis.destroy');
    Route::post('third-party-apis/{thirdPartyApi}/connections', [OrganizationThirdPartyApiController::class, 'store'])
        ->middleware('permission:'.Permissions::ThirdPartyApisManage)
        ->name('third-party-apis.connections.store');
    Route::put('third-party-apis/{thirdPartyApi}/connections/{organizationThirdPartyApi}', [OrganizationThirdPartyApiController::class, 'update'])
        ->middleware('permission:'.Permissions::ThirdPartyApisManage)
        ->name('third-party-apis.connections.update');
    Route::delete('third-party-apis/{thirdPartyApi}/connections/{organizationThirdPartyApi}', [OrganizationThirdPartyApiController::class, 'destroy'])
        ->middleware('permission:'.Permissions::ThirdPartyApisManage)
        ->name('third-party-apis.connections.destroy');

    Route::get('third-party-api-batches', [ThirdPartyApiBatchController::class, 'index'])
        ->middleware('permission:'.Permissions::ApiBatchesView)
        ->name('third-party-api-batches.index');
    Route::get('third-party-api-batches/create', [ThirdPartyApiBatchController::class, 'create'])
        ->middleware('permission:'.Permissions::ApiBatchesManage)
        ->name('third-party-api-batches.create');
    Route::post('third-party-api-batches/csv', [ThirdPartyApiBatchController::class, 'uploadCsv'])
        ->middleware('permission:'.Permissions::ApiBatchesManage)
        ->name('third-party-api-batches.csv');
    Route::get('third-party-api-batches/{thirdPartyApiBatch}', [ThirdPartyApiBatchController::class, 'show'])
        ->middleware('permission:'.Permissions::ApiBatchesView)
        ->name('third-party-api-batches.show');
    Route::get('third-party-api-batches/{thirdPartyApiBatch}/report', [ThirdPartyApiBatchController::class, 'report'])
        ->middleware('permission:'.Permissions::ApiBatchesView)
        ->name('third-party-api-batches.report');
    Route::get('third-party-api-batches/{thirdPartyApiBatch}/report/export', [ThirdPartyApiBatchController::class, 'exportReport'])
        ->middleware('permission:'.Permissions::ApiBatchesView)
        ->name('third-party-api-batches.report.export');
    Route::post('third-party-api-batches/{thirdPartyApiBatch}/retry-failed', [ThirdPartyApiBatchController::class, 'retryFailed'])
        ->middleware('permission:'.Permissions::ApiBatchesManage)
        ->name('third-party-api-batches.retry-failed');
    Route::post('third-party-api-batches/{thirdPartyApiBatch}/items/{thirdPartyApiBatchItem}/retry', [ThirdPartyApiBatchController::class, 'retryItem'])
        ->middleware('permission:'.Permissions::ApiBatchesManage)
        ->name('third-party-api-batches.items.retry');
    Route::delete('third-party-api-batches/{thirdPartyApiBatch}', [ThirdPartyApiBatchController::class, 'destroy'])
        ->middleware('permission:'.Permissions::ApiBatchesManage)
        ->name('third-party-api-batches.destroy');

    Route::get('stock-transaction-reconciliation', [StockTransactionReconciliationController::class, 'index'])
        ->middleware('permission:'.Permissions::StockReconView)
        ->name('stock-transaction-reconciliation.index');
    Route::get('stock-transaction-reconciliation/create', [StockTransactionReconciliationController::class, 'create'])
        ->middleware('permission:'.Permissions::StockReconManage)
        ->name('stock-transaction-reconciliation.create');
    Route::get('stock-transaction-reconciliation/create-from-connections', [StockTransactionReconciliationController::class, 'createFromConnections'])
        ->middleware('permission:'.Permissions::StockReconManage)
        ->name('stock-transaction-reconciliation.create-from-connections');
    Route::post('stock-transaction-reconciliation/connections', [StockTransactionReconciliationController::class, 'storeFromConnections'])
        ->middleware('permission:'.Permissions::StockReconManage)
        ->name('stock-transaction-reconciliation.connections');
    Route::post('stock-transaction-reconciliation/csv', [StockTransactionReconciliationController::class, 'uploadCsv'])
        ->middleware('permission:'.Permissions::StockReconManage)
        ->name('stock-transaction-reconciliation.csv');
    Route::get('stock-transaction-reconciliation/{stockReconSession}', [StockTransactionReconciliationController::class, 'show'])
        ->middleware('permission:'.Permissions::StockReconView)
        ->name('stock-transaction-reconciliation.show');
    Route::get('stock-transaction-reconciliation/{stockReconSession}/report', [StockTransactionReconciliationController::class, 'report'])
        ->middleware('permission:'.Permissions::StockReconView)
        ->name('stock-transaction-reconciliation.report');
    Route::get('stock-transaction-reconciliation/{stockReconSession}/report/export', [StockTransactionReconciliationController::class, 'exportReport'])
        ->middleware('permission:'.Permissions::StockReconView)
        ->name('stock-transaction-reconciliation.report.export');
    Route::get('stock-transaction-reconciliation/{stockReconSession}/report/log-details', [StockTransactionReconciliationController::class, 'reportLogDetails'])
        ->middleware('permission:'.Permissions::StockReconView)
        ->name('stock-transaction-reconciliation.report.log-details');
    Route::get('stock-transaction-reconciliation/{stockReconSession}/zwing-logs', [StockTransactionReconciliationController::class, 'zwingLogs'])
        ->middleware('permission:'.Permissions::StockReconView)
        ->name('stock-transaction-reconciliation.zwing-logs');
    Route::get('stock-transaction-reconciliation/{stockReconSession}/erp-logs', [StockTransactionReconciliationController::class, 'erpLogs'])
        ->middleware('permission:'.Permissions::StockReconView)
        ->name('stock-transaction-reconciliation.erp-logs');
    Route::delete('stock-transaction-reconciliation/{stockReconSession}', [StockTransactionReconciliationController::class, 'destroy'])
        ->middleware('permission:'.Permissions::StockReconManage)
        ->name('stock-transaction-reconciliation.destroy');

    Route::get('transaction-checker', [TransactionCheckerController::class, 'index'])
        ->middleware('permission:'.Permissions::TransactionCheckerView)
        ->name('transaction-checker.index');
    Route::get('transaction-checker/databases', [TransactionCheckerController::class, 'databases'])
        ->middleware('permission:'.Permissions::TransactionCheckerView)
        ->name('transaction-checker.databases');
    Route::post('transaction-checker/check', [TransactionCheckerController::class, 'check'])
        ->middleware('permission:'.Permissions::TransactionCheckerManage)
        ->name('transaction-checker.check');
    Route::delete('transaction-checker/sessions/{session}', [TransactionCheckerController::class, 'destroySession'])
        ->middleware('permission:'.Permissions::TransactionCheckerManage)
        ->name('transaction-checker.sessions.destroy');

    Route::get('invoice-reconciliation', [InvoiceReconciliationController::class, 'index'])
        ->middleware('permission:'.Permissions::InvoiceReconView)
        ->name('invoice-reconciliation.index');
    Route::get('invoice-reconciliation/create', [InvoiceReconciliationController::class, 'create'])
        ->middleware('permission:'.Permissions::InvoiceReconManage)
        ->name('invoice-reconciliation.create');
    Route::post('invoice-reconciliation/csv', [InvoiceReconciliationController::class, 'uploadCsv'])
        ->middleware('permission:'.Permissions::InvoiceReconManage)
        ->name('invoice-reconciliation.csv');
    Route::get('invoice-reconciliation/{invoiceReconSession}', [InvoiceReconciliationController::class, 'show'])
        ->middleware('permission:'.Permissions::InvoiceReconView)
        ->name('invoice-reconciliation.show');
    Route::get('invoice-reconciliation/{invoiceReconSession}/report', [InvoiceReconciliationController::class, 'report'])
        ->middleware('permission:'.Permissions::InvoiceReconView)
        ->name('invoice-reconciliation.report');
    Route::get('invoice-reconciliation/{invoiceReconSession}/report/export', [InvoiceReconciliationController::class, 'exportReport'])
        ->middleware('permission:'.Permissions::InvoiceReconView)
        ->name('invoice-reconciliation.report.export');
    Route::delete('invoice-reconciliation/{invoiceReconSession}', [InvoiceReconciliationController::class, 'destroy'])
        ->middleware('permission:'.Permissions::InvoiceReconManage)
        ->name('invoice-reconciliation.destroy');

    Route::get('expense-cash-reconciliation', [ExpenseCashReconciliationController::class, 'index'])
        ->middleware('permission:'.Permissions::ExpenseCashReconView)
        ->name('expense-cash-reconciliation.index');
    Route::get('expense-cash-reconciliation/create', [ExpenseCashReconciliationController::class, 'create'])
        ->middleware('permission:'.Permissions::ExpenseCashReconManage)
        ->name('expense-cash-reconciliation.create');
    Route::post('expense-cash-reconciliation/csv', [ExpenseCashReconciliationController::class, 'uploadCsv'])
        ->middleware('permission:'.Permissions::ExpenseCashReconManage)
        ->name('expense-cash-reconciliation.csv');
    Route::get('expense-cash-reconciliation/{expenseCashReconSession}', [ExpenseCashReconciliationController::class, 'show'])
        ->middleware('permission:'.Permissions::ExpenseCashReconView)
        ->name('expense-cash-reconciliation.show');
    Route::get('expense-cash-reconciliation/{expenseCashReconSession}/report', [ExpenseCashReconciliationController::class, 'report'])
        ->middleware('permission:'.Permissions::ExpenseCashReconView)
        ->name('expense-cash-reconciliation.report');
    Route::get('expense-cash-reconciliation/{expenseCashReconSession}/report/export', [ExpenseCashReconciliationController::class, 'exportReport'])
        ->middleware('permission:'.Permissions::ExpenseCashReconView)
        ->name('expense-cash-reconciliation.report.export');
    Route::delete('expense-cash-reconciliation/{expenseCashReconSession}', [ExpenseCashReconciliationController::class, 'destroy'])
        ->middleware('permission:'.Permissions::ExpenseCashReconManage)
        ->name('expense-cash-reconciliation.destroy');

    Route::get('inbound-events-runner', [InboundEventsRunnerController::class, 'index'])
        ->middleware('permission:'.Permissions::InboundEventsView)
        ->name('inbound-events-runner.index');
    Route::post('inbound-events-runner/retry', [InboundEventsRunnerController::class, 'retry'])
        ->middleware('permission:'.Permissions::InboundEventsManage)
        ->name('inbound-events-runner.retry');

    Route::get('outbound-sync', [OutboundSyncController::class, 'index'])
        ->middleware('permission:'.Permissions::OutboundSyncView)
        ->name('outbound-sync.index');
    Route::post('outbound-sync/fetch', [OutboundSyncController::class, 'fetch'])
        ->middleware('permission:'.Permissions::OutboundSyncManage)
        ->name('outbound-sync.fetch');

    Route::middleware('permission:'.Permissions::ServerHealthView)->group(function () {
        Route::get('server-health', [ServerHealthController::class, 'index'])->name('server-health.index');
    });
    Route::post('server-health/refresh', [ServerHealthController::class, 'refresh'])
        ->middleware('permission:'.Permissions::ServerHealthManage)
        ->name('server-health.refresh');

    Route::get('sql-queries', [SqlQueryController::class, 'index'])
        ->middleware('permission:'.Permissions::SqlQueriesView)
        ->name('sql-queries.index');
    Route::post('sql-queries', [SqlQueryController::class, 'store'])
        ->middleware('permission:'.Permissions::SqlQueriesManage)
        ->name('sql-queries.store');
    Route::put('sql-queries/{savedSqlQuery}', [SqlQueryController::class, 'update'])
        ->middleware('permission:'.Permissions::SqlQueriesManage)
        ->name('sql-queries.update');
    Route::delete('sql-queries/{savedSqlQuery}', [SqlQueryController::class, 'destroy'])
        ->middleware('permission:'.Permissions::SqlQueriesManage)
        ->name('sql-queries.destroy');
    Route::get('sql-queries/{savedSqlQuery}/export', [SqlQueryController::class, 'export'])
        ->middleware('permission:'.Permissions::SqlQueriesView)
        ->name('sql-queries.export');
    Route::post('sql-queries/import', [SqlQueryController::class, 'import'])
        ->middleware('permission:'.Permissions::SqlQueriesManage)
        ->name('sql-queries.import');
});

require __DIR__.'/settings.php';
