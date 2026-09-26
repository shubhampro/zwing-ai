import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/SfMbrReportController';
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
import { useCan } from '@/hooks/use-can';
import { formatDateOnly, formatDateTime } from '@/lib/datetime';
import { dashboard } from '@/routes';
import { create, details, index, show, summary } from '@/routes/sf-mbr';

type ReportStatus = 'pending' | 'generating' | 'ready' | 'failed';

type ReportRow = {
    id: number;
    title: string;
    starts_on: string | null;
    ends_on: string | null;
    status: ReportStatus;
    applications: string[];
    all_applications: boolean;
    created_by: string | null;
    created_at: string | null;
};

function applicationLabel(row: ReportRow): string {
    if (row.all_applications) {
        return 'All applications';
    }

    return row.applications.join(', ') || '—';
}

export default function SfMbrIndex({ reports }: { reports: ReportRow[] }) {
    const can = useCan();
    const canManage = can('sf-mbr.manage');
    const canDelete = can('sf-mbr.delete');
    const [pending, setPending] = useState<ReportRow | null>(null);
    const { delete: deleteReport, processing } = useForm();

    function confirmDelete() {
        if (pending === null) {
            return;
        }

        deleteReport(destroy.url(pending.id), {
            onSuccess: () => setPending(null),
        });
    }

    return (
        <>
            <Head title="MBR Report" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="MBR Report"
                        description="Saved reviews by application and IST date range."
                        className="mb-0"
                    />
                    {canManage && (
                        <Button size="sm" asChild>
                            <Link href={create.url()}>
                                <Plus className="size-4" />
                                New report
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full min-w-[40rem] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Title</th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Applications
                                </th>
                                <th className="px-3 py-2 font-medium">From</th>
                                <th className="px-3 py-2 font-medium">To</th>
                                <th className="px-3 py-2 font-medium">
                                    Created
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {reports.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No reports yet. Create one to start a
                                        monthly review.
                                    </td>
                                </tr>
                            ) : (
                                reports.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                    >
                                        <td className="px-3 py-2 font-medium">
                                            <Link
                                                href={show.url(row.id)}
                                                className="text-primary underline-offset-4 hover:underline"
                                            >
                                                {row.title}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-2">
                                            <Badge
                                                variant={
                                                    row.status === 'failed'
                                                        ? 'destructive'
                                                        : row.status ===
                                                            'ready'
                                                          ? 'default'
                                                          : 'secondary'
                                                }
                                            >
                                                {row.status === 'generating'
                                                    ? 'Preparing'
                                                    : row.status}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2">
                                            {applicationLabel(row)}
                                        </td>
                                        <td className="px-3 py-2 tabular-nums">
                                            {formatDateOnly(row.starts_on)}
                                        </td>
                                        <td className="px-3 py-2 tabular-nums">
                                            {formatDateOnly(row.ends_on)}
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {row.created_by
                                                ? `${row.created_by} · `
                                                : ''}
                                            {formatDateTime(row.created_at)}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    asChild
                                                >
                                                    <Link
                                                        href={summary.url(
                                                            row.id,
                                                        )}
                                                    >
                                                        Summary
                                                    </Link>
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    asChild
                                                >
                                                    <Link
                                                        href={details.url(
                                                            row.id,
                                                        )}
                                                    >
                                                        Details
                                                    </Link>
                                                </Button>
                                                {canDelete && (
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() =>
                                                            setPending(row)
                                                        }
                                                        aria-label={`Delete ${row.title}`}
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog
                open={pending !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPending(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete MBR report?</DialogTitle>
                        <DialogDescription>
                            This will permanently delete{' '}
                            <span className="font-medium text-foreground">
                                "{pending?.title}"
                            </span>{' '}
                            and its segregated account rows. This action cannot
                            be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setPending(null)}
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

SfMbrIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'MBR Report', href: index() },
    ],
};
