import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Copy, Download, Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';
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
import { cn } from '@/lib/utils';
import { copyToClipboard } from '@/lib/copy-to-clipboard';
import { dashboard } from '@/routes';
import { index, report, show } from '@/routes/invoice-reconciliation';

type MatchStatus =
    | 'matched'
    | 'amount_mismatch'
    | 'status_mismatch'
    | 'mop_ref_mismatch'
    | 'invoice_not_in_erp'
    | 'invoice_not_in_zwing';

type ReportRow = {
    zwing_invoice_id: string | null;
    erp_invoice_id: string | null;
    invoice_id: string;
    zwing_ref_id: string | null;
    erp_ref_id: string | null;
    ref_id: string;
    zwing_total_amount: number | null;
    erp_total_amount: number | null;
    zwing_status: string | null;
    erp_status: string | null;
    match_status: MatchStatus;
};

type Summary = {
    total: number;
    matched: number;
    amount_mismatch: number;
    status_mismatch: number;
    zwing_only: number;
    erp_only: number;
    mop_ref_mismatch: number;
    mismatch: number;
};

type Pagination = {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
};

type DifferenceFilter = 'all' | 'zero' | 'non_zero' | 'missing_side';

type StatusOptions = {
    zwing: string[];
    erp: string[];
};

type Props = {
    session: { id: number; name: string; v_id: number; status: string };
    summary: Summary;
    rows: ReportRow[];
    pagination: Pagination;
    filter: string;
    filters: {
        invoice_query: string;
        zwing_status: string;
        erp_status: string;
        difference: DifferenceFilter;
    };
    statusOptions: StatusOptions;
};

const statusConfig: Record<
    MatchStatus,
    {
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
    }
> = {
    matched: { label: 'In both', variant: 'default' },
    mop_ref_mismatch: { label: 'Mop Ref mismatch', variant: 'destructive' },
    amount_mismatch: { label: 'Amount mismatch', variant: 'destructive' },
    status_mismatch: { label: 'Status mismatch', variant: 'destructive' },
    invoice_not_in_erp: { label: 'Not found in ERP', variant: 'outline' },
    invoice_not_in_zwing: { label: 'Not found in Zwing', variant: 'secondary' },
};

const filters: { value: string; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'matched', label: 'In both' },
    { value: 'mismatch', label: 'All mismatches' },
    { value: 'mop_ref_mismatch', label: 'Mop Ref mismatch' },
    { value: 'amount_mismatch', label: 'Amount mismatch' },
    { value: 'status_mismatch', label: 'Status mismatch' },
    { value: 'zwing_only', label: 'Not in ERP' },
    { value: 'erp_only', label: 'Not in Zwing' },
];

const differenceFilters: { value: DifferenceFilter; label: string }[] = [
    { value: 'all', label: 'All differences' },
    { value: 'zero', label: 'Only exact amount match' },
    { value: 'non_zero', label: 'Only amount difference' },
    { value: 'missing_side', label: 'Only missing on one side' },
];

const ANY_STATUS = '__any__';

const ZWING_HEAD =
    'border-b bg-amber-500/10 px-4 py-2 text-center text-xs font-semibold tracking-wide text-amber-800 uppercase dark:text-amber-300';
const ERP_HEAD =
    'border-b bg-blue-500/10 px-4 py-2 text-center text-xs font-semibold tracking-wide text-blue-800 uppercase dark:text-blue-300';
const ZWING_CELL = 'bg-amber-500/[0.04]';
const ERP_CELL = 'bg-blue-500/[0.04]';

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

function formatAmount(value: number | null): string {
    return value !== null ? value.toLocaleString() : '—';
}

function amountDiff(row: ReportRow): number | null {
    if (row.zwing_total_amount === null || row.erp_total_amount === null) {
        return null;
    }

    return row.zwing_total_amount - row.erp_total_amount;
}

function formatDiff(row: ReportRow): string {
    const diff = amountDiff(row);

    if (diff === null) {
        return '—';
    }

    return diff > 0 ? `+${diff.toLocaleString()}` : diff.toLocaleString();
}

function valuesDiffer(
    zwing: string | number | null,
    erp: string | number | null,
): boolean {
    if (zwing === null || erp === null) {
        return zwing !== erp;
    }

    return zwing !== erp;
}

function compareCellClass(
    zwing: string | number | null,
    erp: string | number | null,
    side: 'zwing' | 'erp',
): string {
    if (zwing === null && erp === null) {
        return '';
    }

    const missing = side === 'zwing' ? zwing === null : erp === null;
    const differs = valuesDiffer(zwing, erp);

    if (missing) {
        return 'bg-orange-500/15 ring-1 ring-inset ring-orange-500/30';
    }

    if (differs) {
        return 'bg-destructive/10 ring-1 ring-inset ring-destructive/25';
    }

    return '';
}

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

function CompareValue({
    value,
    copyLabel,
    align = 'left',
    mono = true,
}: {
    value: string | null;
    copyLabel: string;
    align?: 'left' | 'right';
    mono?: boolean;
}) {
    if (value === null || value === '') {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div
            className={cn(
                'flex items-center gap-1',
                align === 'right' && 'justify-end',
            )}
        >
            <span className={cn(mono && 'font-mono text-xs')}>{value}</span>
            <CopyIconButton text={value} label={copyLabel} />
        </div>
    );
}

function ComparisonRow({ row }: { row: ReportRow }) {
    const diff = amountDiff(row);
    const { label, variant } = statusConfig[row.match_status];

    return (
        <tr className="divide-x divide-sidebar-border/50 hover:bg-muted/20">
            <td className="px-3 py-3 align-middle">
                <Badge variant={variant} className="text-xs whitespace-nowrap">
                    {label}
                </Badge>
            </td>

            <td
                className={cn(
                    'px-3 py-3 align-middle',
                    ZWING_CELL,
                    compareCellClass(row.zwing_ref_id, row.erp_ref_id, 'zwing'),
                )}
            >
                <CompareValue
                    value={row.zwing_ref_id}
                    copyLabel="Zwing Mop Ref id"
                />
            </td>
            <td
                className={cn(
                    'px-3 py-3 align-middle',
                    ZWING_CELL,
                    compareCellClass(
                        row.zwing_invoice_id,
                        row.erp_invoice_id,
                        'zwing',
                    ),
                )}
            >
                <CompareValue
                    value={row.zwing_invoice_id}
                    copyLabel="Zwing invoice ID"
                />
            </td>
            <td
                className={cn(
                    'px-3 py-3 text-right align-middle tabular-nums',
                    ZWING_CELL,
                    compareCellClass(
                        row.zwing_total_amount,
                        row.erp_total_amount,
                        'zwing',
                    ),
                )}
            >
                {formatAmount(row.zwing_total_amount)}
            </td>
            <td
                className={cn(
                    'px-3 py-3 align-middle',
                    ZWING_CELL,
                    compareCellClass(row.zwing_status, row.erp_status, 'zwing'),
                )}
            >
                {row.zwing_status ?? '—'}
            </td>

            <td
                className={cn(
                    'px-2 py-3 text-center align-middle text-xs font-semibold tabular-nums',
                    diff === null
                        ? 'text-muted-foreground'
                        : diff === 0
                          ? 'text-green-600 dark:text-green-400'
                          : 'bg-destructive/5 text-destructive',
                )}
            >
                {formatDiff(row)}
            </td>

            <td
                className={cn(
                    'px-3 py-3 align-middle',
                    ERP_CELL,
                    compareCellClass(row.zwing_ref_id, row.erp_ref_id, 'erp'),
                )}
            >
                <CompareValue
                    value={row.erp_ref_id}
                    copyLabel="ERP Mop Ref id"
                />
            </td>
            <td
                className={cn(
                    'px-3 py-3 align-middle',
                    ERP_CELL,
                    compareCellClass(
                        row.zwing_invoice_id,
                        row.erp_invoice_id,
                        'erp',
                    ),
                )}
            >
                <CompareValue
                    value={row.erp_invoice_id}
                    copyLabel="ERP invoice ID"
                />
            </td>
            <td
                className={cn(
                    'px-3 py-3 text-right align-middle tabular-nums',
                    ERP_CELL,
                    compareCellClass(
                        row.zwing_total_amount,
                        row.erp_total_amount,
                        'erp',
                    ),
                )}
            >
                {formatAmount(row.erp_total_amount)}
            </td>
            <td
                className={cn(
                    'px-3 py-3 align-middle',
                    ERP_CELL,
                    compareCellClass(row.zwing_status, row.erp_status, 'erp'),
                )}
            >
                {row.erp_status ?? '—'}
            </td>
        </tr>
    );
}

export default function InvoiceReconciliationReport({
    session,
    summary,
    rows,
    pagination,
    filter,
    filters: initialFilters,
    statusOptions,
}: Props) {
    const [invoiceQuery, setInvoiceQuery] = useState(
        initialFilters.invoice_query,
    );
    const [zwingStatus, setZwingStatus] = useState(
        initialFilters.zwing_status || ANY_STATUS,
    );
    const [erpStatus, setErpStatus] = useState(
        initialFilters.erp_status || ANY_STATUS,
    );
    const [difference, setDifference] = useState<DifferenceFilter>(
        initialFilters.difference,
    );

    const activeFilters = useMemo(() => {
        return {
            filter,
            invoice_query: invoiceQuery.trim(),
            zwing_status: zwingStatus === ANY_STATUS ? '' : zwingStatus,
            erp_status: erpStatus === ANY_STATUS ? '' : erpStatus,
            difference,
        };
    }, [difference, erpStatus, filter, invoiceQuery, zwingStatus]);

    function buildQueryParams(page = 1) {
        return {
            filter: activeFilters.filter,
            invoice_query: activeFilters.invoice_query,
            zwing_status: activeFilters.zwing_status,
            erp_status: activeFilters.erp_status,
            difference: activeFilters.difference,
            page,
        };
    }

    const exportQueryString = useMemo(() => {
        const params = new URLSearchParams();
        if (activeFilters.filter !== 'all') {
            params.set('filter', activeFilters.filter);
        }
        if (activeFilters.invoice_query !== '') {
            params.set('invoice_query', activeFilters.invoice_query);
        }
        if (activeFilters.zwing_status !== '') {
            params.set('zwing_status', activeFilters.zwing_status);
        }
        if (activeFilters.erp_status !== '') {
            params.set('erp_status', activeFilters.erp_status);
        }
        if (activeFilters.difference !== 'all') {
            params.set('difference', activeFilters.difference);
        }
        return params.toString();
    }, [activeFilters]);

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
        router.get(report.url(session.id), buildQueryParams(1), {
            preserveScroll: false,
        });
    }

    function clearFilters() {
        setInvoiceQuery('');
        setZwingStatus(ANY_STATUS);
        setErpStatus(ANY_STATUS);
        setDifference('all');
        router.get(
            report.url(session.id),
            { filter: 'all', page: 1 },
            { preserveScroll: false },
        );
    }

    const isFiltered =
        filter !== 'all' ||
        activeFilters.invoice_query !== '' ||
        activeFilters.zwing_status !== '' ||
        activeFilters.erp_status !== '' ||
        difference !== 'all';

    function goToPage(page: number) {
        router.get(report.url(session.id), buildQueryParams(page), {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title={`Invoice report — ${session.name}`} />

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
                                Zwing vs ERP comparison
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
                        href={`/invoice-reconciliation/${session.id}/report/export${exportQueryString !== '' ? `?${exportQueryString}` : ''}`}
                        download
                    >
                        <Button variant="outline" size="sm">
                            <Download className="size-4" />
                            Export CSV
                        </Button>
                    </a>
                </div>

                <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
                    <SummaryCard
                        label="Total"
                        value={summary.total}
                        color="text-foreground"
                    />
                    <SummaryCard
                        label="In both"
                        value={summary.matched}
                        color="text-green-600 dark:text-green-400"
                    />
                    <SummaryCard
                        label="Mismatch"
                        value={summary.mismatch}
                        color="text-destructive"
                    />
                    <SummaryCard
                        label="Mop Ref mismatch"
                        value={summary.mop_ref_mismatch}
                        color="text-amber-600 dark:text-amber-400"
                    />
                    <SummaryCard
                        label="Not in ERP"
                        value={summary.zwing_only}
                        color="text-orange-600 dark:text-orange-400"
                    />
                    <SummaryCard
                        label="Not in Zwing"
                        value={summary.erp_only}
                        color="text-blue-600 dark:text-blue-400"
                    />
                </div>

                <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-5">
                        <div className="space-y-1.5">
                            <Label htmlFor="invoice-query">
                                Invoice / Mop Ref id search
                            </Label>
                            <Input
                                id="invoice-query"
                                placeholder="e.g. PMM3001252800002 or 22"
                                value={invoiceQuery}
                                onChange={(e) =>
                                    setInvoiceQuery(e.target.value)
                                }
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        applyAdvancedFilters();
                                    }
                                }}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Zwing status</Label>
                            <Select
                                value={zwingStatus}
                                onValueChange={setZwingStatus}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Any Zwing status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ANY_STATUS}>
                                        Any Zwing status
                                    </SelectItem>
                                    {statusOptions.zwing.map((status) => (
                                        <SelectItem
                                            key={`zwing-${status}`}
                                            value={status}
                                        >
                                            {status}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label>ERP status</Label>
                            <Select
                                value={erpStatus}
                                onValueChange={setErpStatus}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Any ERP status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ANY_STATUS}>
                                        Any ERP status
                                    </SelectItem>
                                    {statusOptions.erp.map((status) => (
                                        <SelectItem
                                            key={`erp-${status}`}
                                            value={status}
                                        >
                                            {status}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label>Amount difference type</Label>
                            <Select
                                value={difference}
                                onValueChange={(value: DifferenceFilter) =>
                                    setDifference(value)
                                }
                            >
                                <SelectTrigger className="w-full">
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
                        <div className="flex items-end gap-2">
                            <Button
                                size="sm"
                                onClick={applyAdvancedFilters}
                                className="flex-1"
                            >
                                <Search className="size-4" />
                                Apply
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={clearFilters}
                                title="Clear filters"
                            >
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

                <div className="flex flex-wrap items-center gap-4 rounded-md border border-dashed px-4 py-2 text-xs text-muted-foreground">
                    <span className="flex items-center gap-2">
                        <span className="size-3 rounded-sm bg-amber-500/20 ring-1 ring-amber-500/40" />
                        Zwing side
                    </span>
                    <span className="flex items-center gap-2">
                        <span className="size-3 rounded-sm bg-blue-500/20 ring-1 ring-blue-500/40" />
                        ERP side
                    </span>
                    <span className="flex items-center gap-2">
                        <span className="size-3 rounded-sm bg-orange-500/20 ring-1 ring-orange-500/40" />
                        Missing on one side
                    </span>
                    <span className="flex items-center gap-2">
                        <span className="size-3 rounded-sm bg-destructive/20 ring-1 ring-destructive/30" />
                        Value differs
                    </span>
                </div>

                <div className="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[960px] border-collapse text-sm">
                            <thead>
                                <tr>
                                    <th
                                        rowSpan={2}
                                        className="border-r border-b bg-muted/50 px-3 py-3 text-left align-bottom font-medium"
                                    >
                                        Result
                                    </th>
                                    <th colSpan={4} className={ZWING_HEAD}>
                                        Zwing
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="border-x border-b bg-muted/50 px-2 py-3 text-center align-middle text-xs font-medium"
                                    >
                                        Δ Amount
                                    </th>
                                    <th colSpan={4} className={ERP_HEAD}>
                                        ERP
                                    </th>
                                </tr>
                                <tr className="border-b bg-muted/30 text-left text-xs text-muted-foreground">
                                    <th
                                        className={cn(
                                            'px-3 py-2 font-medium',
                                            ZWING_CELL,
                                        )}
                                    >
                                        Mop Ref id
                                    </th>
                                    <th
                                        className={cn(
                                            'px-3 py-2 font-medium',
                                            ZWING_CELL,
                                        )}
                                    >
                                        Invoice
                                    </th>
                                    <th
                                        className={cn(
                                            'px-3 py-2 text-right font-medium',
                                            ZWING_CELL,
                                        )}
                                    >
                                        Amount
                                    </th>
                                    <th
                                        className={cn(
                                            'px-3 py-2 font-medium',
                                            ZWING_CELL,
                                        )}
                                    >
                                        Status
                                    </th>
                                    <th
                                        className={cn(
                                            'px-3 py-2 font-medium',
                                            ERP_CELL,
                                        )}
                                    >
                                        Mop Ref id
                                    </th>
                                    <th
                                        className={cn(
                                            'px-3 py-2 font-medium',
                                            ERP_CELL,
                                        )}
                                    >
                                        Invoice
                                    </th>
                                    <th
                                        className={cn(
                                            'px-3 py-2 text-right font-medium',
                                            ERP_CELL,
                                        )}
                                    >
                                        Amount
                                    </th>
                                    <th
                                        className={cn(
                                            'px-3 py-2 font-medium',
                                            ERP_CELL,
                                        )}
                                    >
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={10}
                                            className="px-4 py-10 text-center text-sm text-muted-foreground"
                                        >
                                            No rows found for the selected
                                            filter.
                                        </td>
                                    </tr>
                                )}
                                {rows.map((row, i) => (
                                    <ComparisonRow
                                        key={`${row.ref_id}-${row.invoice_id}-${i}`}
                                        row={row}
                                    />
                                ))}
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
        </>
    );
}

InvoiceReconciliationReport.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Invoice reconciliation', href: index.url() },
        { title: 'Comparison report', href: report.url(0) },
    ],
};
