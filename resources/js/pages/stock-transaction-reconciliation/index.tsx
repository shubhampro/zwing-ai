import { Head, Link, useForm } from '@inertiajs/react';
import { BarChart2, Eye, MoreHorizontal, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/StockTransactionReconciliationController';
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
} from '@/routes/stock-transaction-reconciliation';

type SessionStatus = 'pending' | 'processing' | 'completed' | 'failed';

type SessionRow = {
    id: number;
    name: string;
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

function DeleteDialog({
    session,
    open,
    onOpenChange,
}: {
    session: SessionRow;
    open: boolean;
    onOpenChange: (v: boolean) => void;
}) {
    const { delete: deleteSession, processing } = useForm();

    function confirm() {
        deleteSession(destroy.url(session.id), {
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete reconciliation session?</DialogTitle>
                    <DialogDescription>
                        This will permanently delete{' '}
                        <span className="font-medium text-foreground">
                            "{session.name}"
                        </span>{' '}
                        and all associated Zwing and ERP detail rows. This
                        action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={confirm}
                        disabled={processing}
                    >
                        {processing ? 'Deleting…' : 'Delete'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function StockTransactionReconciliationIndex({
    sessions,
}: {
    sessions: SessionRow[];
}) {
    const [deletingSession, setDeletingSession] = useState<SessionRow | null>(
        null,
    );

    return (
        <>
            <Head title="Stock–transaction reconciliation" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Stock–transaction reconciliation"
                        description="Upload Zwing and ERP CSVs to reconcile stock data. Each upload creates a new session."
                    />
                    <Button size="sm" asChild>
                        <Link href={create.url()}>New reconciliation</Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full min-w-[640px] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">#</th>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">
                                    Vendor ID
                                </th>
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
                                    Created at
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
                                        No reconciliation sessions found.{' '}
                                        <Link
                                            href={create.url()}
                                            className="text-foreground underline"
                                        >
                                            Start a new reconciliation
                                        </Link>{' '}
                                        by uploading Zwing and/or ERP CSV files.
                                    </td>
                                </tr>
                            )}
                            {sessions.map((s) => (
                                <tr
                                    key={s.id}
                                    className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                >
                                    <td className="px-3 py-2 font-mono text-xs text-muted-foreground">
                                        <Link
                                            href={show.url(s.id)}
                                            className="hover:underline"
                                        >
                                            {s.id}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2 font-medium">
                                        <Link
                                            href={show.url(s.id)}
                                            className="hover:underline"
                                        >
                                            {s.name}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2 tabular-nums">
                                        {s.v_id}
                                    </td>
                                    <td className="px-3 py-2 tabular-nums">
                                        {s.zwing_row_count ?? '—'}
                                    </td>
                                    <td className="px-3 py-2 tabular-nums">
                                        {s.erp_row_count ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge
                                            variant={statusVariant[s.status]}
                                            className="capitalize"
                                        >
                                            {s.status}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {formatDate(s.created_at)}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="cursor-pointer"
                                                >
                                                    <MoreHorizontal className="size-4" />
                                                    <span className="sr-only">
                                                        Actions
                                                    </span>
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <Link href={show.url(s.id)}>
                                                        <Eye className="size-4" />
                                                        Show
                                                    </Link>
                                                </DropdownMenuItem>
                                                {s.status === 'completed' && (
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={report.url(
                                                                s.id,
                                                            )}
                                                        >
                                                            <BarChart2 className="size-4" />
                                                            View report
                                                        </Link>
                                                    </DropdownMenuItem>
                                                )}
                                                <DropdownMenuItem
                                                    className="text-destructive focus:text-destructive"
                                                    onSelect={() =>
                                                        setDeletingSession(s)
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

            {deletingSession && (
                <DeleteDialog
                    session={deletingSession}
                    open={deletingSession !== null}
                    onOpenChange={(v) => {
                        if (!v) setDeletingSession(null);
                    }}
                />
            )}
        </>
    );
}

StockTransactionReconciliationIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Stock reconciliation', href: index.url() },
    ],
};
