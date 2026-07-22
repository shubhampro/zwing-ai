<?php

namespace App\Enums;

enum ExternalQueryJobType: string
{
    case PullStock = 'pull_stock';
    case SyncRow = 'sync_row';
    case LogDetails = 'log_details';
    case ListZwingVendors = 'list_zwing_vendors';
    case AttachZwingVendor = 'attach_zwing_vendor';
    case UpdateFromZwingVendor = 'update_from_zwing_vendor';
    case TestOrgDbConnection = 'test_org_db_connection';
    case ListTxnCheckerDatabases = 'list_txn_checker_databases';
    case RunTxnChecker = 'run_txn_checker';
    case ServerHealthCheck = 'server_health_check';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::PullStock => 'Pull stock',
            self::SyncRow => 'Sync row',
            self::LogDetails => 'Log details',
            self::ListZwingVendors => 'List Zwing vendors',
            self::AttachZwingVendor => 'Attach Zwing vendor',
            self::UpdateFromZwingVendor => 'Update from Zwing vendor',
            self::TestOrgDbConnection => 'Test org DB connection',
            self::ListTxnCheckerDatabases => 'List txn checker DBs',
            self::RunTxnChecker => 'Run txn checker',
            self::ServerHealthCheck => 'Server health check',
        };
    }
}
