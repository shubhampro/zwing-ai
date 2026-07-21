<?php

namespace App\Support;

final class Permissions
{
    public const UsersManage = 'users.manage';

    public const InvitesManage = 'invites.manage';

    public const RolesManage = 'roles.manage';

    public const OrganizationsView = 'organizations.view';

    public const OrganizationsCreate = 'organizations.create';

    public const OrganizationsUpdate = 'organizations.update';

    public const OrganizationsDelete = 'organizations.delete';

    public const OrganizationsAttachZwing = 'organizations.attach-zwing';

    public const ThirdPartyApisView = 'third-party-apis.view';

    public const ThirdPartyApisManage = 'third-party-apis.manage';

    public const ApiBatchesView = 'api-batches.view';

    public const ApiBatchesManage = 'api-batches.manage';

    public const StockReconView = 'stock-recon.view';

    public const StockReconManage = 'stock-recon.manage';

    public const InvoiceReconView = 'invoice-recon.view';

    public const InvoiceReconManage = 'invoice-recon.manage';

    public const ExpenseCashReconView = 'expense-cash-recon.view';

    public const ExpenseCashReconManage = 'expense-cash-recon.manage';

    public const TransactionCheckerView = 'transaction-checker.view';

    public const TransactionCheckerManage = 'transaction-checker.manage';

    public const InboundEventsView = 'inbound-events.view';

    public const InboundEventsManage = 'inbound-events.manage';

    public const OutboundSyncView = 'outbound-sync.view';

    public const OutboundSyncManage = 'outbound-sync.manage';

    public const SqlQueriesView = 'sql-queries.view';

    public const SqlQueriesManage = 'sql-queries.manage';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::UsersManage,
            self::InvitesManage,
            self::RolesManage,
            self::OrganizationsView,
            self::OrganizationsCreate,
            self::OrganizationsUpdate,
            self::OrganizationsDelete,
            self::OrganizationsAttachZwing,
            self::ThirdPartyApisView,
            self::ThirdPartyApisManage,
            self::ApiBatchesView,
            self::ApiBatchesManage,
            self::StockReconView,
            self::StockReconManage,
            self::InvoiceReconView,
            self::InvoiceReconManage,
            self::ExpenseCashReconView,
            self::ExpenseCashReconManage,
            self::TransactionCheckerView,
            self::TransactionCheckerManage,
            self::InboundEventsView,
            self::InboundEventsManage,
            self::OutboundSyncView,
            self::OutboundSyncManage,
            self::SqlQueriesView,
            self::SqlQueriesManage,
        ];
    }

    /**
     * @return list<string>
     */
    public static function viewPermissions(): array
    {
        return array_values(array_filter(
            self::all(),
            fn (string $permission): bool => str_ends_with($permission, '.view'),
        ));
    }

    /**
     * Operator: everything except user/invite/role admin.
     *
     * @return list<string>
     */
    public static function operatorPermissions(): array
    {
        return array_values(array_filter(
            self::all(),
            fn (string $permission): bool => ! in_array($permission, [
                self::UsersManage,
                self::InvitesManage,
                self::RolesManage,
            ], true),
        ));
    }

    /**
     * Group permission names by resource prefix (text before first ".").
     *
     * @return array<string, list<string>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::all() as $permission) {
            $prefix = strstr($permission, '.', true) ?: $permission;
            $grouped[$prefix][] = $permission;
        }

        return $grouped;
    }
}
