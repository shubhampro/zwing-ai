import { Head, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    Clock,
    Copy,
    Loader2,
    MinusCircle,
    RefreshCw,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { check } from '@/actions/App/Http/Controllers/InboundSyncController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import { index } from '@/routes/inbound-sync';

type Organization = {
    id: number;
    label: string;
    vendor_id: number;
    client_id: string;
};

type SyncStatus = 'synced' | 'pending' | 'failed' | 'idle';

type DocumentDetail = {
    id: string;
    client_event_unique_code: string | null;
    request: unknown;
    response: unknown;
};

type ResultRow = {
    name: string;
    trans: number;
    val: number;
    fail_cnt: number;
    pending: number;
    success_sync: number | number[];
    need_to_sync: string[];
    event_miss: string[];
    failed_details: DocumentDetail[];
    pending_details: DocumentDetail[];
    need_to_sync_count: number;
    event_miss_count: number;
    status: SyncStatus;
};

type CheckResult = {
    filters: {
        v_id: number;
        client_id: string | null;
        client_event_name: string | null;
        client_event_unique_code: string | null;
    };
    date_range: {
        start: string;
        end: string;
    };
    stats: {
        total_sync: number;
        success_sync: number;
        pending: number;
        remain_sync: number;
        sync_percentage: number;
    };
    result: ResultRow[];
};

function SummaryCard({
    label,
    value,
    suffix,
    variant = 'default',
}: {
    label: string;
    value: string | number;
    suffix?: string;
    variant?: 'default' | 'success' | 'warning' | 'destructive';
}) {
    const valueClass =
        variant === 'success'
            ? 'text-emerald-600 dark:text-emerald-400'
            : variant === 'warning'
              ? 'text-amber-600 dark:text-amber-400'
              : variant === 'destructive'
                ? 'text-destructive'
                : '';

    return (
        <div className="flex flex-col gap-1 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
            <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {label}
            </span>
            <span className={`text-2xl font-semibold tabular-nums ${valueClass}`}>
                {value}
                {suffix && (
                    <span className="ml-1 text-sm font-normal text-muted-foreground">
                        {suffix}
                    </span>
                )}
            </span>
        </div>
    );
}

function StatusBadge({ status }: { status: SyncStatus }) {
    switch (status) {
        case 'synced':
            return (
                <Badge className="gap-1 bg-emerald-600/10 text-emerald-700 hover:bg-emerald-600/10 dark:text-emerald-400">
                    <CheckCircle2 className="size-3" />
                    Synced
                </Badge>
            );
        case 'pending':
            return (
                <Badge className="gap-1 bg-amber-500/10 text-amber-700 hover:bg-amber-500/10 dark:text-amber-400">
                    <Clock className="size-3" />
                    Pending
                </Badge>
            );
        case 'failed':
            return (
                <Badge variant="destructive" className="gap-1">
                    <AlertTriangle className="size-3" />
                    Needs attention
                </Badge>
            );
        default:
            return (
                <Badge variant="outline" className="gap-1">
                    <MinusCircle className="size-3" />
                    No activity
                </Badge>
            );
    }
}

function parseExcludedIds(value: string): Set<string> {
    return new Set(
        value
            .split(/[\n,]+/)
            .map((part) =>
                part
                    .trim()
                    .replace(/^\(+|\)+$/g, '')
                    .replace(/^['"]+|['"]+$/g, ''),
            )
            .filter(Boolean),
    );
}

function filterDocumentDetails(
    details: DocumentDetail[],
    idSearch: string,
    excludedIds: Set<string>,
): DocumentDetail[] {
    const query = idSearch.trim().toLowerCase();

    return details.filter((detail) => {
        if (excludedIds.has(detail.id)) {
            return false;
        }

        if (query === '') {
            return true;
        }

        return (
            detail.id.toLowerCase().includes(query) ||
            (detail.client_event_unique_code ?? '')
                .toLowerCase()
                .includes(query)
        );
    });
}

function formatJsonValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '—';
    }

    if (typeof value === 'string') {
        const trimmed = value.trim();

        if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
            try {
                return JSON.stringify(JSON.parse(trimmed), null, 2);
            } catch {
                return value;
            }
        }

        return value;
    }

    return JSON.stringify(value, null, 2);
}

function JsonBlock({
    label,
    value,
    variant = 'default',
}: {
    label: string;
    value: unknown;
    variant?: 'default' | 'destructive';
}) {
    const formatted = formatJsonValue(value);
    const borderClass =
        variant === 'destructive'
            ? 'border-destructive/30 bg-destructive/5'
            : 'border-sidebar-border/70 bg-muted/30';

    return (
        <div className={`rounded-lg border p-3 ${borderClass}`}>
            <div className="mb-2 flex items-center justify-between gap-2">
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    {label}
                </p>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="h-7 gap-1.5 text-xs"
                    onClick={() => {
                        void copyText(formatted, label);
                    }}
                >
                    <Copy className="size-3" />
                    Copy
                </Button>
            </div>
            <pre className="max-h-64 overflow-auto rounded-md bg-background/80 p-3 font-mono text-xs leading-relaxed whitespace-pre-wrap break-all">
                {formatted}
            </pre>
        </div>
    );
}

function DocumentDetailsList({
    title,
    details,
    totalCount,
    variant,
}: {
    title: string;
    details: DocumentDetail[];
    totalCount: number;
    variant: 'warning' | 'destructive';
}) {
    if (details.length === 0 && totalCount === 0) {
        return null;
    }

    const listTitle = formatListTitle(title, details.length, totalCount);
    const borderClass =
        variant === 'destructive'
            ? 'border-destructive/30'
            : 'border-amber-500/30';

    return (
        <div className={`space-y-3 rounded-lg border p-3 ${borderClass}`}>
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {listTitle}
            </p>
            {details.length === 0 ? (
                <p className="text-xs text-muted-foreground">
                    No documents match the current filter.
                </p>
            ) : (
                details.map((detail) => (
                    <div
                        key={detail.id}
                        className="space-y-3 rounded-lg border border-sidebar-border/70 bg-background/80 p-3 dark:border-sidebar-border"
                    >
                        <div className="flex flex-wrap items-center gap-2">
                            <code className="font-mono text-xs">{detail.id}</code>
                            {detail.client_event_unique_code && (
                                <Badge variant="outline" className="font-mono text-xs">
                                    {detail.client_event_unique_code}
                                </Badge>
                            )}
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="ml-auto h-7 gap-1.5 text-xs"
                                onClick={() => {
                                    void copyText(detail.id, 'MongoDB ID');
                                }}
                            >
                                <Copy className="size-3" />
                                Copy ID
                            </Button>
                        </div>
                        <JsonBlock label="Request" value={detail.request} />
                        <JsonBlock
                            label="Response"
                            value={detail.response}
                            variant={
                                variant === 'destructive' ? 'destructive' : 'default'
                            }
                        />
                    </div>
                ))
            )}
        </div>
    );
}

function formatListTitle(
    title: string,
    visibleCount: number,
    totalCount: number,
): string {
    if (visibleCount === totalCount) {
        return `${title} (${totalCount})`;
    }

    return `${title} (${visibleCount} of ${totalCount})`;
}

async function copyText(text: string, label: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(text);
        toast.success(`${label} copied`);
    } catch {
        toast.error('Could not copy to clipboard');
    }
}

function ResultRowItem({
    row,
    idSearch,
    excludedIds,
}: {
    row: ResultRow;
    idSearch: string;
    excludedIds: Set<string>;
}) {
    const [expanded, setExpanded] = useState(
        row.status === 'failed' || row.status === 'pending',
    );
    const filteredFailedDetails = useMemo(
        () =>
            filterDocumentDetails(
                row.failed_details ?? [],
                idSearch,
                excludedIds,
            ),
        [row.failed_details, idSearch, excludedIds],
    );
    const filteredPendingDetails = useMemo(
        () =>
            filterDocumentDetails(
                row.pending_details ?? [],
                idSearch,
                excludedIds,
            ),
        [row.pending_details, idSearch, excludedIds],
    );
    const hasDetails =
        row.need_to_sync_count > 0 ||
        row.event_miss_count > 0 ||
        (row.failed_details?.length ?? 0) > 0 ||
        (row.pending_details?.length ?? 0) > 0;
    const hasVisibleDetails =
        filteredFailedDetails.length > 0 ||
        filteredPendingDetails.length > 0;

    return (
        <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <button
                type="button"
                className="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-muted/40"
                onClick={() => hasDetails && setExpanded((v) => !v)}
                disabled={!hasDetails}
            >
                {hasDetails ? (
                    expanded ? (
                        <ChevronDown className="size-4 shrink-0 text-muted-foreground" />
                    ) : (
                        <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                    )
                ) : (
                    <span className="size-4 shrink-0" />
                )}

                <div className="flex min-w-0 flex-1 flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <span className="font-medium font-mono text-sm">
                            {row.name}
                        </span>
                        <StatusBadge status={row.status} />
                    </div>

                    <div className="flex flex-wrap gap-3 text-xs text-muted-foreground">
                        <span>
                            Total:{' '}
                            <strong className="text-foreground">
                                {row.trans}
                            </strong>
                        </span>
                        <span>
                            Synced:{' '}
                            <strong className="text-foreground">
                                {Array.isArray(row.success_sync)
                                    ? row.success_sync.length
                                    : row.success_sync}
                            </strong>
                        </span>
                        <span>
                            Pending:{' '}
                            <strong className="text-foreground">
                                {row.pending}
                            </strong>
                        </span>
                        {row.fail_cnt > 0 && (
                            <span className="text-destructive">
                                Failed:{' '}
                                <strong>{row.fail_cnt}</strong>
                            </span>
                        )}
                    </div>
                </div>
            </button>

            {expanded && hasDetails && (
                <div className="space-y-3 border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                    <DocumentDetailsList
                        title="Failed documents"
                        details={filteredFailedDetails}
                        totalCount={row.need_to_sync_count}
                        variant="destructive"
                    />
                    <DocumentDetailsList
                        title="Pending documents"
                        details={filteredPendingDetails}
                        totalCount={row.event_miss_count}
                        variant="warning"
                    />
                    {!hasVisibleDetails && (
                        <p className="text-xs text-muted-foreground">
                            No document IDs match the current filter for this
                            event type.
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}

export default function InboundSyncIndex({
    organizations,
    defaultStartDate,
    defaultEndDate,
    mongoConfigured,
}: {
    organizations: Organization[];
    defaultStartDate: string;
    defaultEndDate: string;
    mongoConfigured: boolean;
}) {
    const { data, setData, processing, errors } = useForm({
        v_id: '',
        client_id: '',
        client_event_name: '',
        client_event_unique_code: '',
        start_date: defaultStartDate,
        end_date: defaultEndDate,
    });

    const [result, setResult] = useState<CheckResult | null>(null);
    const [runError, setRunError] = useState<string | null>(null);
    const [running, setRunning] = useState(false);
    const [visibleEventTypes, setVisibleEventTypes] = useState<Set<string>>(
        new Set(),
    );
    const [idSearch, setIdSearch] = useState('');
    const [excludeIdsText, setExcludeIdsText] = useState('');

    const excludedIds = useMemo(
        () => parseExcludedIds(excludeIdsText),
        [excludeIdsText],
    );

    useEffect(() => {
        if (!result) {
            setVisibleEventTypes(new Set());
            setIdSearch('');
            setExcludeIdsText('');

            return;
        }

        setVisibleEventTypes(new Set(result.result.map((row) => row.name)));
        setIdSearch('');
        setExcludeIdsText('');
    }, [result]);

    const visibleRows = useMemo(() => {
        if (!result) {
            return [];
        }

        return result.result.filter((row) => visibleEventTypes.has(row.name));
    }, [result, visibleEventTypes]);

    const hasActiveIdFilter = idSearch.trim() !== '' || excludedIds.size > 0;
    const hasActiveEventFilter =
        result !== null && visibleEventTypes.size < result.result.length;

    const isReady =
        data.v_id !== '' && data.start_date !== '' && data.end_date !== '';

    function fillFromOrganization(orgId: string) {
        const org = organizations.find((item) => String(item.id) === orgId);
        if (!org) {
            return;
        }

        setData((prev) => ({
            ...prev,
            v_id: String(org.vendor_id),
            client_id: org.client_id,
        }));
    }

    async function handleCheck(e: React.FormEvent) {
        e.preventDefault();
        setRunError(null);
        setResult(null);
        setRunning(true);

        try {
            const xsrfToken = decodeURIComponent(
                document.cookie
                    .split('; ')
                    .find((row) => row.startsWith('XSRF-TOKEN='))
                    ?.split('=')[1] ?? '',
            );

            const res = await fetch(check.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    v_id: Number(data.v_id),
                    start_date: data.start_date,
                    end_date: data.end_date,
                    client_id: data.client_id.trim() || null,
                    client_event_name: data.client_event_name.trim() || null,
                    client_event_unique_code:
                        data.client_event_unique_code.trim() || null,
                }),
            });

            const json = await res.json().catch(() => ({}));

            if (!res.ok) {
                setRunError(json?.message ?? `Request failed (${res.status})`);
                return;
            }

            setResult(json as CheckResult);
        } catch (err) {
            setRunError(err instanceof Error ? err.message : 'Unknown error');
        } finally {
            setRunning(false);
        }
    }

    const issueCount =
        visibleRows.filter(
            (row) => row.status === 'failed' || row.status === 'pending',
        ).length ?? 0;

    function toggleEventType(eventName: string, checked: boolean): void {
        setVisibleEventTypes((current) => {
            const next = new Set(current);

            if (checked) {
                next.add(eventName);
            } else {
                next.delete(eventName);
            }

            return next;
        });
    }

    function resetResultFilters(): void {
        if (!result) {
            return;
        }

        setVisibleEventTypes(new Set(result.result.map((row) => row.name)));
        setIdSearch('');
        setExcludeIdsText('');
    }

    return (
        <>
            <Head title="Inbound Sync Check" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Inbound Sync Check"
                    description="Check whether ERP inbound API events are synced to Zwing via MongoDB."
                />

                {!mongoConfigured && (
                    <div className="rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-800 dark:text-amber-300">
                        MongoDB is not configured. Add{' '}
                        <code className="rounded bg-background/60 px-1">
                            MONGODB_SSH_DATABASE
                        </code>{' '}
                        (or{' '}
                        <code className="rounded bg-background/60 px-1">
                            MONGO_DB_DATABASE
                        </code>
                        ) to your{' '}
                        <code className="rounded bg-background/60 px-1">.env</code>{' '}
                        file. If using an SSH tunnel, ensure it is open on port{' '}
                        <code className="rounded bg-background/60 px-1">
                            MONGODB_SSH_LOCAL_PORT
                        </code>
                        .
                    </div>
                )}

                <form
                    onSubmit={handleCheck}
                    className="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border md:p-5"
                >
                    {organizations.length > 0 && (
                        <div className="mb-4 space-y-2">
                            <Label htmlFor="org_quick_fill">
                                Quick fill from organization
                            </Label>
                            <Select onValueChange={fillFromOrganization}>
                                <SelectTrigger
                                    id="org_quick_fill"
                                    className="w-full md:max-w-md"
                                >
                                    <SelectValue placeholder="Select organization to auto-fill vendor & client ID…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {organizations.map((org) => (
                                        <SelectItem
                                            key={org.id}
                                            value={String(org.id)}
                                        >
                                            {org.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div className="space-y-2">
                            <Label htmlFor="v_id">
                                Vendor ID{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="v_id"
                                type="number"
                                min={1}
                                placeholder="e.g. 287"
                                value={data.v_id}
                                onChange={(e) => setData('v_id', e.target.value)}
                            />
                            {errors.v_id && (
                                <p className="text-xs text-destructive">
                                    {errors.v_id}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="start_date">
                                Start date{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="start_date"
                                type="datetime-local"
                                value={data.start_date}
                                onChange={(e) =>
                                    setData('start_date', e.target.value)
                                }
                            />
                            {errors.start_date && (
                                <p className="text-xs text-destructive">
                                    {errors.start_date}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="end_date">
                                End date{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="end_date"
                                type="datetime-local"
                                value={data.end_date}
                                min={data.start_date}
                                onChange={(e) =>
                                    setData('end_date', e.target.value)
                                }
                            />
                            {errors.end_date && (
                                <p className="text-xs text-destructive">
                                    {errors.end_date}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="client_id">Client ID</Label>
                            <Input
                                id="client_id"
                                placeholder="e.g. V12345RTZW87 (optional)"
                                value={data.client_id}
                                onChange={(e) =>
                                    setData('client_id', e.target.value)
                                }
                            />
                            {errors.client_id && (
                                <p className="text-xs text-destructive">
                                    {errors.client_id}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="client_event_name">
                                Client event name
                            </Label>
                            <Input
                                id="client_event_name"
                                placeholder="e.g. snd.invoice.added (optional)"
                                value={data.client_event_name}
                                onChange={(e) =>
                                    setData('client_event_name', e.target.value)
                                }
                            />
                            {errors.client_event_name && (
                                <p className="text-xs text-destructive">
                                    {errors.client_event_name}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="client_event_unique_code">
                                Client event unique code
                            </Label>
                            <Input
                                id="client_event_unique_code"
                                placeholder="e.g. 99776 (optional)"
                                value={data.client_event_unique_code}
                                onChange={(e) =>
                                    setData(
                                        'client_event_unique_code',
                                        e.target.value,
                                    )
                                }
                            />
                            {errors.client_event_unique_code && (
                                <p className="text-xs text-destructive">
                                    {errors.client_event_unique_code}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="mt-4">
                        <Button
                            type="submit"
                            disabled={
                                !isReady ||
                                running ||
                                processing ||
                                !mongoConfigured
                            }
                            className="w-full sm:w-auto sm:min-w-44"
                        >
                            {running ? (
                                <>
                                    <Loader2 className="size-4 animate-spin" />
                                    Fetching…
                                </>
                            ) : (
                                <>
                                    <RefreshCw className="size-4" />
                                    Fetch sync status
                                </>
                            )}
                        </Button>
                    </div>
                </form>

                {runError && (
                    <div className="rounded-xl border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                        {runError}
                    </div>
                )}

                {result && (
                    <div className="flex flex-col gap-5">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="text-sm font-medium">
                                    Vendor {result.filters.v_id}
                                    {result.filters.client_id && (
                                        <>
                                            {' '}
                                            ·{' '}
                                            <span className="font-mono">
                                                {result.filters.client_id}
                                            </span>
                                        </>
                                    )}
                                    {result.filters.client_event_name && (
                                        <>
                                            {' '}
                                            · Event{' '}
                                            <span className="font-mono">
                                                {result.filters.client_event_name}
                                            </span>
                                        </>
                                    )}
                                    {result.filters.client_event_unique_code && (
                                        <>
                                            {' '}
                                            · Code{' '}
                                            <span className="font-mono">
                                                {
                                                    result.filters
                                                        .client_event_unique_code
                                                }
                                            </span>
                                        </>
                                    )}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {result.date_range.start} →{' '}
                                    {result.date_range.end}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setResult(null);
                                    setRunError(null);
                                }}
                            >
                                <RefreshCw className="size-3.5" />
                                Clear results
                            </Button>
                        </div>

                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <SummaryCard
                                label="Total sync"
                                value={result.stats.total_sync}
                            />
                            <SummaryCard
                                label="Success sync"
                                value={result.stats.success_sync}
                                variant="success"
                            />
                            <SummaryCard
                                label="Pending"
                                value={result.stats.pending}
                                variant="warning"
                            />
                            <SummaryCard
                                label="Sync rate"
                                value={result.stats.sync_percentage.toFixed(2)}
                                suffix="%"
                                variant={
                                    result.stats.sync_percentage >= 100
                                        ? 'success'
                                        : 'warning'
                                }
                            />
                        </div>

                        <div className="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                            <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p className="text-sm font-medium">
                                        Filter results
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Hide event types or narrow document IDs
                                        before copying.
                                    </p>
                                </div>
                                {(hasActiveIdFilter || hasActiveEventFilter) && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={resetResultFilters}
                                    >
                                        Reset filters
                                    </Button>
                                )}
                            </div>

                            <div className="space-y-4">
                                <div className="space-y-2">
                                    <Label>Event types</Label>
                                    <div className="flex flex-wrap gap-2">
                                        {result.result.map((row) => {
                                            const checked = visibleEventTypes.has(
                                                row.name,
                                            );

                                            return (
                                                <label
                                                    key={row.name}
                                                    className="flex cursor-pointer items-center gap-2 rounded-lg border border-sidebar-border/70 px-3 py-2 text-sm dark:border-sidebar-border"
                                                >
                                                    <Checkbox
                                                        checked={checked}
                                                        onCheckedChange={(
                                                            value,
                                                        ) =>
                                                            toggleEventType(
                                                                row.name,
                                                                value === true,
                                                            )
                                                        }
                                                    />
                                                    <span className="font-mono text-sm">
                                                        {row.name}
                                                    </span>
                                                </label>
                                            );
                                        })}
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="id_search">
                                            Search MongoDB ID
                                        </Label>
                                        <Input
                                            id="id_search"
                                            placeholder="e.g. 69df49200e8f0c6f726e7ca0"
                                            value={idSearch}
                                            onChange={(e) =>
                                                setIdSearch(e.target.value)
                                            }
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="exclude_ids">
                                            Exclude IDs
                                        </Label>
                                        <textarea
                                            id="exclude_ids"
                                            rows={3}
                                            placeholder="Paste IDs to hide, comma or line separated"
                                            value={excludeIdsText}
                                            onChange={(e) =>
                                                setExcludeIdsText(
                                                    e.target.value,
                                                )
                                            }
                                            className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {result.result.length === 0 ? (
                            <div className="rounded-xl border border-sidebar-border/70 px-4 py-8 text-center text-sm text-muted-foreground dark:border-sidebar-border">
                                No results found
                                {result.filters.client_event_name
                                    ? ` for event "${result.filters.client_event_name}"`
                                    : ''}
                                .
                            </div>
                        ) : visibleRows.length === 0 ? (
                            <div className="rounded-xl border border-sidebar-border/70 px-4 py-8 text-center text-sm text-muted-foreground dark:border-sidebar-border">
                                All event types are hidden. Select at least one
                                event type above to see results.
                            </div>
                        ) : (
                            <>
                                {issueCount > 0 ? (
                                    <div className="rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-800 dark:text-amber-300">
                                        {issueCount} transaction type
                                        {issueCount === 1 ? '' : 's'} need
                                        attention — expand rows below to see
                                        unsynced document IDs.
                                    </div>
                                ) : (
                                    <div className="rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300">
                                        All transaction types are fully synced
                                        for this date range.
                                    </div>
                                )}

                                <div className="flex flex-col gap-2">
                                    {visibleRows.map((row) => (
                                        <ResultRowItem
                                            key={row.name}
                                            row={row}
                                            idSearch={idSearch}
                                            excludedIds={excludedIds}
                                        />
                                    ))}
                                </div>
                            </>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

InboundSyncIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Inbound Sync', href: index.url() },
    ],
};
