import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Building2,
    ClipboardCheck,
    CloudUpload,
    Database,
    FileText,
    KeyRound,
    LayoutGrid,
    Mail,
    PlayCircle,
    Plug,
    RefreshCw,
    Scale,
    Shield,
    Users,
    Wallet,
} from 'lucide-react';
import { useMemo } from 'react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as expenseCashReconciliationIndex } from '@/routes/expense-cash-reconciliation';
import { index as inboundEventsRunnerIndex } from '@/routes/inbound-events-runner';
import { index as invitesIndex } from '@/routes/invites';
import { index as invoiceReconciliationIndex } from '@/routes/invoice-reconciliation';
import { index as organizationsIndex } from '@/routes/organizations';
import { index as outboundSyncIndex } from '@/routes/outbound-sync';
import { index as rolesIndex } from '@/routes/roles';
import { index as sqlQueriesIndex } from '@/routes/sql-queries';
import { index as stockTransactionReconciliationIndex } from '@/routes/stock-transaction-reconciliation';
import { index as thirdPartyApiBatchesIndex } from '@/routes/third-party-api-batches';
import { index as thirdPartyApisIndex } from '@/routes/third-party-apis';
import { index as transactionCheckerIndex } from '@/routes/transaction-checker';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Access',
        icon: Shield,
        items: [
            {
                title: 'Users',
                href: usersIndex.url(),
                icon: Users,
                permission: 'users.manage',
            },
            {
                title: 'Invites',
                href: invitesIndex.url(),
                icon: Mail,
                permission: 'invites.manage',
            },
            {
                title: 'Roles',
                href: rolesIndex.url(),
                icon: KeyRound,
                permission: 'roles.manage',
            },
        ],
    },
    {
        title: 'Organizations',
        href: organizationsIndex.url(),
        icon: Building2,
        permission: 'organizations.view',
    },
    {
        title: 'Integrations',
        icon: Plug,
        items: [
            {
                title: 'Third party APIs',
                href: thirdPartyApisIndex.url(),
                icon: Plug,
                permission: 'third-party-apis.view',
            },
            {
                title: 'API batches',
                href: thirdPartyApiBatchesIndex.url(),
                icon: FileText,
                permission: 'api-batches.view',
            },
        ],
    },
    {
        title: 'Reconciliation',
        icon: Scale,
        items: [
            {
                title: 'Stock reconciliation',
                href: stockTransactionReconciliationIndex.url(),
                icon: ArrowLeftRight,
                permission: 'stock-recon.view',
            },
            {
                title: 'Transaction Checker',
                href: transactionCheckerIndex.url(),
                icon: ClipboardCheck,
                permission: 'transaction-checker.view',
            },
            {
                title: 'Invoice reconciliation',
                href: invoiceReconciliationIndex.url(),
                icon: FileText,
                permission: 'invoice-recon.view',
            },
            {
                title: 'Expense & cash',
                href: expenseCashReconciliationIndex.url(),
                icon: Wallet,
                permission: 'expense-cash-recon.view',
            },
        ],
    },
    {
        title: 'Operations',
        icon: RefreshCw,
        items: [
            {
                title: 'Inbound Events Runner',
                href: inboundEventsRunnerIndex.url(),
                icon: PlayCircle,
                permission: 'inbound-events.view',
            },
            {
                title: 'Outbound Sync',
                href: outboundSyncIndex.url(),
                icon: CloudUpload,
                permission: 'outbound-sync.view',
            },
        ],
    },
    {
        title: 'SQL Queries',
        href: sqlQueriesIndex.url(),
        icon: Database,
        permission: 'sql-queries.view',
    },
];

const footerNavItems: NavItem[] = [];

function filterNavItems(items: NavItem[], permissions: string[]): NavItem[] {
    return items.flatMap((item) => {
        if (item.items?.length) {
            const children = filterNavItems(item.items, permissions);

            if (children.length === 0) {
                return [];
            }

            return [{ ...item, items: children }];
        }

        if (item.permission && !permissions.includes(item.permission)) {
            return [];
        }

        return [item];
    });
}

export function AppSidebar() {
    const permissions = usePage().props.auth?.permissions ?? [];

    const visibleNavItems = useMemo(
        () => filterNavItems(mainNavItems, permissions),
        [permissions],
    );

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={visibleNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
