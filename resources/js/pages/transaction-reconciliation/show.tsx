import { Head, Link, useForm, usePoll } from '@inertiajs/react';
import {
    ArrowLeft,
    BarChart2,
    CheckCircle,
    LoaderCircle,
    Trash2,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/TransactionReconciliationController';
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
import { index, report, show } from '@/routes/transaction-reconciliation';

type SessionStatus = 'pending' | 'processing' | 'completed' | 'failed';

type SessionData = {
    id: number;
    name: string;
    type: string;
    type_label: string;
    v_id: number;
    source?: string;
    zwing_file_name: string | null;
    erp_file_name: string | null;
    zwing_row_count: number | null;
    erp_row_count: number | null;
    zwing_processed_rows: number;
    erp_processed_rows: number;
    zwing_skipped_rows: number;
    erp_skipped_rows: number;
    zwing_query_ms: number | null;
    erp_query_ms: number | null;
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

function formatDuration(ms: number | null): string | null {
    if (ms === null) {
        return null;
    }

    if (ms < 1000) {
        return `${ms} ms`;
    }

    const totalSeconds = ms / 1000;

    if (totalSeconds < 60) {
        return `${totalSeconds.toFixed(totalSeconds < 10 ? 1 : 0)} s`;
    }

    const minutes = Math.floor(totalSeconds / 60);
    const seconds = Math.round(totalSeconds % 60);

    return `${minutes}m ${seconds}s`;
}

function ProgressCard({
    label,
    fileName,
    processed,
    total,
    skipped,
    status,
    queryMs,
}: {
    label: string;
    fileName: string | null;
    processed: number;
    total: number | null;
    skipped: number;
    status: SessionStatus;
    queryMs: number | null;
}) {
    const isActive = fileName !== null;
    const knownTotal = total !== null && total > 0;
    const percentage = knownTotal
        ? Math.min(100, Math.round((processed / total) * 100))
        : status === 'completed' && isActive
          ? 100
          : 0;
    const isRunning =
        isActive &&
        (status === 'pending' || status === 'processing') &&
        total === null;
    const isDone =
        status === 'completed' ||
        (isActive && knownTotal && processed >= total && !isRunning);
    const isFailed = status === 'failed';
    const durationLabel = formatDuration(queryMs);

    return (
        <div className="flex flex-col gap-2 rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
            <div className="flex items-center justify-between gap-2">
                <div>
                    <p className="font-medium">{label}</p>
                    <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                        {fileName ?? 'Not selected'}
                    </p>
                </div>
                {isActive && (
                    <div className="shrink-0">
                        {isRunning && (
                            <LoaderCircle className="size-5 animate-spin text-muted-foreground" />
                        )}
                        {isDone && !isFailed && !isRunning && (
                            <CheckCircle className="size-5 text-green-500" />
                        )}
                        {isFailed && (
                            <XCircle className="size-5 text-destructive" />
                        )}
                    </div>
                )}
            </div>

            {isActive && (
                <>
                    <div className="h-2.5 w-full overflow-hidden rounded-full bg-muted">
                        {isRunning && !knownTotal ? (
                            <div className="h-full w-1/3 animate-pulse rounded-full bg-primary/80" />
                        ) : (
                            <div
                                className="h-full rounded-full bg-primary transition-all duration-500"
                                style={{ width: `${percentage}%` }}
                            />
                        )}
                    </div>
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span>
                            {!knownTotal
                                ? `${processed.toLocaleString()} rows pulled`
                                : `${processed.toLocaleString()} / ${total?.toLocaleString()} rows`}
                        </span>
                        <span>
                            {isRunning && !knownTotal
                                ? 'Loading…'
                                : `${percentage}%`}
                        </span>
                    </div>
                    {durationLabel && (
                        <p className="text-xs text-muted-foreground">
                            Query time:{' '}
                            <span className="font-medium text-foreground">
                                {durationLabel}
                            </span>
                        </p>
                    )}
                    {skipped > 0 && (
                        <p className="text-xs text-amber-600 dark:text-amber-400">
                            {skipped.toLocaleString()} rows skipped
                        </p>
                    )}
                </>
            )}
        </div>
    );
}

export default function TransactionReconciliationShow({
    session,
}: {
    session: SessionData;
}) {
    const isFinished =
        session.status === 'completed' || session.status === 'failed';
    const [confirmOpen, setConfirmOpen] = useState(false);
    const { delete: deleteSession, processing } = useForm();

    usePoll(2000, { autoStart: !isFinished });

    return (
        <>
            <Head title={`${session.type_label} #${session.id}`} />

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
                                {session.type_label} · Vendor {session.v_id}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {session.status === 'pending' &&
                                    'Queued — waiting for worker…'}
                                {session.status === 'processing' &&
                                    'Pulling rows — page refreshes automatically.'}
                                {session.status === 'completed' &&
                                    session.reconciled_at &&
                                    `Completed at ${new Date(session.reconciled_at).toLocaleString()}`}
                                {session.status === 'failed' &&
                                    'Processing failed.'}
                            </p>
                            {session.failure_reason && (
                                <p className="mt-2 text-sm text-destructive">
                                    {session.failure_reason}
                                </p>
                            )}
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
                            <Button variant="outline" size="sm" asChild>
                                <Link href={report.url(session.id)}>
                                    <BarChart2 className="size-4" />
                                    Report
                                </Link>
                            </Button>
                        )}
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => setConfirmOpen(true)}
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <ProgressCard
                        label="Zwing"
                        fileName={session.zwing_file_name}
                        processed={session.zwing_processed_rows}
                        total={session.zwing_row_count}
                        skipped={session.zwing_skipped_rows}
                        status={session.status}
                        queryMs={session.zwing_query_ms}
                    />
                    <ProgressCard
                        label="ERP"
                        fileName={session.erp_file_name}
                        processed={session.erp_processed_rows}
                        total={session.erp_row_count}
                        skipped={session.erp_skipped_rows}
                        status={session.status}
                        queryMs={session.erp_query_ms}
                    />
                </div>
            </div>

            <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete session?</DialogTitle>
                        <DialogDescription>
                            Deletes this session and pulled rows.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={processing}
                            onClick={() =>
                                deleteSession(destroy.url(session.id), {
                                    onSuccess: () => setConfirmOpen(false),
                                })
                            }
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

TransactionReconciliationShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Transaction reconciliation', href: index.url() },
        { title: 'Session details', href: show.url(0) },
    ],
};
