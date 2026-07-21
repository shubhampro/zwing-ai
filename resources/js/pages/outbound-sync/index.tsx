import { Head } from '@inertiajs/react';
import { ChevronDown, Copy, Loader2, RefreshCw, Search } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { fetch as fetchAction } from '@/actions/App/Http/Controllers/OutboundSyncController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { copyToClipboard } from '@/lib/copy-to-clipboard';
import { dashboard } from '@/routes';
import { index } from '@/routes/outbound-sync';

type DefaultFilters = {
    v_id: number;
    partner_code: string;
    start_date: string;
    end_date: string;
};

type SyncStats = {
    totalSync: number;
    total_sync: number;
    remain_sync: number;
    sucessSync: number;
    pending: number;
};

type SyncResultRow = {
    name: string;
    trans: number;
    val: number;
    failCnt: number;
    needToSync: string[];
    eventMiss: string[];
    pending: number;
    successSync: number | number[];
};

type FetchResponse = {
    success: boolean;
    result: SyncResultRow[];
    stats: SyncStats | null;
    message?: string;
};

const SYNC_HEAD =
    'border-b bg-amber-500/10 px-3 py-2 text-center text-xs font-semibold tracking-wide text-amber-800 uppercase dark:text-amber-300';
const ISSUE_HEAD =
    'border-b bg-blue-500/10 px-3 py-2 text-center text-xs font-semibold tracking-wide text-blue-800 uppercase dark:text-blue-300';
const SYNC_CELL = 'bg-amber-500/[0.04]';
const ISSUE_CELL = 'bg-blue-500/[0.04]';
const TABLE_COLUMN_COUNT = 8;

function formatEntityName(name: string): string {
    return name.replace(/[_-]/g, ' ');
}

function toneStyles(tone: 'amber' | 'blue') {
    return tone === 'amber'
        ? {
              badge: 'bg-amber-500/15 text-amber-900 hover:bg-amber-500/15 dark:text-amber-200',
              chip: 'bg-amber-500/10 text-amber-900 ring-amber-500/30 dark:text-amber-200',
              row: 'hover:bg-amber-500/10',
          }
        : {
              badge: 'bg-blue-500/15 text-blue-900 hover:bg-blue-500/15 dark:text-blue-200',
              chip: 'bg-blue-500/10 text-blue-900 ring-blue-500/30 dark:text-blue-200',
              row: 'hover:bg-blue-500/10',
          };
}

async function copyId(id: string): Promise<void> {
    const copied = await copyToClipboard(id);

    if (copied) {
        toast.success(`Copied ${id}`);
    }
}

function formatIdsForSqlInClause(ids: string[]): string {
    return `(${ids.map((id) => `'${id}'`).join(',')})`;
}

async function copyIdsAsSqlInClause(
    ids: string[],
    label: string,
): Promise<void> {
    const copied = await copyToClipboard(formatIdsForSqlInClause(ids));

    if (copied) {
        toast.success(`Copied ${ids.length} ${label} IDs`);
    }
}

function IdChip({ id, tone }: { id: string; tone: 'amber' | 'blue' }) {
    const styles = toneStyles(tone);

    return (
        <button
            type="button"
            onClick={() => {
                void copyId(id);
            }}
            className={cn(
                'inline-flex items-center gap-1 rounded-md px-2 py-0.5 font-mono text-xs ring-1 transition-colors hover:opacity-80',
                styles.chip,
            )}
            title={`${id} — click to copy`}
        >
            {id}
            <Copy className="size-3 shrink-0 opacity-60" />
        </button>
    );
}

function IdCountBadge({
    count,
    tone,
}: {
    count: number;
    tone: 'amber' | 'blue';
}) {
    if (count === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    const styles = toneStyles(tone);

    return (
        <Badge className={cn('tabular-nums', styles.badge)}>
            {count} {count === 1 ? 'ID' : 'IDs'}
        </Badge>
    );
}

function ExpandedIdPanel({
    needToSync,
    eventMiss,
}: {
    needToSync: string[];
    eventMiss: string[];
}) {
    return (
        <div className="grid gap-4 md:grid-cols-2">
            <div className="rounded-md border border-amber-500/20 bg-amber-500/[0.04] p-3">
                <div className="mb-2 flex items-center justify-between gap-2">
                    <p className="text-xs font-semibold tracking-wide text-amber-800 uppercase dark:text-amber-300">
                        Need to sync
                    </p>
                    {needToSync.length > 0 && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-7 text-xs"
                            onClick={() => {
                                void copyIdsAsSqlInClause(
                                    needToSync,
                                    'need to sync',
                                );
                            }}
                        >
                            <Copy className="size-3" />
                            Copy all
                        </Button>
                    )}
                </div>
                {needToSync.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No IDs</p>
                ) : (
                    <div className="flex flex-wrap gap-1.5">
                        {needToSync.map((id) => (
                            <IdChip key={id} id={id} tone="amber" />
                        ))}
                    </div>
                )}
            </div>

            <div className="rounded-md border border-blue-500/20 bg-blue-500/[0.04] p-3">
                <div className="mb-2 flex items-center justify-between gap-2">
                    <p className="text-xs font-semibold tracking-wide text-blue-800 uppercase dark:text-blue-300">
                        Event miss
                    </p>
                    {eventMiss.length > 0 && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-7 text-xs"
                            onClick={() => {
                                void copyIdsAsSqlInClause(
                                    eventMiss,
                                    'event miss',
                                );
                            }}
                        >
                            <Copy className="size-3" />
                            Copy all
                        </Button>
                    )}
                </div>
                {eventMiss.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No IDs</p>
                ) : (
                    <div className="flex flex-wrap gap-1.5">
                        {eventMiss.map((id) => (
                            <IdChip key={id} id={id} tone="blue" />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

function EntityTableRow({
    row,
    expanded,
    onToggle,
}: {
    row: SyncResultRow;
    expanded: boolean;
    onToggle: () => void;
}) {
    const successCount = normalizeCount(row.successSync);
    const failedPending = row.failCnt + row.pending;
    const missingIdCount = row.needToSync.length + row.eventMiss.length;
    const canExpand = missingIdCount > 0;

    return (
        <>
            <tr
                className={cn(
                    row.failCnt > 0 || row.needToSync.length > 0
                        ? 'bg-destructive/[0.03]'
                        : row.pending > 0 || row.eventMiss.length > 0
                          ? 'bg-amber-500/[0.03]'
                          : undefined,
                    canExpand && 'cursor-pointer hover:bg-muted/40',
                )}
                onClick={canExpand ? onToggle : undefined}
            >
                <td className="px-3 py-3 font-medium capitalize">
                    <div className="flex items-center gap-2">
                        {canExpand ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7 shrink-0"
                                onClick={(event) => {
                                    event.stopPropagation();
                                    onToggle();
                                }}
                                aria-expanded={expanded}
                                aria-label={
                                    expanded
                                        ? `Collapse ${formatEntityName(row.name)} IDs`
                                        : `Expand ${formatEntityName(row.name)} IDs`
                                }
                            >
                                <ChevronDown
                                    className={cn(
                                        'size-4 transition-transform',
                                        expanded && 'rotate-180',
                                    )}
                                />
                            </Button>
                        ) : (
                            <span className="inline-block size-7 shrink-0" />
                        )}
                        <span>{formatEntityName(row.name)}</span>
                    </div>
                </td>
                <td className="px-3 py-3">
                    <StatusBadge
                        failCnt={row.failCnt}
                        pending={row.pending}
                        needToSync={row.needToSync}
                        eventMiss={row.eventMiss}
                    />
                </td>
                <td
                    className={cn(
                        'px-3 py-3 text-right tabular-nums',
                        SYNC_CELL,
                    )}
                >
                    {row.trans.toLocaleString()}
                </td>
                <td
                    className={cn(
                        'px-3 py-3 text-right tabular-nums',
                        SYNC_CELL,
                    )}
                >
                    {row.val.toLocaleString()}
                </td>
                <td
                    className={cn(
                        'px-3 py-3 text-right text-green-700 tabular-nums dark:text-green-400',
                        SYNC_CELL,
                    )}
                >
                    {successCount.toLocaleString()}
                </td>
                <td
                    className={cn(
                        'px-3 py-3 text-right tabular-nums',
                        SYNC_CELL,
                        failedPending > 0 && 'font-semibold text-destructive',
                    )}
                >
                    {failedPending.toLocaleString()}
                    {row.failCnt > 0 && row.pending > 0 && (
                        <span className="ml-1 text-xs font-normal text-muted-foreground">
                            ({row.failCnt} fail, {row.pending} pending)
                        </span>
                    )}
                </td>
                <td className={cn('px-3 py-3 align-top', ISSUE_CELL)}>
                    <IdCountBadge count={row.needToSync.length} tone="amber" />
                </td>
                <td className={cn('px-3 py-3 align-top', ISSUE_CELL)}>
                    <IdCountBadge count={row.eventMiss.length} tone="blue" />
                </td>
            </tr>

            {expanded && canExpand && (
                <tr className="bg-muted/20">
                    <td
                        colSpan={TABLE_COLUMN_COUNT}
                        className="border-t border-sidebar-border/70 px-4 py-4 dark:border-sidebar-border"
                    >
                        <ExpandedIdPanel
                            needToSync={row.needToSync}
                            eventMiss={row.eventMiss}
                        />
                    </td>
                </tr>
            )}
        </>
    );
}

function SummaryCard({
    label,
    value,
    color,
    suffix,
}: {
    label: string;
    value: number | string;
    color: string;
    suffix?: string;
}) {
    return (
        <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className={cn('mt-1 text-2xl font-bold tabular-nums', color)}>
                {typeof value === 'number' ? value.toLocaleString() : value}
                {suffix}
            </p>
        </div>
    );
}

function normalizeCount(value: number | number[]): number {
    return Array.isArray(value) ? value.length : value;
}

function StatusBadge({
    failCnt,
    pending,
    needToSync,
    eventMiss,
}: {
    failCnt: number;
    pending: number;
    needToSync: string[];
    eventMiss: string[];
}) {
    const issues = failCnt + pending + needToSync.length + eventMiss.length;

    if (issues === 0) {
        return (
            <Badge className="bg-green-600 hover:bg-green-600">Healthy</Badge>
        );
    }

    if (failCnt > 0 || needToSync.length > 0) {
        return <Badge variant="destructive">Needs attention</Badge>;
    }

    return (
        <Badge className="bg-amber-600 hover:bg-amber-600">
            Pending / miss
        </Badge>
    );
}

export default function OutboundSyncIndex({
    defaultFilters,
}: {
    defaultFilters: DefaultFilters;
}) {
    const [vId, setVId] = useState(String(defaultFilters.v_id));
    const [partnerCode, setPartnerCode] = useState(defaultFilters.partner_code);
    const [startDate, setStartDate] = useState(defaultFilters.start_date);
    const [endDate, setEndDate] = useState(defaultFilters.end_date);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [data, setData] = useState<FetchResponse | null>(null);
    const [expandedRows, setExpandedRows] = useState<Set<string>>(new Set());

    function toggleRow(name: string): void {
        setExpandedRows((current) => {
            const next = new Set(current);

            if (next.has(name)) {
                next.delete(name);
            } else {
                next.add(name);
            }

            return next;
        });
    }

    async function handleFetch(e: React.FormEvent) {
        e.preventDefault();
        setError(null);
        setLoading(true);
        setExpandedRows(new Set());

        try {
            const xsrfToken = decodeURIComponent(
                document.cookie
                    .split('; ')
                    .find((row) => row.startsWith('XSRF-TOKEN='))
                    ?.split('=')[1] ?? '',
            );

            const res = await fetch(fetchAction.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    v_id: Number(vId),
                    partner_code: partnerCode,
                    start_date: startDate,
                    end_date: endDate,
                }),
            });

            const json: FetchResponse & { message?: string } = await res
                .json()
                .catch(() => ({ success: false, result: [], stats: null }));

            if (!res.ok || !json.success) {
                setData(null);
                setError(json.message ?? `Request failed (${res.status})`);
                return;
            }

            setData(json);
        } catch (err) {
            setData(null);
            setError(err instanceof Error ? err.message : 'Unknown error');
        } finally {
            setLoading(false);
        }
    }

    const stats = data?.stats;
    const rows = data?.result ?? [];
    const syncPercent = stats?.total_sync ?? 0;
    const issueRows = rows.filter(
        (row) =>
            row.failCnt > 0 ||
            row.pending > 0 ||
            row.needToSync.length > 0 ||
            row.eventMiss.length > 0,
    );

    return (
        <>
            <Head title="Outbound Sync" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Outbound Sync"
                    description="Check unsynced outbound transactions from Connect by vendor, partner, and date range."
                />

                <form
                    onSubmit={handleFetch}
                    className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-5">
                        <div className="space-y-1.5">
                            <Label htmlFor="v-id">Vendor ID</Label>
                            <Input
                                id="v-id"
                                type="number"
                                min={1}
                                value={vId}
                                onChange={(e) => setVId(e.target.value)}
                                required
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="partner-code">Partner code</Label>
                            <Input
                                id="partner-code"
                                value={partnerCode}
                                onChange={(e) => setPartnerCode(e.target.value)}
                                required
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="start-date">Start date</Label>
                            <Input
                                id="start-date"
                                type="date"
                                value={startDate}
                                onChange={(e) => setStartDate(e.target.value)}
                                required
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="end-date">End date</Label>
                            <Input
                                id="end-date"
                                type="date"
                                value={endDate}
                                onChange={(e) => setEndDate(e.target.value)}
                                required
                            />
                        </div>
                        <div className="flex items-end">
                            <Button
                                type="submit"
                                disabled={loading}
                                className="w-full"
                            >
                                {loading ? (
                                    <>
                                        <Loader2 className="size-4 animate-spin" />
                                        Fetching…
                                    </>
                                ) : (
                                    <>
                                        <Search className="size-4" />
                                        Fetch data
                                    </>
                                )}
                            </Button>
                        </div>
                    </div>
                </form>

                {error && (
                    <div className="rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                        {error}
                    </div>
                )}

                {stats && (
                    <>
                        <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
                            <SummaryCard
                                label="Total sync"
                                value={stats.totalSync}
                                color="text-foreground"
                            />
                            <SummaryCard
                                label="Sync rate"
                                value={syncPercent.toFixed(2)}
                                suffix="%"
                                color="text-green-600 dark:text-green-400"
                            />
                            <SummaryCard
                                label="Success sync"
                                value={stats.sucessSync}
                                color="text-green-600 dark:text-green-400"
                            />
                            <SummaryCard
                                label="Remaining"
                                value={stats.remain_sync}
                                color="text-orange-600 dark:text-orange-400"
                            />
                            <SummaryCard
                                label="Pending"
                                value={stats.pending}
                                color="text-amber-600 dark:text-amber-400"
                            />
                        </div>

                        <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <div className="mb-2 flex items-center justify-between gap-3">
                                <p className="text-sm font-medium">
                                    Overall sync progress
                                </p>
                                <span className="text-sm text-muted-foreground tabular-nums">
                                    {syncPercent.toFixed(2)}%
                                </span>
                            </div>
                            <div className="h-2 overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full rounded-full bg-green-600 transition-all dark:bg-green-500"
                                    style={{
                                        width: `${Math.min(Math.max(syncPercent, 0), 100)}%`,
                                    }}
                                />
                            </div>
                        </div>
                    </>
                )}

                {data && (
                    <>
                        <div className="flex flex-wrap items-center gap-4 rounded-md border border-dashed px-4 py-2 text-xs text-muted-foreground">
                            <span className="flex items-center gap-2">
                                <span className="size-3 rounded-sm bg-amber-500/20 ring-1 ring-amber-500/40" />
                                Sync counts
                            </span>
                            <span className="flex items-center gap-2">
                                <span className="size-3 rounded-sm bg-blue-500/20 ring-1 ring-blue-500/40" />
                                Missing / pending IDs
                            </span>
                            <span className="flex items-center gap-2">
                                <span className="size-3 rounded-sm bg-green-500/20 ring-1 ring-green-500/40" />
                                Healthy entity
                            </span>
                            <span className="flex items-center gap-2">
                                <span className="size-3 rounded-sm bg-destructive/20 ring-1 ring-destructive/30" />
                                Failed / need sync
                            </span>
                            <span className="text-muted-foreground">
                                Rows with missing IDs can be expanded
                            </span>
                        </div>

                        <div className="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[1100px] border-collapse text-sm">
                                    <thead>
                                        <tr>
                                            <th
                                                rowSpan={2}
                                                className="border-r border-b bg-muted/50 px-3 py-3 text-left align-bottom font-medium"
                                            >
                                                Entity
                                            </th>
                                            <th
                                                rowSpan={2}
                                                className="border-r border-b bg-muted/50 px-3 py-3 text-left align-bottom font-medium"
                                            >
                                                Status
                                            </th>
                                            <th
                                                colSpan={4}
                                                className={SYNC_HEAD}
                                            >
                                                Sync counts
                                            </th>
                                            <th
                                                colSpan={2}
                                                className={ISSUE_HEAD}
                                            >
                                                Missing IDs
                                            </th>
                                        </tr>
                                        <tr className="border-b bg-muted/30 text-left text-xs text-muted-foreground">
                                            <th
                                                className={cn(
                                                    'px-3 py-2 text-right font-medium',
                                                    SYNC_CELL,
                                                )}
                                            >
                                                Transactions
                                            </th>
                                            <th
                                                className={cn(
                                                    'px-3 py-2 text-right font-medium',
                                                    SYNC_CELL,
                                                )}
                                            >
                                                Validated
                                            </th>
                                            <th
                                                className={cn(
                                                    'px-3 py-2 text-right font-medium',
                                                    SYNC_CELL,
                                                )}
                                            >
                                                Success
                                            </th>
                                            <th
                                                className={cn(
                                                    'px-3 py-2 text-right font-medium',
                                                    SYNC_CELL,
                                                )}
                                            >
                                                Failed / pending
                                            </th>
                                            <th
                                                className={cn(
                                                    'px-3 py-2 font-medium',
                                                    ISSUE_CELL,
                                                )}
                                            >
                                                Need to sync
                                            </th>
                                            <th
                                                className={cn(
                                                    'px-3 py-2 font-medium',
                                                    ISSUE_CELL,
                                                )}
                                            >
                                                Event miss
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {rows.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={8}
                                                    className="px-4 py-10 text-center text-sm text-muted-foreground"
                                                >
                                                    No entity data returned for
                                                    the selected filters.
                                                </td>
                                            </tr>
                                        )}
                                        {rows.map((row) => (
                                            <EntityTableRow
                                                key={row.name}
                                                row={row}
                                                expanded={expandedRows.has(
                                                    row.name,
                                                )}
                                                onToggle={() =>
                                                    toggleRow(row.name)
                                                }
                                            />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <p className="border-t border-sidebar-border/70 px-4 py-2 text-xs text-muted-foreground dark:border-sidebar-border">
                                Showing {rows.length} entities
                                {issueRows.length > 0 &&
                                    ` · ${issueRows.length} with issues`}
                            </p>
                        </div>
                    </>
                )}

                {!data && !loading && !error && (
                    <div className="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-sidebar-border/70 px-6 py-16 text-center dark:border-sidebar-border">
                        <RefreshCw className="size-8 text-muted-foreground/60" />
                        <div>
                            <p className="text-sm font-medium">
                                No data loaded yet
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Adjust filters if needed, then click Fetch data.
                            </p>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

OutboundSyncIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Outbound Sync', href: index.url() },
    ],
};
