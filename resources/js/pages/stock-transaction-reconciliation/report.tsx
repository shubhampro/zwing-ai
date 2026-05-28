import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Copy, Download, FileText, Loader2, Search, X } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
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
import { dashboard } from '@/routes';
import { logDetails } from '@/routes/stock-transaction-reconciliation/report';
import { index, report, show } from '@/routes/stock-transaction-reconciliation';

type MatchStatus = 'matched' | 'qty_mismatch' | 'zwing_only' | 'erp_only';

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
    matched: { zwing: LogEntry[]; erp: LogEntry[] };
    mismatch: { zwing: LogEntry[]; erp: LogEntry[] };
};

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
        zwing_log_file_name: string | null;
        erp_log_file_name: string | null;
    };
    summary: Summary;
    rows: ReportRow[];
    pagination: Pagination;
    filter: string;
    filters: {
        icode_query: string;
        stock_point: string;
        difference: DifferenceFilter;
    };
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

const filters: { value: string; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'matched', label: 'Matched' },
    { value: 'qty_mismatch', label: 'Qty mismatch' },
    { value: 'zwing_only', label: 'Zwing only' },
    { value: 'erp_only', label: 'ERP only' },
];

const differenceFilters: { value: DifferenceFilter; label: string }[] = [
    { value: 'all', label: 'All differences' },
    { value: 'zero', label: 'Only exact qty match' },
    { value: 'non_zero', label: 'Only qty difference' },
    { value: 'missing_side', label: 'Only missing on one side' },
];

const ANY_STOCK_POINT = '__any__';

function SummaryCard({
    label,
    value,
    color,
}: {
    label: string;
    value: number;
    color: string;
}) {
    return (
        <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className={`mt-1 text-2xl font-bold ${color}`}>
                {value.toLocaleString()}
            </p>
        </div>
    );
}

async function copyText(text: string, label: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(text);
        toast.success(`${label} copied`);
    } catch {
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
}: {
    title: string;
    rows: LogEntry[];
    emptyMessage: string;
}) {
    return (
        <div className="flex flex-col gap-2">
            <p className="text-sm font-medium">{title}</p>
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

export default function StockTransactionReconciliationReport({
    session,
    summary,
    rows,
    pagination,
    filter,
    filters: initialFilters,
    stockPointOptions,
}: Props) {
    const [icodeQuery, setIcodeQuery] = useState(initialFilters.icode_query);
    const [stockPoint, setStockPoint] = useState(initialFilters.stock_point || ANY_STOCK_POINT);
    const [difference, setDifference] = useState<DifferenceFilter>(initialFilters.difference);

    const activeFilters = useMemo(() => {
        return {
            filter,
            icode_query: icodeQuery.trim(),
            stock_point: stockPoint === ANY_STOCK_POINT ? '' : stockPoint,
            difference,
        };
    }, [difference, filter, icodeQuery, stockPoint]);

    function buildQueryParams(page = 1) {
        return {
            filter: activeFilters.filter,
            icode_query: activeFilters.icode_query,
            stock_point: activeFilters.stock_point,
            difference: activeFilters.difference,
            page,
        };
    }

    const exportQueryString = useMemo(() => {
        const params = new URLSearchParams();
        if (activeFilters.filter !== 'all') {
            params.set('filter', activeFilters.filter);
        }
        if (activeFilters.icode_query !== '') {
            params.set('icode_query', activeFilters.icode_query);
        }
        if (activeFilters.stock_point !== '') {
            params.set('stock_point', activeFilters.stock_point);
        }
        if (activeFilters.difference !== 'all') {
            params.set('difference', activeFilters.difference);
        }
        return params.toString();
    }, [activeFilters]);

    const hasAnyLogs =
        session.zwing_log_file_name !== null ||
        session.erp_log_file_name !== null;
    const showLogActions = filter === 'qty_mismatch' && hasAnyLogs;

    const [logModalOpen, setLogModalOpen] = useState(false);
    const [logModalTab, setLogModalTab] = useState<'matched' | 'mismatch'>(
        'matched',
    );
    const [selectedRow, setSelectedRow] = useState<ReportRow | null>(null);
    const [logDetailsData, setLogDetailsData] =
        useState<LogDetailResponse | null>(null);
    const [logDetailsLoading, setLogDetailsLoading] = useState(false);
    const [logDetailsError, setLogDetailsError] = useState<string | null>(null);

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

                if (!response.ok) {
                    throw new Error('Failed to load log details.');
                }

                const data = (await response.json()) as LogDetailResponse;
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

    function applyFilter(value: string) {
        router.get(
            report.url(session.id),
            {
                ...buildQueryParams(1),
                filter: value,
            },
            { preserveScroll: false },
        );
    }

    function applyAdvancedFilters() {
        router.get(report.url(session.id), buildQueryParams(1), { preserveScroll: false });
    }

    function clearFilters() {
        setIcodeQuery('');
        setStockPoint(ANY_STOCK_POINT);
        setDifference('all');
        router.get(report.url(session.id), { filter: 'all', page: 1 }, { preserveScroll: false });
    }

    const isFiltered =
        filter !== 'all' ||
        activeFilters.icode_query !== '' ||
        activeFilters.stock_point !== '' ||
        difference !== 'all';

    function goToPage(page: number) {
        router.get(report.url(session.id), buildQueryParams(page), { preserveScroll: true });
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
                        href={`/stock-transaction-reconciliation/${session.id}/report/export${exportQueryString !== '' ? `?${exportQueryString}` : ''}`}
                        download
                    >
                        <Button variant="outline" size="sm">
                            <Download className="size-4" />
                            Export CSV
                        </Button>
                    </a>
                </div>

                <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
                    <SummaryCard
                        label="Total rows"
                        value={summary.total}
                        color="text-foreground"
                    />
                    <SummaryCard
                        label="Matched"
                        value={summary.matched}
                        color="text-green-600 dark:text-green-400"
                    />
                    <SummaryCard
                        label="Qty mismatch"
                        value={summary.qty_mismatch}
                        color="text-destructive"
                    />
                    <SummaryCard
                        label="Zwing only"
                        value={summary.zwing_only}
                        color="text-amber-600 dark:text-amber-400"
                    />
                    <SummaryCard
                        label="ERP only"
                        value={summary.erp_only}
                        color="text-blue-600 dark:text-blue-400"
                    />
                </div>

                <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="icode-query">Icode search</Label>
                            <Input
                                id="icode-query"
                                placeholder="e.g. ND1884"
                                value={icodeQuery}
                                onChange={(e) => setIcodeQuery(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        applyAdvancedFilters();
                                    }
                                }}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Stock point</Label>
                            <Select value={stockPoint} onValueChange={setStockPoint}>
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Any stock point" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ANY_STOCK_POINT}>Any stock point</SelectItem>
                                    {stockPointOptions.map((option) => (
                                        <SelectItem key={option} value={option}>
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label>Qty difference type</Label>
                            <Select
                                value={difference}
                                onValueChange={(value: DifferenceFilter) => setDifference(value)}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {differenceFilters.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex items-end gap-2">
                            <Button size="sm" onClick={applyAdvancedFilters} className="flex-1">
                                <Search className="size-4" />
                                Apply
                            </Button>
                            <Button variant="outline" size="sm" onClick={clearFilters} title="Clear filters">
                                <X className="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {filters.map((f) => (
                        <Button
                            key={f.value}
                            variant={filter === f.value ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => applyFilter(f.value)}
                        >
                            {f.label}
                        </Button>
                    ))}
                    {isFiltered && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={clearFilters}
                            className="cursor-pointer gap-1 text-muted-foreground"
                        >
                            <X className="size-3.5" />
                            Clear filter
                        </Button>
                    )}
                </div>

                <div className="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
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
                                    {showLogActions && (
                                        <th className="px-4 py-3 font-medium">
                                            Logs
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={showLogActions ? 10 : 9}
                                            className="px-4 py-10 text-center text-sm text-muted-foreground"
                                        >
                                            No rows found for the selected
                                            filter.
                                        </td>
                                    </tr>
                                )}
                                {rows.map((row, i) => {
                                    const diff =
                                        row.zwing_qty !== null &&
                                        row.erp_qty !== null
                                            ? row.zwing_qty - row.erp_qty
                                            : null;

                                    return (
                                        <tr
                                            key={i}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-2.5">
                                                {row.site_code}
                                            </td>
                                            <td className="px-4 py-2.5">
                                                <div className="flex items-center gap-1">
                                                    <span className="font-mono text-xs">{row.icode}</span>
                                                    <CopyIconButton text={row.icode} label="Icode" />
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
                                                className={`px-4 py-2.5 text-right font-medium tabular-nums ${
                                                    diff === null
                                                        ? 'text-muted-foreground'
                                                        : diff === 0
                                                          ? 'text-green-600 dark:text-green-400'
                                                          : 'text-destructive'
                                                }`}
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
                                            {showLogActions && (
                                                <td className="px-4 py-2.5">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            openLogModal(row)
                                                        }
                                                    >
                                                        <FileText className="size-3.5" />
                                                        View logs
                                                    </Button>
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
                                            on icode, site_code, sprefcode,
                                            batch_no
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
                        <div className="flex items-center justify-center gap-2 py-12 text-muted-foreground">
                            <Loader2 className="size-5 animate-spin" />
                            Loading log entries…
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
                                    emptyMessage={
                                        logDetailsData.has_zwing_logs
                                            ? 'No entries in this group.'
                                            : 'No Zwing log file uploaded.'
                                    }
                                />
                                <LogSideTable
                                    title="ERP"
                                    rows={activeErpLogs}
                                    emptyMessage={
                                        logDetailsData.has_erp_logs
                                            ? 'No entries in this group.'
                                            : 'No ERP log file uploaded.'
                                    }
                                />
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
