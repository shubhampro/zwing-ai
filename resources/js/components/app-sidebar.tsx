import { Link } from '@inertiajs/react';
import { ArrowLeftRight, Building2, ClipboardCheck, FileText, LayoutGrid, Plug } from 'lucide-react';
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
import { index as databaseConnectionsIndex } from '@/routes/database-connections';
import { index as invoiceReconciliationIndex } from '@/routes/invoice-reconciliation';
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
