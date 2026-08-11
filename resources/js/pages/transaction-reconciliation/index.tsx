import { Head, Link, useForm } from '@inertiajs/react';
import { BarChart2, Eye, MoreHorizontal, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/TransactionReconciliationController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dashboard } from '@/routes';
import {
    create,
    index,
    report,
    show,
} from '@/routes/transaction-reconciliation';

type SessionStatus = 'pending' | 'processing' | 'completed' | 'failed';

type SessionRow = {
    id: number;
    name: string;
    type: string;
    type_label: string;
    v_id: number;
    zwing_file_name: string | null;
    erp_file_name: string | null;
    zwing_row_count: number | null;
    erp_row_count: number | null;
    status: SessionStatus;
    reconciled_at: string | null;
    created_at: string;
};

const statusVariant: Record<
    SessionStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'secondary',
    processing: 'outline',
    completed: 'default',
    failed: 'destructive',
};

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

export default function TransactionReconciliationIndex({
    sessions,
}: {
    sessions: SessionRow[];
}) {
    const [deleting, setDeleting] = useState<SessionRow | null>(null);
    const { delete: deleteSession, processing } = useForm();

    return (
        <>
            <Head title="Transaction reconciliation" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Transaction reconciliation"
                        description="Pull Packet / GRT (and later GRN/SPT) from Zwing + ERP, then match on txn id."
                    />
                    <Button size="sm" asChild>
                        <Link href={create.url()}>
                            <Plus className="size-4" />
                            New reconciliation
                        </Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full min-w-[720px] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">#</th>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">Type</th>
                                <th className="px-3 py-2 font-medium">
                                    Zwing rows
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    ERP rows
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Created
                                </th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {sessions.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No sessions yet.{' '}
                                        <Link
                                            href={create.url()}
                                            className="text-foreground underline"
                                        >
                                            Create reconciliation
                                        </Link>
                                        .
                                    </td>
                                </tr>
                            )}
                            {sessions.map((session) => (
                                <tr
                                    key={session.id}
                                    className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                >
                                    <td className="px-3 py-2 font-mono text-xs text-muted-foreground">
                                        <Link
                                            href={show.url(session.id)}
                                            className="hover:underline"
                                        >
                                            {session.id}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2 font-medium">
                                        <Link
                                            href={show.url(session.id)}
                                            className="hover:underline"
                                        >
                                            {session.name}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2">
                                        {session.type_label}
                                    </td>
                                    <td className="px-3 py-2 tabular-nums">
                                        {session.zwing_row_count ?? '—'}
                                    </td>
                                    <td className="px-3 py-2 tabular-nums">
                                        {session.erp_row_count ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge
                                            variant={
                                                statusVariant[session.status]
                                            }
                                            className="capitalize"
                                        >
                                            {session.status}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {formatDate(session.created_at)}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                >
                                                    <MoreHorizontal className="size-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={show.url(
                                                            session.id,
                                                        )}
                                                    >
                                                        <Eye className="size-4" />
                                                        Open
                                                    </Link>
                                                </DropdownMenuItem>
                                                {session.status ===
                                                    'completed' && (
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={report.url(
                                                                session.id,
                                                            )}
                                                        >
                                                            <BarChart2 className="size-4" />
                                                            Report
                                                        </Link>
                                                    </DropdownMenuItem>
                                                )}
                                                <DropdownMenuItem
                                                    onClick={() =>
                                                        setDeleting(session)
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                    Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete session?</DialogTitle>
                        <DialogDescription>
                            Deletes {deleting?.name} and all pulled rows.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleting(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={processing}
                            onClick={() => {
                                if (!deleting) {
                                    return;
                                }

                                deleteSession(destroy.url(deleting.id), {
                                    onSuccess: () => setDeleting(null),
                                });
                            }}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

TransactionReconciliationIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Transaction reconciliation', href: index.url() },
    ],
};
