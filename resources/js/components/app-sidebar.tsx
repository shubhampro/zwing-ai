import { Link } from '@inertiajs/react';
import {
    ArrowLeftRight,
    ArrowDownToLine,
    BrainCircuit,
    Building2,
    ClipboardCheck,
    FileText,
    LayoutGrid,
    MessageSquare,
    RefreshCcw,
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
import { index as assistantIndex } from '@/routes/assistant';
import { index as invoiceReconciliationIndex } from '@/routes/invoice-reconciliation';
import { index as inboundSyncIndex } from '@/routes/inbound-sync';
import { index as outboundUnsyncIndex } from '@/routes/outbound-unsync';
import { index as stockTransactionReconciliationIndex } from '@/routes/stock-transaction-reconciliation';
import { index as transactionCheckerIndex } from '@/routes/transaction-checker';
import { index as modelTrainingIndex } from '@/routes/model-training';
import { index as organizationsIndex } from '@/routes/organizations';
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
        title: 'Outbound Unsync',
        href: outboundUnsyncIndex.url(),
        icon: RefreshCcw,
    },
    {
        title: 'Inbound Sync',
        href: inboundSyncIndex.url(),
        icon: ArrowDownToLine,
    },
    {
        title: 'Invoice reconciliation',
        href: invoiceReconciliationIndex.url(),
        icon: FileText,
    },
    {
        title: 'AI Assistant',
        href: assistantIndex.url(),
        icon: MessageSquare,
    },
    {
        title: 'Model training',
        href: modelTrainingIndex.url(),
        icon: BrainCircuit,
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
