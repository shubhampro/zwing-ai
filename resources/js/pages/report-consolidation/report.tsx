import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Copy, Download, Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { exportReport } from '@/actions/App/Http/Controllers/ReportConsolidationController';
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
import { copyToClipboard } from '@/lib/copy-to-clipboard';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index, report, show } from '@/routes/report-consolidation';

type MatchStatus =
    | 'matched'
    | 'amount_mismatch'
    | 'invoice_only'
    | 'mop_only';

type ReportRow = {
    invoice_invoice_no: string | null;
    mop_invoice_no: string | null;
    invoice_no: string;
    store_name: string | null;
    invoice_date: string | null;
    mop_date: string | null;
    invoice_total: number | null;
    mop_total: number | null;
    match_status: MatchStatus;
};

type Summary = {
    total: number;
    matched: number;
    amount_mismatch: number;
    invoice_only: number;
    mop_only: number;
    mismatch: number;
};

type Pagination = {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
};

type DifferenceFilter = 'all' | 'zero' | 'non_zero' | 'missing_side';

type Props = {
    session: { id: number; name: string; v_id: number; status: string };
    summary: Summary;
    rows: ReportRow[];
    pagination: Pagination;
    filter: string;
    filters: {
        invoice_query: string;
        store: string;
        difference: DifferenceFilter;
    };
    stores: string[];
};

const statusConfig: Record<
    MatchStatus,
    {
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
    }
> = {
    matched: { label: 'Matched', variant: 'default' },
    amount_mismatch: { label: 'Amount mismatch', variant: 'destructive' },
    invoice_only: { label: 'Invoice only', variant: 'outline' },
    mop_only: { label: 'MOP only', variant: 'secondary' },
};

const filters: { value: string; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'matched', label: 'Matched' },
    { value: 'amount_mismatch', label: 'Amount mismatch' },
    { value: 'invoice_only', label: 'Invoice only' },
    { value: 'mop_only', label: 'MOP only' },
];

const ALL_STORES = '__all__';

const differenceFilters: { value: DifferenceFilter; label: string }[] = [
    { value: 'all', label: 'All differences' },
    { value: 'zero', label: 'Only exact amount match' },
    { value: 'non_zero', label: 'Only amount difference' },
    { value: 'missing_side', label: 'Only missing on one side' },
];

const INVOICE_HEAD =
    'border-b bg-amber-500/10 px-4 py-2 text-center text-xs font-semibold tracking-wide text-amber-800 uppercase dark:text-amber-300';
const MOP_HEAD =
    'border-b bg-blue-500/10 px-4 py-2 text-center text-xs font-semibold tracking-wide text-blue-800 uppercase dark:text-blue-300';
const INVOICE_CELL = 'bg-amber-500/[0.04]';
const MOP_CELL = 'bg-blue-500/[0.04]';

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
    if (row.invoice_total === null || row.mop_total === null) {
        return null;
    }

    return row.invoice_total - row.mop_total;
}

function formatDiff(row: ReportRow): string {
    const diff = amountDiff(row);

    if (diff === null) {
        return '—';
    }

    return diff > 0 ? `+${diff.toLocaleString()}` : diff.toLocaleString();
}

function valuesDiffer(
    invoice: string | number | null,
    mop: string | number | null,
): boolean {
    if (invoice === null || mop === null) {
        return invoice !== mop;
    }

    return invoice !== mop;
}

function compareCellClass(
    invoice: string | number | null,
    mop: string | number | null,
    side: 'invoice' | 'mop',
): string {
    if (invoice === null && mop === null) {
        return '';
    }

    const missing = side === 'invoice' ? invoice === null : mop === null;
    const differs = valuesDiffer(invoice, mop);

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
            <td className="px-3 py-3 align-middle">
                <CompareValue
                    value={row.invoice_no}
                    copyLabel="Invoice no"
                />
            </td>
            <td className="px-3 py-3 align-middle">
                <CompareValue
                    value={row.store_name}
                    copyLabel="Store"
                    mono={false}
                />
            </td>
            <td
                className={cn(
                    'px-3 py-3 align-middle',
                    INVOICE_CELL,
                    compareCellClass(row.invoice_date, row.mop_date, 'invoice'),
                )}
            >
                {row.invoice_date ?? '—'}
            </td>
            <td
                className={cn(
                    'px-3 py-3 text-right align-middle tabular-nums',
                    INVOICE_CELL,
                    compareCellClass(
                        row.invoice_total,
                        row.mop_total,
                        'invoice',
                    ),
                )}
            >
                {formatAmount(row.invoice_total)}
            </td>
            <td
                className={cn(
                    'px-2 py-3 text-center align-middle text-xs tabular-nums',
                    diff !== null &&
                        diff !== 0 &&
                        'bg-destructive/10 font-medium text-destructive',
                )}
            >
                {formatDiff(row)}
            </td>
            <td
                className={cn(
                    'px-3 py-3 align-middle',
                    MOP_CELL,
                    compareCellClass(row.invoice_date, row.mop_date, 'mop'),
                )}
            >
                {row.mop_date ?? '—'}
            </td>
            <td
                className={cn(
                    'px-3 py-3 text-right align-middle tabular-nums',
                    MOP_CELL,
                    compareCellClass(row.invoice_total, row.mop_total, 'mop'),
                )}
            >
                {formatAmount(row.mop_total)}
            </td>
        </tr>
    );
}

export default function ReportConsolidationReport({
    session,
    summary,
    rows,
    pagination,
    filter,
    filters: initialFilters,
    stores,
}: Props) {
    const [invoiceQuery, setInvoiceQuery] = useState(
        initialFilters.invoice_query,
    );
    const [store, setStore] = useState(initialFilters.store);
    const [difference, setDifference] = useState<DifferenceFilter>(
        initialFilters.difference,
    );

    const activeFilters = useMemo(() => {
        return {
            filter,
            invoice_query: invoiceQuery.trim(),
            store,
            difference,
        };
    }, [difference, filter, invoiceQuery, store]);

    function buildQueryParams(page = 1) {
        return {
            filter: activeFilters.filter,
            invoice_query: activeFilters.invoice_query,
            store: activeFilters.store,
            difference: activeFilters.difference,
            page,
        };
    }

    const exportUrl = useMemo(() => {
        return exportReport.url(session.id, {
            query: {
                ...(activeFilters.filter !== 'all'
                    ? { filter: activeFilters.filter }
                    : {}),
                ...(activeFilters.invoice_query !== ''
                    ? { invoice_query: activeFilters.invoice_query }
                    : {}),
                ...(activeFilters.store !== ''
                    ? { store: activeFilters.store }
                    : {}),
                ...(activeFilters.difference !== 'all'
                    ? { difference: activeFilters.difference }
                    : {}),
            },
        });
    }, [activeFilters, session.id]);

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
        setStore('');
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
        store !== '' ||
        difference !== 'all';

    function goToPage(page: number) {
        router.get(report.url(session.id), buildQueryParams(page), {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title={`Invoice vs MOP — ${session.name}`} />

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
                                Invoice vs MOP
                                <span className="ml-2 font-mono text-base text-muted-foreground">
                                    #{session.id}
                                </span>
                            </h1>
                            <p className="mt-0.5 text-sm text-muted-foreground">
                                {session.name} · Vendor ID {session.v_id}
                            </p>
                        </div>
                    </div>
                    <a href={exportUrl} download>
                        <Button variant="outline" size="sm">
                            <Download className="size-4" />
                            Export CSV
                        </Button>
                    </a>
                </div>

                <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
                    <SummaryCard
                        label="Total"
                        value={summary.total}
                        color="text-foreground"
                    />
                    <SummaryCard
                        label="Matched"
                        value={summary.matched}
                        color="text-green-600 dark:text-green-400"
                    />
                    <SummaryCard
                        label="Amount mismatch"
                        value={summary.amount_mismatch}
                        color="text-destructive"
                    />
                    <SummaryCard
                        label="Invoice only"
                        value={summary.invoice_only}
                        color="text-orange-600 dark:text-orange-400"
                    />
                    <SummaryCard
                        label="MOP only"
                        value={summary.mop_only}
                        color="text-blue-600 dark:text-blue-400"
                    />
                </div>

                <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="invoice-query">Invoice search</Label>
                            <Input
                                id="invoice-query"
                                placeholder="e.g. PMM3001252800002"
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
                            <Label>Store</Label>
                            <Select
                                value={store === '' ? ALL_STORES : store}
                                onValueChange={(value) =>
                                    setStore(value === ALL_STORES ? '' : value)
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="All stores" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_STORES}>
                                        All stores
                                    </SelectItem>
                                    {stores.map((name) => (
                                        <SelectItem key={name} value={name}>
                                            {name}
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

                <div className="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[920px] border-collapse text-sm">
                            <thead>
                                <tr>
                                    <th
                                        rowSpan={2}
                                        className="border-r border-b bg-muted/50 px-3 py-3 text-left align-bottom font-medium"
                                    >
                                        Result
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="border-r border-b bg-muted/50 px-3 py-3 text-left align-bottom font-medium"
                                    >
                                        Invoice no
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="border-r border-b bg-muted/50 px-3 py-3 text-left align-bottom font-medium"
                                    >
                                        Store
                                    </th>
                                    <th colSpan={2} className={INVOICE_HEAD}>
                                        Invoice
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className="border-x border-b bg-muted/50 px-2 py-3 text-center align-middle text-xs font-medium"
                                    >
                                        Δ Amount
                                    </th>
                                    <th colSpan={2} className={MOP_HEAD}>
                                        MOP
                                    </th>
                                </tr>
                                <tr className="border-b bg-muted/30 text-left text-xs text-muted-foreground">
                                    <th
                                        className={cn(
                                            'px-3 py-2 font-medium',
                                            INVOICE_CELL,
                                        )}
                                    >
                                        Date
                                    </th>
                                    <th
                                        className={cn(
                                            'px-3 py-2 text-right font-medium',
                                            INVOICE_CELL,
                                        )}
                                    >
                                        Amount
                                    </th>
                                    <th
                                        className={cn(
                                            'px-3 py-2 font-medium',
                                            MOP_CELL,
                                        )}
                                    >
                                        Date
                                    </th>
                                    <th
                                        className={cn(
                                            'px-3 py-2 text-right font-medium',
                                            MOP_CELL,
                                        )}
                                    >
                                        Amount
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
                                            No rows found for the selected
                                            filter.
                                        </td>
                                    </tr>
                                )}
                                {rows.map((row, i) => (
                                    <ComparisonRow
                                        key={`${row.invoice_no}-${i}`}
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

ReportConsolidationReport.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Report consolidation', href: index.url() },
        { title: 'Comparison report', href: report.url(0) },
    ],
};
