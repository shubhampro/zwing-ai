import { Head, Link, useForm, usePoll } from '@inertiajs/react';
import { ArrowLeft, CheckCircle, Trash2, XCircle } from 'lucide-react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/StockTransactionReconciliationController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { BarChart2 } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { dashboard } from '@/routes';
import { index, report, show } from '@/routes/stock-transaction-reconciliation';

type SessionStatus = 'pending' | 'processing' | 'completed' | 'failed';

type SessionData = {
    id: number;
    name: string;
    v_id: number;
    zwing_file_name: string | null;
    erp_file_name: string | null;
    zwing_row_count: number | null;
    erp_row_count: number | null;
    zwing_processed_rows: number;
    erp_processed_rows: number;
    zwing_skipped_rows: number;
    erp_skipped_rows: number;
    status: SessionStatus;
    reconciled_at: string | null;
    created_at: string;
};

const statusVariant: Record<SessionStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    pending: 'secondary',
    processing: 'outline',
    completed: 'default',
    failed: 'destructive',
};

function ProgressBar({
    label,
    fileName,
    processed,
    total,
    skipped,
    status,
}: {
    label: string;
    fileName: string | null;
    processed: number;
    total: number | null;
    skipped: number;
    status: SessionStatus;
}) {
    const isActive = fileName !== null;
    const percentage = total && total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
    const isDone = status === 'completed' || (isActive && processed >= (total ?? 0) && total !== null);
    const isFailed = status === 'failed';

    return (
        <div className="flex flex-col gap-2 rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
            <div className="flex items-center justify-between gap-2">
                <div>
                    <p className="font-medium">{label}</p>
                    {fileName ? (
                        <p className="text-muted-foreground mt-0.5 font-mono text-xs">{fileName}</p>
                    ) : (
                        <p className="text-muted-foreground mt-0.5 text-xs">No file uploaded</p>
                    )}
                </div>
                {isActive && (
                    <div className="shrink-0">
                        {isDone && !isFailed && <CheckCircle className="size-5 text-green-500" />}
                        {isFailed && <XCircle className="size-5 text-destructive" />}
                    </div>
                )}
            </div>

            {isActive && (
                <>
                    <div className="bg-muted h-2.5 w-full overflow-hidden rounded-full">
                        <div
                            className="h-full rounded-full bg-primary transition-all duration-500"
                            style={{ width: `${percentage}%` }}
                        />
                    </div>
                    <div className="text-muted-foreground flex items-center justify-between text-xs">
                        <span>
                            {processed.toLocaleString()} / {total !== null ? total.toLocaleString() : '—'} rows
                        </span>
                        <span>{percentage}%</span>
                    </div>
                    {skipped > 0 && (
                        <p className="text-xs text-amber-600 dark:text-amber-400">
                            {skipped.toLocaleString()} {skipped === 1 ? 'row' : 'rows'} skipped — missing or invalid data
                        </p>
                    )}
                </>
            )}

            {!isActive && (
                <div className="bg-muted h-2.5 w-full overflow-hidden rounded-full">
                    <div className="h-full w-0 rounded-full bg-primary" />
                </div>
            )}
        </div>
    );
}

export default function StockTransactionReconciliationShow({ session }: { session: SessionData }) {
    const isFinished = session.status === 'completed' || session.status === 'failed';
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
            <Head title={`Reconciliation #${session.id}`} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <Link href={index.url()}>
                            <Button variant="outline" size="icon" className="mt-0.5 shrink-0">
                                <ArrowLeft className="size-4" />
                            </Button>
                        </Link>
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {session.name}
                            <span className="text-muted-foreground ml-2 font-mono text-base">#{session.id}</span>
                        </h1>
                        <p className="text-muted-foreground mt-0.5 text-xs">Vendor ID: {session.v_id}</p>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {session.status === 'pending' && 'Queued — waiting for the worker to start…'}
                            {session.status === 'processing' && 'Inserting rows — this page refreshes automatically.'}
                            {session.status === 'completed' && `Completed at ${new Date(session.reconciled_at!).toLocaleString()}`}
                            {session.status === 'failed' && 'Processing failed. Please try uploading again.'}
                        </p>
                    </div>
                    </div>

                    <div className="flex shrink-0 items-center gap-2">
                        <Badge variant={statusVariant[session.status]} className="capitalize">
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
                        label="Zwing (POS)"
                        fileName={session.zwing_file_name}
                        processed={session.zwing_processed_rows}
                        total={session.zwing_row_count}
                        skipped={session.zwing_skipped_rows}
                        status={session.status}
                    />
                    <ProgressBar
                        label="ERP"
                        fileName={session.erp_file_name}
                        processed={session.erp_processed_rows}
                        total={session.erp_row_count}
                        skipped={session.erp_skipped_rows}
                        status={session.status}
                    />
                </div>
            </div>

            <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete reconciliation session?</DialogTitle>
                        <DialogDescription>
                            This will permanently delete <span className="text-foreground font-medium">"{session.name}"</span> and all
                            associated Zwing and ERP detail rows. This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmOpen(false)} disabled={processing}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete} disabled={processing}>
                            {processing ? 'Deleting…' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

StockTransactionReconciliationShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Stock reconciliation', href: index.url() },
        { title: 'Session details', href: show.url(0) },
    ],
};
