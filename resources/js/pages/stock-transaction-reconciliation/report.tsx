import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Copy,
    Download,
    FileText,
    Filter,
    Loader2,
    RefreshCw,
    Search,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCan } from '@/hooks/use-can';
import {
    isExternalQueryPollPayload,
    waitForExternalQuery,
} from '@/lib/external-query';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { copyToClipboard } from '@/lib/copy-to-clipboard';
import {
    exportMethod,
    logDetails,
    syncRow,
} from '@/routes/stock-transaction-reconciliation/report';
import { index, report, show } from '@/routes/stock-transaction-reconciliation';

type MatchStatus = 'matched' | 'qty_mismatch' | 'zwing_only' | 'erp_only';
type StatusFilter = 'all' | MatchStatus;

const REPORT_FILTER_PARTIAL = [
    'summary',
    'rows',
    'pagination',
    'filter',
    'filters',
] as const;

const REPORT_FILTER_VISIT = {
    preserveState: true,
    preserveScroll: true,
    only: [...REPORT_FILTER_PARTIAL],
};

const ICODE_SEARCH_DEBOUNCE_MS = 300;

type ReportRow = {
    site_code: string;
    icode: string;
    batch_no: string | null;
    sprefcode: string;
    stock_point_name: string;
    zwing_qty: number | null;
    erp_qty: number | null;
    match_status: MatchStatus;
};

type LogEntry = {
    id: number;
    doc_no: string;
    qty: number;
    enttype: string;
};

type LogDetailResponse = {
    has_zwing_logs: boolean;
    has_erp_logs: boolean;
    zwing_query_ms: number | null;
    erp_query_ms: number | null;
    matched: { zwing: LogEntry[]; erp: LogEntry[] };
    mismatch: { zwing: LogEntry[]; erp: LogEntry[] };
};

type SyncRowResponse = {
    site_code: string;
    icode: string;
    batch_no: string;
    sprefcode: string;
    stock_point_name: string | null;
    zwing_qty: number | null;
    erp_qty: number | null;
    match_status: MatchStatus | null;
    removed: boolean;
    zwing_query_ms: number | null;
    erp_query_ms: number | null;
};

function reportRowKey(
    row: Pick<ReportRow, 'site_code' | 'icode' | 'batch_no' | 'sprefcode'>,
): string {
    return `${row.site_code}|${row.icode}|${row.batch_no ?? ''}|${row.sprefcode}`;
}

function formatLogQueryDuration(ms: number | null): string | null {
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

type Summary = {
    total: number;
    matched: number;
    qty_mismatch: number;
    zwing_only: number;
    erp_only: number;
};

type Pagination = {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
};

type DifferenceFilter = 'all' | 'zero' | 'non_zero' | 'missing_side';

type Props = {
    session: {
        id: number;
        name: string;
        v_id: number;
        status: string;
        source: string | null;
        zwing_file_name: string | null;
        erp_file_name: string | null;
        zwing_log_file_name: string | null;
        erp_log_file_name: string | null;
    };
    summary: Summary;
    rows: ReportRow[];
    pagination: Pagination;
    filter: string;
    filters: {
        icode_query: string;
        site_code: string;
        stock_point: string;
        difference: DifferenceFilter;
    };
    siteCodeOptions: string[];
    stockPointOptions: string[];
};

const statusConfig: Record<
    MatchStatus,
    {
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
    }
> = {
    matched: { label: 'Matched', variant: 'default' },
    qty_mismatch: { label: 'Qty mismatch', variant: 'destructive' },
    zwing_only: { label: 'Zwing only', variant: 'outline' },
    erp_only: { label: 'ERP only', variant: 'secondary' },
};

const differenceFilters: { value: DifferenceFilter; label: string }[] = [
    { value: 'all', label: 'All differences' },
    { value: 'zero', label: 'Exact qty match' },
    { value: 'non_zero', label: 'Qty difference' },
    { value: 'missing_side', label: 'Missing on one side' },
];

const ANY_STOCK_POINT = '__any__';
const ANY_SITE_CODE = '__any__';

const statusFilterCards: {
    value: StatusFilter;
    label: string;
    summaryKey: keyof Summary;
    accent: string;
    active: string;
}[] = [
    {
        value: 'all',
        label: 'Total',
        summaryKey: 'total',
        accent: 'text-foreground',
        active: 'border-foreground/40 bg-muted/60 ring-2 ring-foreground/15',
    },
    {
        value: 'matched',
        label: 'Matched',
        summaryKey: 'matched',
        accent: 'text-green-600 dark:text-green-400',
        active: 'border-green-500/50 bg-green-500/10 ring-2 ring-green-500/20',
    },
    {
        value: 'qty_mismatch',
        label: 'Qty mismatch',
        summaryKey: 'qty_mismatch',
        accent: 'text-destructive',
        active: 'border-destructive/50 bg-destructive/10 ring-2 ring-destructive/20',
    },
    {
        value: 'zwing_only',
        label: 'Zwing only',
        summaryKey: 'zwing_only',
        accent: 'text-amber-600 dark:text-amber-400',
        active: 'border-amber-500/50 bg-amber-500/10 ring-2 ring-amber-500/20',
    },
    {
        value: 'erp_only',
        label: 'ERP only',
        summaryKey: 'erp_only',
        accent: 'text-blue-600 dark:text-blue-400',
        active: 'border-blue-500/50 bg-blue-500/10 ring-2 ring-blue-500/20',
    },
];

async function copyText(text: string, label: string): Promise<void> {
    const copied = await copyToClipboard(text);

    if (copied) {
        toast.success(`${label} copied`);
    } else {
        toast.error('Could not copy to clipboard');
    }
}

function CopyIconButton({ text, label }: { text: string; label: string }) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className="size-7 shrink-0"
            onClick={() => {
                void copyText(text, label);
            }}
            title={`Copy ${label}`}
        >
            <Copy className="size-3.5" />
            <span className="sr-only">Copy {label}</span>
        </Button>
    );
}

function LogSideTable({
    title,
    rows,
    emptyMessage,
    queryMs,
}: {
    title: string;
    rows: LogEntry[];
    emptyMessage: string;
    queryMs: number | null;
}) {
    const durationLabel = formatLogQueryDuration(queryMs);

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-baseline justify-between gap-2">
                <p className="text-sm font-medium">{title}</p>
                {durationLabel !== null && (
                    <p className="text-xs text-muted-foreground tabular-nums">
                        Query {durationLabel}
                    </p>
                )}
            </div>
            <div className="overflow-x-auto rounded-md border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b bg-muted/50 text-left">
                            <th className="px-3 py-2 font-medium">Doc no</th>
                            <th className="px-3 py-2 text-right font-medium">
                                Qty
                            </th>
                            <th className="px-3 py-2 font-medium">
                                Entry type
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {rows.length === 0 && (
                            <tr>
                                <td
                                    colSpan={3}
                                    className="px-3 py-6 text-center text-muted-foreground"
                                >
                                    {emptyMessage}
                                </td>
                            </tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="hover:bg-muted/30">
                                <td className="px-3 py-2 font-mono text-xs">
                                    {row.doc_no || '—'}
                                </td>
                                <td className="px-3 py-2 text-right tabular-nums">
                                    {row.qty.toLocaleString()}
                                </td>
                                <td className="px-3 py-2 text-muted-foreground">
                                    {row.enttype || '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function FilterChip({
    label,
    value,
    onRemove,
}: {
    label: string;
    value: string;
    onRemove: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onRemove}
            className="inline-flex items-center gap-1.5 rounded-full border bg-background px-2.5 py-1 text-xs transition-colors hover:bg-muted"
        >
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value}</span>
            <X className="size-3 text-muted-foreground" />
        </button>
    );
}

export default function StockTransactionReconciliationReport({
    session,
    summary,
    rows,
    pagination,
    filter,
    filters: initialFilters,
    siteCodeOptions,
    stockPointOptions,
}: Props) {
    const can = useCan();
    const [tableRows, setTableRows] = useState(rows);
    const [syncingKey, setSyncingKey] = useState<string | null>(null);
    const [icodeQuery, setIcodeQuery] = useState(initialFilters.icode_query);
    const [siteCode, setSiteCode] = useState(
        initialFilters.site_code || ANY_SITE_CODE,
    );
    const [stockPoint, setStockPoint] = useState(
        initialFilters.stock_point || ANY_STOCK_POINT,
    );
    const [difference, setDifference] = useState<DifferenceFilter>(
        initialFilters.difference,
    );
    const skipIcodeDebounceRef = useRef(false);

    useEffect(() => {
        setTableRows(rows);
    }, [rows]);

    const activeFilters = useMemo(() => {
        return {
            filter: filter as StatusFilter,
            icode_query: icodeQuery.trim(),
            site_code: siteCode === ANY_SITE_CODE ? '' : siteCode,
            stock_point: stockPoint === ANY_STOCK_POINT ? '' : stockPoint,
            difference,
        };
    }, [difference, filter, icodeQuery, siteCode, stockPoint]);

    const buildQueryParams = useCallback(
        (
            page = 1,
            overrides: Partial<{
                filter: string;
                icode_query: string;
                site_code: string;
                stock_point: string;
                difference: DifferenceFilter;
            }> = {},
        ) => {
            const next = {
                filter: activeFilters.filter,
                icode_query: activeFilters.icode_query,
                site_code: activeFilters.site_code,
                stock_point: activeFilters.stock_point,
                difference: activeFilters.difference,
                ...overrides,
                page,
            };

            return {
                filter: next.filter,
                icode_query: next.icode_query,
                site_code: next.site_code,
                stock_point: next.stock_point,
                difference: next.difference,
                page: next.page,
            };
        },
        [activeFilters],
    );

    const navigateWithFilters = useCallback(
        (
            overrides: Partial<{
                filter: string;
                icode_query: string;
                site_code: string;
                stock_point: string;
                difference: DifferenceFilter;
            }> = {},
            page = 1,
        ) => {
            router.get(
                report.url(session.id),
                buildQueryParams(page, overrides),
                REPORT_FILTER_VISIT,
            );
        },
        [buildQueryParams, session.id],
    );

    useEffect(() => {
        if (skipIcodeDebounceRef.current) {
            skipIcodeDebounceRef.current = false;

            return;
        }

        const trimmed = icodeQuery.trim();

        if (trimmed === initialFilters.icode_query) {
            return;
        }

        const timeoutId = window.setTimeout(() => {
            navigateWithFilters({ icode_query: trimmed });
        }, ICODE_SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timeoutId);
    }, [icodeQuery, initialFilters.icode_query, navigateWithFilters]);

    const exportQuery = useMemo(() => {
        const query: Record<string, string> = {};

        if (activeFilters.filter !== 'all') {
            query.filter = activeFilters.filter;
        }
        if (activeFilters.icode_query !== '') {
            query.icode_query = activeFilters.icode_query;
        }
        if (activeFilters.site_code !== '') {
            query.site_code = activeFilters.site_code;
        }
        if (activeFilters.stock_point !== '') {
            query.stock_point = activeFilters.stock_point;
        }
        if (activeFilters.difference !== 'all') {
            query.difference = activeFilters.difference;
        }

        return query;
    }, [activeFilters]);

    const isConnection = (session.source ?? 'csv') === 'connection';
    const hasAnyLogs = isConnection
        ? session.zwing_file_name !== null || session.erp_file_name !== null
        : session.zwing_log_file_name !== null ||
          session.erp_log_file_name !== null;
    const showLogActions = filter === 'qty_mismatch' && hasAnyLogs;
    const showSyncActions = isConnection && can('stock-recon.manage');
    const showActions = showLogActions || showSyncActions;

    const [logModalOpen, setLogModalOpen] = useState(false);
    const [logModalTab, setLogModalTab] = useState<'matched' | 'mismatch'>(
        'matched',
    );
    const [selectedRow, setSelectedRow] = useState<ReportRow | null>(null);
    const [logDetailsData, setLogDetailsData] =
        useState<LogDetailResponse | null>(null);
    const [logDetailsLoading, setLogDetailsLoading] = useState(false);
    const [logDetailsError, setLogDetailsError] = useState<string | null>(null);

    const [syncModalOpen, setSyncModalOpen] = useState(false);
    const [syncModalRow, setSyncModalRow] = useState<ReportRow | null>(null);
    const [syncModalBefore, setSyncModalBefore] = useState<ReportRow | null>(
        null,
    );
    const [syncModalResult, setSyncModalResult] =
        useState<SyncRowResponse | null>(null);
    const [syncModalLoading, setSyncModalLoading] = useState(false);
    const [syncModalError, setSyncModalError] = useState<string | null>(null);

    const loadLogDetails = useCallback(
        async (row: ReportRow) => {
            setLogDetailsLoading(true);
            setLogDetailsError(null);
            setLogDetailsData(null);

            const url = logDetails.url(session.id, {
                query: {
                    site_code: row.site_code,
                    icode: row.icode,
                    batch_no: row.batch_no ?? '',
                    sprefcode: row.sprefcode,
                },
            });

            try {
                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok && response.status !== 202) {
                    throw new Error('Failed to load log details.');
                }

                const payload = (await response.json()) as
                    | LogDetailResponse
                    | Awaited<ReturnType<typeof waitForExternalQuery>>;

                let data: LogDetailResponse;

                if (isExternalQueryPollPayload(payload)) {
                    const settled =
                        payload.status === 'completed'
                            ? payload
                            : await waitForExternalQuery(payload.id);
                    data = settled.result as LogDetailResponse;
                } else {
                    data = payload;
                }

                setLogDetailsData(data);

                const matchedCount =
                    data.matched.zwing.length + data.matched.erp.length;
                const mismatchCount =
                    data.mismatch.zwing.length + data.mismatch.erp.length;

                setLogModalTab(
                    matchedCount > 0 || mismatchCount === 0
                        ? 'matched'
                        : 'mismatch',
                );
            } catch {
                setLogDetailsError('Could not load log entries for this SKU.');
            } finally {
                setLogDetailsLoading(false);
            }
        },
        [session.id],
    );

    function openLogModal(row: ReportRow) {
        setSelectedRow(row);
        setLogModalTab('matched');
        setLogModalOpen(true);
        loadLogDetails(row);
    }

    function closeLogModal() {
        setLogModalOpen(false);
        setSelectedRow(null);
        setLogDetailsData(null);
        setLogDetailsError(null);
    }

    function openSyncModal(row: ReportRow) {
        setSyncModalRow(row);
        setSyncModalBefore({ ...row });
        setSyncModalResult(null);
        setSyncModalError(null);
        setSyncModalOpen(true);
        void syncReportRow(row);
    }

    function closeSyncModal() {
        setSyncModalOpen(false);
        setSyncModalRow(null);
        setSyncModalBefore(null);
        setSyncModalResult(null);
        setSyncModalError(null);
        setSyncModalLoading(false);
        setSyncingKey(null);
    }

    async function syncReportRow(row: ReportRow) {
        const key = reportRowKey(row);
        setSyncingKey(key);
        setSyncModalLoading(true);
        setSyncModalError(null);
        setSyncModalResult(null);

        try {
            const xsrfToken = decodeURIComponent(
                document.cookie
                    .split('; ')
                    .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                    ?.split('=')[1] ?? '',
            );

            const response = await fetch(syncRow.url(session.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken,
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    site_code: row.site_code,
                    icode: row.icode,
                    batch_no: row.batch_no ?? '',
                    sprefcode: row.sprefcode,
                }),
            });

            if (!response.ok && response.status !== 202) {
                throw new Error('Failed to sync row.');
            }

            const payload = await response.json();

            if (!isExternalQueryPollPayload(payload)) {
                throw new Error('Unexpected sync response.');
            }

            const settled =
                payload.status === 'completed'
                    ? payload
                    : await waitForExternalQuery(payload.id);

            const data = settled.result as SyncRowResponse;
            setSyncModalResult(data);

            if (data.removed) {
                setTableRows((current) =>
                    current.filter((item) => reportRowKey(item) !== key),
                );
                return;
            }

            setTableRows((current) =>
                current.map((item) =>
                    reportRowKey(item) === key
                        ? {
                              ...item,
                              stock_point_name:
                                  data.stock_point_name ??
                                  item.stock_point_name,
                              zwing_qty: data.zwing_qty,
                              erp_qty: data.erp_qty,
                              match_status:
                                  data.match_status ?? item.match_status,
                          }
                        : item,
                ),
            );
            setSyncModalRow((current) =>
                current && reportRowKey(current) === key
                    ? {
                          ...current,
                          stock_point_name:
                              data.stock_point_name ?? current.stock_point_name,
                          zwing_qty: data.zwing_qty,
                          erp_qty: data.erp_qty,
                          match_status:
                              data.match_status ?? current.match_status,
                      }
                    : current,
            );
        } catch {
            setSyncModalError('Could not sync this row from connections.');
        } finally {
            setSyncModalLoading(false);
            setSyncingKey(null);
        }
    }

    function applyStatusFilter(value: StatusFilter) {
        navigateWithFilters({ filter: value });
    }

    function applyAdvancedFilters() {
        navigateWithFilters();
    }

    function clearFilters() {
        skipIcodeDebounceRef.current = true;
        setIcodeQuery('');
        setSiteCode(ANY_SITE_CODE);
        setStockPoint(ANY_STOCK_POINT);
        setDifference('all');
        router.get(
            report.url(session.id),
            {
                filter: 'all',
                icode_query: '',
                site_code: '',
                stock_point: '',
                difference: 'all',
                page: 1,
            },
            REPORT_FILTER_VISIT,
        );
    }

    function removeFilterChip(
        key:
            | 'filter'
            | 'icode_query'
            | 'site_code'
            | 'stock_point'
            | 'difference',
    ) {
        if (key === 'filter') {
            applyStatusFilter('all');
            return;
        }

        if (key === 'icode_query') {
            skipIcodeDebounceRef.current = true;
            setIcodeQuery('');
            navigateWithFilters({ icode_query: '' });
            return;
        }

        if (key === 'site_code') {
            setSiteCode(ANY_SITE_CODE);
            navigateWithFilters({ site_code: '' });
            return;
        }

        if (key === 'stock_point') {
            setStockPoint(ANY_STOCK_POINT);
            navigateWithFilters({ stock_point: '' });
            return;
        }

        setDifference('all');
        navigateWithFilters({ difference: 'all' });
    }

    const isFiltered =
        filter !== 'all' ||
        initialFilters.icode_query !== '' ||
        initialFilters.site_code !== '' ||
        initialFilters.stock_point !== '' ||
        initialFilters.difference !== 'all';

    const activeChipCount = [
        filter !== 'all',
        initialFilters.icode_query !== '',
        initialFilters.site_code !== '',
        initialFilters.stock_point !== '',
        initialFilters.difference !== 'all',
    ].filter(Boolean).length;

    function goToPage(page: number) {
        router.get(
            report.url(session.id),
            buildQueryParams(page),
            REPORT_FILTER_VISIT,
        );
    }

    const selectedDiff =
        selectedRow &&
        selectedRow.zwing_qty !== null &&
        selectedRow.erp_qty !== null
            ? selectedRow.zwing_qty - selectedRow.erp_qty
            : null;

    const activeZwingLogs =
        logDetailsData === null
            ? []
            : logModalTab === 'matched'
              ? logDetailsData.matched.zwing
              : logDetailsData.mismatch.zwing;

    const activeErpLogs =
        logDetailsData === null
            ? []
            : logModalTab === 'matched'
              ? logDetailsData.matched.erp
              : logDetailsData.mismatch.erp;

    const matchedLogCount = logDetailsData
        ? logDetailsData.matched.zwing.length +
          logDetailsData.matched.erp.length
        : 0;

    const mismatchLogCount = logDetailsData
        ? logDetailsData.mismatch.zwing.length +
          logDetailsData.mismatch.erp.length
        : 0;

    const statusLabel =
        statusFilterCards.find((card) => card.value === filter)?.label ?? 'All';

    const differenceLabel =
        differenceFilters.find(
            (option) => option.value === initialFilters.difference,
        )?.label ?? 'All differences';

    return (
        <>
            <Head title={`Report — ${session.name}`} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link href={show.url(session.id)}>
                            <Button
                                variant="outline"
                                size="icon"
                                className="shrink-0"
                            >
                                <ArrowLeft className="size-4" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-xl font-semibold tracking-tight">
                                Comparison report
                                <span className="ml-2 font-mono text-base text-muted-foreground">
                                    #{session.id}
                                </span>
                            </h1>
                            <p className="mt-0.5 text-sm text-muted-foreground">
                                {session.name} · Vendor ID {session.v_id}
                            </p>
                        </div>
                    </div>
                    <a
                        href={exportMethod.url(session.id, {
                            query: exportQuery,
                        })}
                        download
                    >
                        <Button variant="outline" size="sm">
                            <Download className="size-4" />
                            Export CSV
                        </Button>
                    </a>
                </div>

                <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
                    {statusFilterCards.map((card) => {
                        const isActive = filter === card.value;

                        return (
                            <button
                                key={card.value}
                                type="button"
                                onClick={() => applyStatusFilter(card.value)}
                                className={cn(
                                    'rounded-xl border border-sidebar-border/70 p-4 text-left transition-all hover:bg-muted/40 dark:border-sidebar-border',
                                    isActive && card.active,
                                )}
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <p className="text-xs font-medium text-muted-foreground">
                                        {card.label}
                                    </p>
                                    {isActive && (
                                        <Badge
                                            variant="secondary"
                                            className="h-5 px-1.5 text-[10px]"
                                        >
                                            Active
                                        </Badge>
                                    )}
                                </div>
                                <p
                                    className={cn(
                                        'mt-1 text-2xl font-bold tabular-nums',
                                        card.accent,
                                    )}
                                >
                                    {summary[card.summaryKey].toLocaleString()}
                                </p>
                            </button>
                        );
                    })}
                </div>

                <div className="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                        <div className="flex items-center gap-2">
                            <div className="flex size-8 items-center justify-center rounded-lg bg-muted">
                                <Filter className="size-4 text-muted-foreground" />
                            </div>
                            <div>
                                <p className="text-sm font-medium">Filters</p>
                                <p className="text-xs text-muted-foreground">
                                    {activeChipCount > 0
                                        ? `${activeChipCount} active · ${pagination.total.toLocaleString()} matching rows`
                                        : `${pagination.total.toLocaleString()} rows in view`}
                                </p>
                            </div>
                        </div>
                        {isFiltered && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={clearFilters}
                                className="gap-1 text-muted-foreground"
                            >
                                <X className="size-3.5" />
                                Clear all
                            </Button>
                        )}
                    </div>

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            applyAdvancedFilters();
                        }}
                        className="grid grid-cols-1 gap-4 p-4 md:grid-cols-2 xl:grid-cols-5"
                    >
                        <div className="space-y-1.5 xl:col-span-2">
                            <Label htmlFor="icode-query">Icode</Label>
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="icode-query"
                                    className="pl-8"
                                    placeholder="Search icode…"
                                    value={icodeQuery}
                                    onChange={(e) =>
                                        setIcodeQuery(e.target.value)
                                    }
                                />
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="site-code">Site code</Label>
                            <Select
                                value={siteCode}
                                onValueChange={(value) => {
                                    setSiteCode(value);
                                    navigateWithFilters({
                                        site_code:
                                            value === ANY_SITE_CODE
                                                ? ''
                                                : value,
                                        icode_query: icodeQuery.trim(),
                                    });
                                }}
                            >
                                <SelectTrigger
                                    id="site-code"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Any site code" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ANY_SITE_CODE}>
                                        Any site code
                                    </SelectItem>
                                    {siteCodeOptions.map((option) => (
                                        <SelectItem key={option} value={option}>
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="stock-point">Stock point</Label>
                            <Select
                                value={stockPoint}
                                onValueChange={(value) => {
                                    setStockPoint(value);
                                    navigateWithFilters({
                                        stock_point:
                                            value === ANY_STOCK_POINT
                                                ? ''
                                                : value,
                                        icode_query: icodeQuery.trim(),
                                    });
                                }}
                            >
                                <SelectTrigger
                                    id="stock-point"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Any stock point" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ANY_STOCK_POINT}>
                                        Any stock point
                                    </SelectItem>
                                    {stockPointOptions.map((option) => (
                                        <SelectItem key={option} value={option}>
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="difference">Qty difference</Label>
                            <Select
                                value={difference}
                                onValueChange={(value: DifferenceFilter) => {
                                    setDifference(value);
                                    navigateWithFilters({
                                        difference: value,
                                        icode_query: icodeQuery.trim(),
                                    });
                                }}
                            >
                                <SelectTrigger
                                    id="difference"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {differenceFilters.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex items-end md:col-span-2 xl:col-span-5">
                            <Button type="submit" size="sm" className="gap-1.5">
                                <Search className="size-3.5" />
                                Apply search
                            </Button>
                        </div>
                    </form>

                    {isFiltered && (
                        <div className="flex flex-wrap gap-2 border-t px-4 py-3">
                            {filter !== 'all' && (
                                <FilterChip
                                    label="Status"
                                    value={statusLabel}
                                    onRemove={() => removeFilterChip('filter')}
                                />
                            )}
                            {initialFilters.icode_query !== '' && (
                                <FilterChip
                                    label="Icode"
                                    value={initialFilters.icode_query}
                                    onRemove={() =>
                                        removeFilterChip('icode_query')
                                    }
                                />
                            )}
                            {initialFilters.site_code !== '' && (
                                <FilterChip
                                    label="Site"
                                    value={initialFilters.site_code}
                                    onRemove={() =>
                                        removeFilterChip('site_code')
                                    }
                                />
                            )}
                            {initialFilters.stock_point !== '' && (
                                <FilterChip
                                    label="Stock point"
                                    value={initialFilters.stock_point}
                                    onRemove={() =>
                                        removeFilterChip('stock_point')
                                    }
                                />
                            )}
                            {initialFilters.difference !== 'all' && (
                                <FilterChip
                                    label="Difference"
                                    value={differenceLabel}
                                    onRemove={() =>
                                        removeFilterChip('difference')
                                    }
                                />
                            )}
                        </div>
                    )}
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
                        <p className="text-sm text-muted-foreground">
                            Showing{' '}
                            <span className="font-medium text-foreground">
                                {rows.length.toLocaleString()}
                            </span>{' '}
                            of{' '}
                            <span className="font-medium text-foreground">
                                {pagination.total.toLocaleString()}
                            </span>{' '}
                            rows
                            {filter !== 'all' && (
                                <span>
                                    {' '}
                                    · status{' '}
                                    <span className="font-medium text-foreground">
                                        {statusLabel}
                                    </span>
                                </span>
                            )}
                        </p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-left">
                                    <th className="px-4 py-3 font-medium">
                                        Site code
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Icode
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Batch no
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Sprefcode
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Stock point
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Zwing qty
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        ERP qty
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Difference
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    {showActions && (
                                        <th className="px-4 py-3 font-medium">
                                            Actions
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {tableRows.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={showActions ? 10 : 9}
                                            className="px-4 py-12 text-center"
                                        >
                                            <p className="text-sm font-medium">
                                                No rows match these filters
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Try clearing a chip or widening
                                                the search.
                                            </p>
                                            {isFiltered && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="mt-4"
                                                    onClick={clearFilters}
                                                >
                                                    Clear all filters
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                )}
                                {tableRows.map((row) => {
                                    const key = reportRowKey(row);
                                    const isSyncing = syncingKey === key;
                                    const diff =
                                        row.zwing_qty !== null &&
                                        row.erp_qty !== null
                                            ? row.zwing_qty - row.erp_qty
                                            : null;

                                    return (
                                        <tr
                                            key={key}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-2.5">
                                                {row.site_code}
                                            </td>
                                            <td className="px-4 py-2.5">
                                                <div className="flex items-center gap-1">
                                                    <span className="font-mono text-xs">
                                                        {row.icode}
                                                    </span>
                                                    <CopyIconButton
                                                        text={row.icode}
                                                        label="Icode"
                                                    />
                                                </div>
                                            </td>
                                            <td className="px-4 py-2.5 text-muted-foreground">
                                                {row.batch_no || '—'}
                                            </td>
                                            <td className="px-4 py-2.5">
                                                {row.sprefcode}
                                            </td>
                                            <td className="px-4 py-2.5">
                                                {row.stock_point_name}
                                            </td>
                                            <td className="px-4 py-2.5 text-right tabular-nums">
                                                {row.zwing_qty !== null
                                                    ? row.zwing_qty.toLocaleString()
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-2.5 text-right tabular-nums">
                                                {row.erp_qty !== null
                                                    ? row.erp_qty.toLocaleString()
                                                    : '—'}
                                            </td>
                                            <td
                                                className={cn(
                                                    'px-4 py-2.5 text-right font-medium tabular-nums',
                                                    diff === null
                                                        ? 'text-muted-foreground'
                                                        : diff === 0
                                                          ? 'text-green-600 dark:text-green-400'
                                                          : 'text-destructive',
                                                )}
                                            >
                                                {diff === null
                                                    ? '—'
                                                    : diff > 0
                                                      ? `+${diff}`
                                                      : diff}
                                            </td>
                                            <td className="px-4 py-2.5">
                                                <Badge
                                                    variant={
                                                        statusConfig[
                                                            row.match_status
                                                        ].variant
                                                    }
                                                    className="text-xs"
                                                >
                                                    {
                                                        statusConfig[
                                                            row.match_status
                                                        ].label
                                                    }
                                                </Badge>
                                            </td>
                                            {showActions && (
                                                <td className="px-4 py-2.5">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        {showSyncActions && (
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                disabled={
                                                                    isSyncing
                                                                }
                                                                onClick={() =>
                                                                    openSyncModal(
                                                                        row,
                                                                    )
                                                                }
                                                            >
                                                                {isSyncing ? (
                                                                    <Loader2 className="size-3.5 animate-spin" />
                                                                ) : (
                                                                    <RefreshCw className="size-3.5" />
                                                                )}
                                                                Sync
                                                            </Button>
                                                        )}
                                                        {showLogActions && (
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    openLogModal(
                                                                        row,
                                                                    )
                                                                }
                                                            >
                                                                <FileText className="size-3.5" />
                                                                View logs
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            )}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {pagination.last_page > 1 && (
                        <div className="flex items-center justify-between border-t px-4 py-3">
                            <p className="text-sm text-muted-foreground">
                                Page {pagination.current_page} of{' '}
                                {pagination.last_page} ·{' '}
                                {pagination.total.toLocaleString()} rows
                            </p>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={pagination.current_page <= 1}
                                    onClick={() =>
                                        goToPage(pagination.current_page - 1)
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
                                        goToPage(pagination.current_page + 1)
                                    }
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <Dialog
                open={logModalOpen}
                onOpenChange={(open) => !open && closeLogModal()}
            >
                <DialogContent className="flex max-h-[90vh] max-w-4xl flex-col gap-4 overflow-hidden sm:max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>Log details</DialogTitle>
                        <DialogDescription asChild>
                            <div className="space-y-1 text-sm text-muted-foreground">
                                {selectedRow && (
                                    <>
                                        <p>
                                            <span className="font-medium text-foreground">
                                                {selectedRow.icode}
                                            </span>{' '}
                                            · {selectedRow.site_code} · batch{' '}
                                            {selectedRow.batch_no || '—'} ·
                                            spref {selectedRow.sprefcode}
                                        </p>
                                        <p>
                                            Stock qty: Zwing{' '}
                                            {selectedRow.zwing_qty?.toLocaleString() ??
                                                '—'}{' '}
                                            / ERP{' '}
                                            {selectedRow.erp_qty?.toLocaleString() ??
                                                '—'}
                                            {selectedDiff !== null && (
                                                <span className="text-destructive">
                                                    {' '}
                                                    (diff{' '}
                                                    {selectedDiff > 0
                                                        ? '+'
                                                        : ''}
                                                    {selectedDiff})
                                                </span>
                                            )}
                                        </p>
                                        <p className="text-xs">
                                            Match logs on doc_no + qty · filter
                                            on icode, site_code, sprefcode
                                            {isConnection
                                                ? ' · live from connections'
                                                : ', batch_no'}
                                        </p>
                                    </>
                                )}
                            </div>
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant={
                                logModalTab === 'matched'
                                    ? 'default'
                                    : 'outline'
                            }
                            onClick={() => setLogModalTab('matched')}
                        >
                            Matched
                            {logDetailsData !== null && (
                                <span className="ml-1.5 text-muted-foreground tabular-nums">
                                    ({matchedLogCount})
                                </span>
                            )}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant={
                                logModalTab === 'mismatch'
                                    ? 'default'
                                    : 'outline'
                            }
                            onClick={() => setLogModalTab('mismatch')}
                        >
                            Mismatch
                            {logDetailsData !== null && (
                                <span className="ml-1.5 text-muted-foreground tabular-nums">
                                    ({mismatchLogCount})
                                </span>
                            )}
                        </Button>
                    </div>

                    {logDetailsLoading && (
                        <div className="flex flex-col items-center justify-center gap-2 py-12 text-muted-foreground">
                            <Loader2 className="size-5 animate-spin" />
                            <p className="text-sm">
                                {isConnection
                                    ? 'Queued on external-query… loading logs…'
                                    : 'Loading log entries…'}
                            </p>
                        </div>
                    )}

                    {logDetailsError && (
                        <p className="py-8 text-center text-sm text-destructive">
                            {logDetailsError}
                        </p>
                    )}

                    {!logDetailsLoading &&
                        !logDetailsError &&
                        logDetailsData && (
                            <div className="grid min-h-0 flex-1 grid-cols-1 gap-4 overflow-y-auto md:grid-cols-2">
                                <LogSideTable
                                    title="Zwing"
                                    rows={activeZwingLogs}
                                    queryMs={logDetailsData.zwing_query_ms}
                                    emptyMessage={
                                        logDetailsData.has_zwing_logs
                                            ? 'No entries in this group.'
                                            : isConnection
                                              ? 'Zwing connection not included in this session.'
                                              : 'No Zwing log file uploaded.'
                                    }
                                />
                                <LogSideTable
                                    title="ERP"
                                    rows={activeErpLogs}
                                    queryMs={logDetailsData.erp_query_ms}
                                    emptyMessage={
                                        logDetailsData.has_erp_logs
                                            ? 'No entries in this group.'
                                            : isConnection
                                              ? 'ERP connection not included in this session.'
                                              : 'No ERP log file uploaded.'
                                    }
                                />
                            </div>
                        )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={syncModalOpen}
                onOpenChange={(open) => !open && closeSyncModal()}
            >
                <DialogContent className="max-w-lg sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Sync row</DialogTitle>
                        <DialogDescription asChild>
                            <div className="space-y-1 text-sm text-muted-foreground">
                                {syncModalRow && (
                                    <>
                                        <p>
                                            <span className="font-medium text-foreground">
                                                {syncModalRow.icode}
                                            </span>{' '}
                                            · {syncModalRow.site_code} · batch{' '}
                                            {syncModalRow.batch_no || '—'} ·
                                            spref {syncModalRow.sprefcode}
                                        </p>
                                        <p className="text-xs">
                                            Fetch latest stock qty for this key
                                            only from live connections.
                                        </p>
                                    </>
                                )}
                            </div>
                        </DialogDescription>
                    </DialogHeader>

                    {syncModalLoading && (
                        <div className="flex flex-col items-center justify-center gap-3 py-10 text-muted-foreground">
                            <Loader2 className="size-6 animate-spin" />
                            <p className="text-sm">
                                Queued on external-query… fetching Zwing / ERP…
                            </p>
                        </div>
                    )}

                    {syncModalError && (
                        <p className="py-6 text-center text-sm text-destructive">
                            {syncModalError}
                        </p>
                    )}

                    {!syncModalLoading &&
                        !syncModalError &&
                        syncModalResult && (
                            <div className="space-y-4">
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="rounded-lg border px-3 py-3">
                                        <p className="text-xs text-muted-foreground">
                                            Zwing connection
                                        </p>
                                        <p className="mt-1 text-sm font-medium tabular-nums">
                                            {formatLogQueryDuration(
                                                syncModalResult.zwing_query_ms,
                                            ) ??
                                                (session.zwing_file_name
                                                    ? '—'
                                                    : 'Not included')}
                                        </p>
                                        {syncModalResult.zwing_query_ms !==
                                            null && (
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                Query time
                                            </p>
                                        )}
                                    </div>
                                    <div className="rounded-lg border px-3 py-3">
                                        <p className="text-xs text-muted-foreground">
                                            ERP connection
                                        </p>
                                        <p className="mt-1 text-sm font-medium tabular-nums">
                                            {formatLogQueryDuration(
                                                syncModalResult.erp_query_ms,
                                            ) ??
                                                (session.erp_file_name
                                                    ? '—'
                                                    : 'Not included')}
                                        </p>
                                        {syncModalResult.erp_query_ms !==
                                            null && (
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                Query time
                                            </p>
                                        )}
                                    </div>
                                </div>

                                {syncModalResult.removed ? (
                                    <p className="rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-3 text-sm">
                                        No stock found on either side. Row
                                        removed from this session.
                                    </p>
                                ) : (
                                    <div className="space-y-3 rounded-lg border px-3 py-3">
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="text-sm font-medium">
                                                Stock qty
                                            </p>
                                            {syncModalResult.match_status && (
                                                <Badge
                                                    variant={
                                                        statusConfig[
                                                            syncModalResult
                                                                .match_status
                                                        ].variant
                                                    }
                                                    className="text-xs"
                                                >
                                                    {
                                                        statusConfig[
                                                            syncModalResult
                                                                .match_status
                                                        ].label
                                                    }
                                                </Badge>
                                            )}
                                        </div>
                                        <div className="grid grid-cols-2 gap-3 text-sm">
                                            <div>
                                                <p className="text-xs text-muted-foreground">
                                                    Zwing
                                                </p>
                                                <p className="mt-1 tabular-nums">
                                                    <span className="text-muted-foreground">
                                                        {syncModalBefore?.zwing_qty?.toLocaleString() ??
                                                            '—'}
                                                    </span>
                                                    <span className="mx-1.5 text-muted-foreground">
                                                        →
                                                    </span>
                                                    <span className="font-medium">
                                                        {syncModalResult.zwing_qty?.toLocaleString() ??
                                                            '—'}
                                                    </span>
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs text-muted-foreground">
                                                    ERP
                                                </p>
                                                <p className="mt-1 tabular-nums">
                                                    <span className="text-muted-foreground">
                                                        {syncModalBefore?.erp_qty?.toLocaleString() ??
                                                            '—'}
                                                    </span>
                                                    <span className="mx-1.5 text-muted-foreground">
                                                        →
                                                    </span>
                                                    <span className="font-medium">
                                                        {syncModalResult.erp_qty?.toLocaleString() ??
                                                            '—'}
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                </DialogContent>
            </Dialog>
        </>
    );
}

StockTransactionReconciliationReport.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Stock reconciliation', href: index.url() },
        { title: 'Session details', href: show.url(0) },
        { title: 'Comparison report', href: report.url(0) },
    ],
};
