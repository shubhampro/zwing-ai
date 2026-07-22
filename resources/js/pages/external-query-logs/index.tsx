import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateTime } from '@/lib/datetime';
import { dashboard } from '@/routes';
import { index as externalQueryLogsIndex } from '@/routes/external-query-logs';
import { show as stockReconShow } from '@/routes/stock-transaction-reconciliation';

type JobType =
    | 'pull_stock'
    | 'sync_row'
    | 'log_details'
    | 'list_zwing_vendors'
    | 'attach_zwing_vendor'
    | 'update_from_zwing_vendor'
    | 'test_org_db_connection'
    | 'list_txn_checker_databases'
    | 'run_txn_checker'
    | 'server_health_check';
type Status = 'pending' | 'processing' | 'completed' | 'failed';

type LogRow = {
    id: number;
    job_type: JobType;
    status: Status;
    context: Record<string, unknown> | null;
    zwing_query_ms: number | null;
    erp_query_ms: number | null;
    failure_reason: string | null;
    started_at: string | null;
    finished_at: string | null;
    created_at: string | null;
    user: { id: number; name: string; email: string } | null;
    session: { id: number; name: string } | null;
};

type Pagination = {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
};

type Props = {
    logs: LogRow[];
    pagination: Pagination;
    filters: {
        job_type: string;
        status: string;
        session_id: number | null;
    };
    jobTypeOptions: JobType[];
    statusOptions: Status[];
};

const ANY = '__any__';

const statusVariant: Record<
    Status,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'secondary',
    processing: 'outline',
    completed: 'default',
    failed: 'destructive',
};

const jobTypeLabel: Record<JobType, string> = {
    pull_stock: 'Pull stock',
    sync_row: 'Sync row',
    log_details: 'Log details',
    list_zwing_vendors: 'List Zwing vendors',
    attach_zwing_vendor: 'Attach Zwing vendor',
    update_from_zwing_vendor: 'Update from Zwing vendor',
    test_org_db_connection: 'Test org DB connection',
    list_txn_checker_databases: 'List txn checker DBs',
    run_txn_checker: 'Run txn checker',
    server_health_check: 'Server health check',
};

function formatMs(ms: number | null): string {
    if (ms === null) {
        return '—';
    }

    if (ms < 1000) {
        return `${ms} ms`;
    }

    return `${(ms / 1000).toFixed(ms < 10000 ? 1 : 0)} s`;
}

function contextSummary(context: Record<string, unknown> | null): string {
    if (!context) {
        return '—';
    }

    const parts: string[] = [];

    if (typeof context.icode === 'string' && context.icode !== '') {
        parts.push(context.icode);
    }

    if (typeof context.site_code === 'string' && context.site_code !== '') {
        parts.push(`site ${context.site_code}`);
    }

    if (typeof context.sprefcode === 'string' && context.sprefcode !== '') {
        parts.push(`spref ${context.sprefcode}`);
    }

    if (context.include_zwing === true) {
        parts.push('zwing');
    }

    if (context.include_erp === true) {
        parts.push('erp');
    }

    return parts.length > 0 ? parts.join(' · ') : '—';
}

export default function ExternalQueryLogsIndex({
    logs,
    pagination,
    filters,
    jobTypeOptions,
    statusOptions,
}: Props) {
    const [jobType, setJobType] = useState(filters.job_type || ANY);
    const [status, setStatus] = useState(filters.status || ANY);
    const [sessionId, setSessionId] = useState(
        filters.session_id ? String(filters.session_id) : '',
    );

    function applyFilters(page = 1) {
        router.get(
            externalQueryLogsIndex.url(),
            {
                job_type: jobType === ANY ? undefined : jobType,
                status: status === ANY ? undefined : status,
                session_id: sessionId.trim() === '' ? undefined : sessionId,
                page,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="External query logs" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="External query logs"
                    description="Admin-only audit of serial remote DB jobs on the external-query queue."
                />

                <div className="grid gap-3 rounded-lg border p-4 md:grid-cols-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="job_type">Job type</Label>
                        <Select value={jobType} onValueChange={setJobType}>
                            <SelectTrigger id="job_type">
                                <SelectValue placeholder="All types" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ANY}>All types</SelectItem>
                                {jobTypeOptions.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {jobTypeLabel[option]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="status">Status</Label>
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger id="status">
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ANY}>
                                    All statuses
                                </SelectItem>
                                {statusOptions.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="session_id">Session ID</Label>
                        <Input
                            id="session_id"
                            value={sessionId}
                            onChange={(event) =>
                                setSessionId(event.target.value)
                            }
                            placeholder="Optional"
                            inputMode="numeric"
                        />
                    </div>
                    <div className="flex items-end gap-2">
                        <Button type="button" onClick={() => applyFilters(1)}>
                            Apply
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setJobType(ANY);
                                setStatus(ANY);
                                setSessionId('');
                                router.get(externalQueryLogsIndex.url());
                            }}
                        >
                            Clear
                        </Button>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[960px] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">ID</th>
                                <th className="px-3 py-2 font-medium">Type</th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">User</th>
                                <th className="px-3 py-2 font-medium">
                                    Session
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Context
                                </th>
                                <th className="px-3 py-2 font-medium">Zwing</th>
                                <th className="px-3 py-2 font-medium">ERP</th>
                                <th className="px-3 py-2 font-medium">
                                    Created
                                </th>
                                <th className="px-3 py-2 font-medium">Error</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {logs.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={10}
                                        className="px-3 py-10 text-center text-muted-foreground"
                                    >
                                        No external query logs yet.
                                    </td>
                                </tr>
                            )}
                            {logs.map((log) => (
                                <tr key={log.id} className="align-top">
                                    <td className="px-3 py-2.5 tabular-nums">
                                        {log.id}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        {jobTypeLabel[log.job_type]}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <Badge
                                            variant={statusVariant[log.status]}
                                            className="text-xs"
                                        >
                                            {log.status}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <div className="font-medium">
                                            {log.user?.name ?? '—'}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {log.user?.email ?? ''}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2.5">
                                        {log.session ? (
                                            <Link
                                                href={stockReconShow.url(
                                                    log.session.id,
                                                )}
                                                className="text-primary underline-offset-4 hover:underline"
                                            >
                                                #{log.session.id}{' '}
                                                {log.session.name}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="max-w-[220px] px-3 py-2.5 text-xs text-muted-foreground">
                                        {contextSummary(log.context)}
                                    </td>
                                    <td className="px-3 py-2.5 tabular-nums">
                                        {formatMs(log.zwing_query_ms)}
                                    </td>
                                    <td className="px-3 py-2.5 tabular-nums">
                                        {formatMs(log.erp_query_ms)}
                                    </td>
                                    <td className="px-3 py-2.5 text-muted-foreground">
                                        {formatDateTime(log.created_at)}
                                    </td>
                                    <td className="max-w-[240px] px-3 py-2.5 text-xs text-destructive">
                                        {log.failure_reason ?? '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {pagination.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Page {pagination.current_page} of{' '}
                            {pagination.last_page} ·{' '}
                            {pagination.total.toLocaleString()} logs
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={pagination.current_page <= 1}
                                onClick={() =>
                                    applyFilters(pagination.current_page - 1)
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={
                                    pagination.current_page >=
                                    pagination.last_page
                                }
                                onClick={() =>
                                    applyFilters(pagination.current_page + 1)
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

ExternalQueryLogsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'External query logs',
            href: externalQueryLogsIndex.url(),
        },
    ],
};
