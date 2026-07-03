import { Link } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Building2,
    ClipboardCheck,
    CloudUpload,
    Database,
    FileText,
    LayoutGrid,
    PlayCircle,
    Wallet,
    Plug
} from 'lucide-react';
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
import { index as invoiceReconciliationIndex } from '@/routes/invoice-reconciliation';
import { index as inboundEventsRunnerIndex } from '@/routes/inbound-events-runner';
import { index as outboundSyncIndex } from '@/routes/outbound-sync';
import { index as stockTransactionReconciliationIndex } from '@/routes/stock-transaction-reconciliation';
import { index as transactionCheckerIndex } from '@/routes/transaction-checker';
import { index as organizationsIndex } from '@/routes/organizations';
import { index as thirdPartyApiBatchesIndex } from '@/routes/third-party-api-batches';
import { index as thirdPartyApisIndex } from '@/routes/third-party-apis';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Organizations',
        href: organizationsIndex.url(),
        icon: Building2,
    },
    {
        title: 'Third party APIs',
        href: thirdPartyApisIndex.url(),
        icon: Plug,
    },
    {
        title: 'API batches',
        href: thirdPartyApiBatchesIndex.url(),
        icon: FileText,
    },
    {
        title: 'Stock reconciliation',
        href: stockTransactionReconciliationIndex.url(),
        icon: ArrowLeftRight,
    },
    {
        title: 'Transaction Checker',
        href: transactionCheckerIndex.url(),
        icon: ClipboardCheck,
    },
    {
        title: 'Invoice reconciliation',
        href: invoiceReconciliationIndex.url(),
        icon: FileText,
    },
    {
        title: 'Expense & cash reconciliation',
        href: expenseCashReconciliationIndex.url(),
        icon: Wallet,
    },
    {
        title: 'Inbound Events Runner',
        href: inboundEventsRunnerIndex.url(),
        icon: PlayCircle,
    },
    {
        title: 'Outbound Sync',
        href: outboundSyncIndex.url(),
        icon: CloudUpload,
    },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
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
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
