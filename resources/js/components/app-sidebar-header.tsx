import { Breadcrumbs } from '@/components/breadcrumbs';
import { DatabaseContextSelector } from '@/components/database-context-selector';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex min-h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 py-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:min-h-12 md:gap-3 md:px-4">
            <div className="flex min-w-0 flex-1 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div
                className="bg-border hidden h-7 w-px shrink-0 self-center sm:block"
                aria-hidden
            />
            <DatabaseContextSelector />
        </header>
    );
}
