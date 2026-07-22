import { Head, router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { resolveExternalQueryResponse, xsrfToken } from '@/lib/external-query';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index, refresh } from '@/routes/server-health';

type ConnectionResult = {
    key: string;
    status: 'ok' | 'warn' | 'critical' | 'down';
    latency_ms: number | null;
    meta: {
        version?: string | null;
        threads_running?: number | null;
        threads_connected?: number | null;
        max_used_connections?: number | null;
        questions?: number | null;
    };
    error: string | null;
};

type Snapshot = {
    overall_status: string;
    ran_at: string;
    results: ConnectionResult[];
    cached: boolean;
    locked: boolean;
};

type HistoryRow = {
    id: number;
    ran_at: string | null;
    overall_status: string;
    results: ConnectionResult[];
};

const statusBadgeClass: Record<string, string> = {
    ok: 'bg-emerald-500/15 text-emerald-800 dark:text-emerald-200',
    warn: 'bg-amber-500/15 text-amber-900 dark:text-amber-200',
    critical: 'bg-orange-500/15 text-orange-900 dark:text-orange-200',
    down: 'bg-destructive/15 text-destructive',
};

function StatusBadge({ status }: { status: string }) {
    return (
        <Badge
            variant="secondary"
            className={cn('capitalize', statusBadgeClass[status] ?? '')}
        >
            {status}
        </Badge>
    );
}

function formatWhen(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    try {
        return new Date(iso).toLocaleString();
    } catch {
        return iso;
    }
}

export default function ServerHealthIndex({
    snapshot,
    history,
    cache_ttl_seconds,
    cache_fresh,
    locked,
    can_refresh,
}: {
    snapshot: Snapshot | null;
    history: HistoryRow[];
    cache_ttl_seconds: number;
    cache_fresh: boolean;
    locked: boolean;
    can_refresh: boolean;
}) {
    const [refreshing, setRefreshing] = useState(false);
    const refreshDisabled = !can_refresh || locked || cache_fresh || refreshing;

    async function runRefresh() {
        setRefreshing(true);

        try {
            const response = await fetch(refresh.url(), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (response.status === 409) {
                const json = (await response.json().catch(() => ({}))) as {
                    message?: string;
                };
                toast.error(json.message ?? 'Health check unavailable.');
                return;
            }

            if (!response.ok && response.status !== 202) {
                throw new Error(`Health check failed (${response.status})`);
            }

            const settled = await resolveExternalQueryResponse(response);
            const overall =
                typeof settled.result?.overall_status === 'string'
                    ? settled.result.overall_status
                    : 'unknown';
            toast.success(`DB health check completed: ${overall}`);
            router.reload({ preserveScroll: true });
        } catch (error) {
            toast.error(
                error instanceof Error ? error.message : 'Health check failed.',
            );
        } finally {
            setRefreshing(false);
        }
    }

    return (
        <>
            <Head title="DB Health" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        className="mb-0"
                        title="DB Health"
                        description="Lightweight named-connection checks. Cached to avoid query spikes."
                    />
                    <Button
                        size="sm"
                        className="sm:shrink-0"
                        disabled={refreshDisabled}
                        onClick={runRefresh}
                    >
                        <RefreshCw
                            className={cn(
                                'size-4',
                                refreshing && 'animate-spin',
                            )}
                        />
                        {refreshing ? 'Checking…' : 'Refresh'}
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                    <span>
                        Overall:{' '}
                        {snapshot ? (
                            <StatusBadge status={snapshot.overall_status} />
                        ) : (
                            <span className="text-foreground">
                                No checks yet
                            </span>
                        )}
                    </span>
                    <span>
                        Last checked:{' '}
                        <span className="text-foreground">
                            {formatWhen(snapshot?.ran_at)}
                        </span>
                    </span>
                    <span>
                        Cache TTL: {cache_ttl_seconds}s
                        {cache_fresh ? ' (fresh)' : ''}
                        {locked ? ' · locked' : ''}
                    </span>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[720px] text-left text-sm">
                        <thead className="border-b bg-muted/40 text-xs tracking-wide text-muted-foreground uppercase">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Connection
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Latency
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Threads running
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Threads connected
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Version
                                </th>
                                <th className="px-3 py-2 font-medium">Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            {snapshot?.results?.length ? (
                                snapshot.results.map((row) => (
                                    <tr
                                        key={row.key}
                                        className="border-b last:border-0"
                                    >
                                        <td className="px-3 py-2 font-medium">
                                            {row.key}
                                        </td>
                                        <td className="px-3 py-2">
                                            <StatusBadge status={row.status} />
                                        </td>
                                        <td className="px-3 py-2 tabular-nums">
                                            {row.latency_ms !== null
                                                ? `${row.latency_ms} ms`
                                                : '—'}
                                        </td>
                                        <td className="px-3 py-2 tabular-nums">
                                            {row.meta.threads_running ?? '—'}
                                        </td>
                                        <td className="px-3 py-2 tabular-nums">
                                            {row.meta.threads_connected ?? '—'}
                                        </td>
                                        <td className="px-3 py-2">
                                            {row.meta.version ?? '—'}
                                        </td>
                                        <td className="max-w-xs truncate px-3 py-2 text-destructive">
                                            {row.error ?? '—'}
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No snapshot yet. Admins can run Refresh
                                        to check named DB connections.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {history.length > 0 && (
                    <div className="space-y-2">
                        <h2 className="text-sm font-medium">Recent history</h2>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b bg-muted/40 text-xs tracking-wide text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            When
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Overall
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Targets
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {history.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="px-3 py-2">
                                                {formatWhen(row.ran_at)}
                                            </td>
                                            <td className="px-3 py-2">
                                                <StatusBadge
                                                    status={row.overall_status}
                                                />
                                            </td>
                                            <td className="px-3 py-2 text-muted-foreground">
                                                {row.results
                                                    .map(
                                                        (r) =>
                                                            `${r.key}:${r.status}`,
                                                    )
                                                    .join(' · ')}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

ServerHealthIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'DB Health', href: index() },
    ],
};
