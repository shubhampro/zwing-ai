import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowLeftRight,
    Building2,
    ClipboardCheck,
    CloudUpload,
    Database,
    FileText,
    Gauge,
    KeyRound,
    LayoutGrid,
    Mail,
    Package,
    PlayCircle,
    Plug,
    RefreshCw,
    Scale,
    Shield,
    Users,
    Wallet,
    WandSparkles,
} from 'lucide-react';
import { useMemo } from 'react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { QueueStatusWidget } from '@/components/queue-status';
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
import { index as externalQueryLogsIndex } from '@/routes/external-query-logs';
import { index as inboundEventsRunnerIndex } from '@/routes/inbound-events-runner';
import { index as invitesIndex } from '@/routes/invites';
import { index as invoiceReconciliationIndex } from '@/routes/invoice-reconciliation';
import { index as organizationsIndex } from '@/routes/organizations';
import { index as outboundSyncIndex } from '@/routes/outbound-sync';
import { index as payloadComposersIndex } from '@/routes/payload-composers';
import { index as rolesIndex } from '@/routes/roles';
import { index as serverHealthIndex } from '@/routes/server-health';
import { index as sqlQueriesIndex } from '@/routes/sql-queries';
import { index as stockTransactionReconciliationIndex } from '@/routes/stock-transaction-reconciliation';
import { index as thirdPartyApiBatchesIndex } from '@/routes/third-party-api-batches';
import { index as thirdPartyApisIndex } from '@/routes/third-party-apis';
import { index as transactionCheckerIndex } from '@/routes/transaction-checker';
import { index as transactionReconciliationIndex } from '@/routes/transaction-reconciliation';
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
                title: 'Transaction reconciliation',
                href: transactionReconciliationIndex.url(),
                icon: Package,
                permission: 'transaction-recon.view',
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
        title: 'Monitoring',
        icon: Gauge,
        role: 'admin',
        items: [
            {
                title: 'DB Health',
                href: serverHealthIndex.url(),
                icon: Activity,
                role: 'admin',
            },
            {
                title: 'Sync logs',
                href: externalQueryLogsIndex.url(),
                icon: Database,
                role: 'admin',
            },
            {
                title: 'Horizon',
                href: '/horizon',
                icon: Gauge,
                role: 'admin',
                external: true,
            },
        ],
    },
    {
        title: 'SQL Queries',
        href: sqlQueriesIndex.url(),
        icon: Database,
        permission: 'sql-queries.view',
    },
    {
        title: 'Payload composers',
        href: payloadComposersIndex.url(),
        icon: WandSparkles,
        permission: 'payload-composers.view',
    },
];

const footerNavItems: NavItem[] = [];

function filterNavItems(
    items: NavItem[],
    permissions: string[],
    roles: string[],
): NavItem[] {
    return items.flatMap((item) => {
        if (item.items?.length) {
            if (item.role && !roles.includes(item.role)) {
                return [];
            }

            if (item.permission && !permissions.includes(item.permission)) {
                return [];
            }

            const children = filterNavItems(item.items, permissions, roles);

            if (children.length === 0) {
                return [];
            }

            return [{ ...item, items: children }];
        }

        if (item.permission && !permissions.includes(item.permission)) {
            return [];
        }

        if (item.role && !roles.includes(item.role)) {
            return [];
        }

        return [item];
    });
}

export function AppSidebar() {
    const permissions = usePage().props.auth?.permissions ?? [];
    const roles = usePage().props.auth?.roles ?? [];

    const visibleNavItems = useMemo(
        () => filterNavItems(mainNavItems, permissions, roles),
        [permissions, roles],
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
                <QueueStatusWidget />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
