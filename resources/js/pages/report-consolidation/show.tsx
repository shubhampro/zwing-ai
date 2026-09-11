import { Head, Link, useForm, usePoll } from '@inertiajs/react';
import {
    ArrowLeft,
    BarChart2,
    CheckCircle,
    Trash2,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/ReportConsolidationController';
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
import { dashboard } from '@/routes';
import { index, report, show } from '@/routes/report-consolidation';

type SessionStatus = 'pending' | 'processing' | 'completed' | 'failed';

type SessionData = {
    id: number;
    name: string;
    v_id: number;
    date_from: string | null;
    date_to: string | null;
    invoice_row_count: number | null;
    mop_row_count: number | null;
    invoice_processed_rows: number;
    mop_processed_rows: number;
    invoice_skipped_rows: number;
    mop_skipped_rows: number;
    invoice_query_ms: number | null;
    mop_query_ms: number | null;
    status: SessionStatus;
    failure_reason: string | null;
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

function ProgressBar({
    label,
    processed,
    total,
    skipped,
    status,
}: {
    label: string;
    processed: number;
    total: number | null;
    skipped: number;
    status: SessionStatus;
}) {
    const percentage =
        total && total > 0
            ? Math.min(100, Math.round((processed / total) * 100))
            : 0;
    const isDone =
        status === 'completed' ||
        (processed >= (total ?? 0) && total !== null);
    const isFailed = status === 'failed';
    const isActive = status !== 'pending' || processed > 0;

    return (
        <div className="flex flex-col gap-2 rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
            <div className="flex items-center justify-between gap-2">
                <p className="font-medium">{label}</p>
                <div className="shrink-0">
                    {isDone && !isFailed && (
                        <CheckCircle className="size-5 text-green-500" />
                    )}
                    {isFailed && (
                        <XCircle className="size-5 text-destructive" />
                    )}
                </div>
            </div>

            {isActive ? (
                <>
                    <div className="h-2.5 w-full overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full rounded-full bg-primary transition-all duration-500"
                            style={{ width: `${percentage}%` }}
                        />
                    </div>
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span>
                            {processed.toLocaleString()} /{' '}
                            {total !== null ? total.toLocaleString() : '—'} rows
                        </span>
                        <span>{percentage}%</span>
                    </div>
                    {skipped > 0 && (
                        <p className="text-xs text-amber-600 dark:text-amber-400">
                            {skipped.toLocaleString()}{' '}
                            {skipped === 1 ? 'row' : 'rows'} skipped — missing
                            or invalid data
                        </p>
                    )}
                </>
            ) : (
                <div className="h-2.5 w-full overflow-hidden rounded-full bg-muted">
                    <div className="h-full w-0 rounded-full bg-primary" />
                </div>
            )}
        </div>
    );
}

export default function ReportConsolidationShow({
    session,
}: {
    session: SessionData;
}) {
    const isFinished =
        session.status === 'completed' || session.status === 'failed';
    const [confirmOpen, setConfirmOpen] = useState(false);
    const { delete: deleteSession, processing } = useForm();

    usePoll(2000, { autoStart: !isFinished });

    function confirmDelete() {
        deleteSession(destroy.url(session.id), {
            onSuccess: () => setConfirmOpen(false),
        });
    }

    return (
        <>
            <Head title={`Report consolidation #${session.id}`} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <Link href={index.url()}>
                            <Button
                                variant="outline"
                                size="icon"
                                className="mt-0.5 shrink-0"
                            >
                                <ArrowLeft className="size-4" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-xl font-semibold tracking-tight">
                                {session.name}
                                <span className="ml-2 font-mono text-base text-muted-foreground">
                                    #{session.id}
                                </span>
                            </h1>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                Vendor ID: {session.v_id}
                                {session.date_from && session.date_to
                                    ? ` · ${session.date_from} to ${session.date_to}`
                                    : ''}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {session.status === 'pending' &&
                                    'Queued — waiting for the worker to start…'}
                                {session.status === 'processing' &&
                                    'Pulling Invoice and MOP rows — this page refreshes automatically.'}
                                {session.status === 'completed' &&
                                    `Completed at ${new Date(session.reconciled_at!).toLocaleString()}`}
                                {session.status === 'failed' &&
                                    (session.failure_reason ??
                                        'Processing failed. Please try again.')}
                            </p>
                        </div>
                    </div>

                    <div className="flex shrink-0 items-center gap-2">
                        <Badge
                            variant={statusVariant[session.status]}
                            className="capitalize"
                        >
                            {session.status}
                        </Badge>
                        {session.status === 'completed' && (
                            <Link href={report.url(session.id)}>
                                <Button variant="outline" size="sm">
                                    <BarChart2 className="size-4" />
                                    View report
                                </Button>
                            </Link>
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            className="text-destructive hover:text-destructive"
                            onClick={() => setConfirmOpen(true)}
                        >
                            <Trash2 className="size-4" />
                            Delete
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <ProgressBar
                        label="Invoice report"
                        processed={session.invoice_processed_rows}
                        total={session.invoice_row_count}
                        skipped={session.invoice_skipped_rows}
                        status={session.status}
                    />
                    <ProgressBar
                        label="MOP report"
                        processed={session.mop_processed_rows}
                        total={session.mop_row_count}
                        skipped={session.mop_skipped_rows}
                        status={session.status}
                    />
                </div>
            </div>

            <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete consolidation session?</DialogTitle>
                        <DialogDescription>
                            This will permanently delete{' '}
                            <span className="font-medium text-foreground">
                                "{session.name}"
                            </span>{' '}
                            and all associated invoice and MOP rows. This action
                            cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmOpen(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={confirmDelete}
                            disabled={processing}
                        >
                            {processing ? 'Deleting…' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ReportConsolidationShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Report consolidation', href: index.url() },
        { title: 'Session details', href: show.url(0) },
    ],
};
