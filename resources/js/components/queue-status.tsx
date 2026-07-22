import { usePage, usePoll } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import type { QueueStatus } from '@/types/queue';

function statusLabel(status: QueueStatus): string {
    if (!status.available) {
        return 'Unavailable';
    }

    if (status.processing > 0) {
        return 'Busy';
    }

    if (status.waiting > 0) {
        return 'Queued';
    }

    return 'Idle';
}

function statusDotClass(status: QueueStatus): string {
    if (!status.available) {
        return 'bg-muted-foreground/50';
    }

    if (status.processing > 0) {
        return 'bg-amber-500';
    }

    if (status.waiting > 0) {
        return 'bg-sky-500';
    }

    return 'bg-emerald-500';
}

export function QueueStatusWidget() {
    const queueStatus = usePage().props.queueStatus;

    usePoll(
        5000,
        { only: ['queueStatus'] },
        { mode: 'rest', autoStart: queueStatus !== null },
    );

    if (!queueStatus) {
        return null;
    }

    const external = queueStatus.queues['external-query'];
    const defaults = queueStatus.queues.default;
    const label = statusLabel(queueStatus);
    const tooltip = queueStatus.available
        ? `external-query ${external.pending} waiting / ${external.processing} processing · default ${defaults.pending} waiting`
        : 'Queue status unavailable';

    return (
        <SidebarGroup className="group-data-[collapsible=icon]:p-0">
            <SidebarGroupContent>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            tooltip={{ children: tooltip }}
                            className="pointer-events-none cursor-default hover:bg-transparent active:bg-transparent"
                        >
                            <span
                                className={cn(
                                    'size-2 shrink-0 rounded-full',
                                    statusDotClass(queueStatus),
                                )}
                            />
                            <span className="flex min-w-0 flex-1 items-center justify-between gap-2 text-xs">
                                <span className="truncate text-muted-foreground">
                                    Queue
                                </span>
                                <span className="flex items-center gap-1.5 text-sidebar-foreground tabular-nums">
                                    {queueStatus.processing > 0 && (
                                        <LoaderCircle className="size-3 animate-spin text-amber-500" />
                                    )}
                                    <span>{queueStatus.waiting}</span>
                                    <span className="text-muted-foreground">
                                        {label}
                                    </span>
                                </span>
                            </span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem className="group-data-[collapsible=icon]:hidden">
                        <div className="px-2 pb-1 text-[11px] leading-relaxed text-muted-foreground">
                            <div className="flex justify-between gap-2">
                                <span>external-query</span>
                                <span className="text-sidebar-foreground tabular-nums">
                                    {external.pending}
                                    {external.processing > 0
                                        ? ` · ${external.processing} run`
                                        : ''}
                                </span>
                            </div>
                            <div className="flex justify-between gap-2">
                                <span>default</span>
                                <span className="text-sidebar-foreground tabular-nums">
                                    {defaults.pending}
                                    {defaults.processing > 0
                                        ? ` · ${defaults.processing} run`
                                        : ''}
                                </span>
                            </div>
                        </div>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}
