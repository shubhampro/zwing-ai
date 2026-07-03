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
import { dashboard } from '@/routes';
import { index, report, show } from '@/routes/expense-cash-reconciliation';

type MatchStatus =
    | 'matched'
    | 'amount_mismatch'
    | 'date_mismatch'
    | 'status_mismatch'
    | 'zwing_only'
    | 'erp_only';

type ReportRow = {
    site_id: string;
    doc_no: string;
    zwing_date: string | null;
    erp_date: string | null;
    zwing_amount: number | null;
    erp_amount: number | null;
    zwing_status: string | null;
    erp_status: string | null;
    match_status: MatchStatus;
};

type Summary = {
    total: number;
    matched: number;
    amount_mismatch: number;
    date_mismatch: number;
    status_mismatch: number;
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
        doc_query: string;
        site_query: string;
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
    matched: { label: 'Matched', variant: 'default' },
    amount_mismatch: { label: 'Amount mismatch', variant: 'destructive' },
    date_mismatch: { label: 'Date mismatch', variant: 'destructive' },
    status_mismatch: { label: 'Status mismatch', variant: 'destructive' },
    zwing_only: { label: 'Zwing only (not in ERP)', variant: 'outline' },
    erp_only: { label: 'ERP only', variant: 'secondary' },
};

const filters: { value: string; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'matched', label: 'Matched' },
    { value: 'amount_mismatch', label: 'Amount mismatch' },
    { value: 'date_mismatch', label: 'Date mismatch' },
    { value: 'status_mismatch', label: 'Status mismatch' },
    { value: 'zwing_only', label: 'Zwing only' },
    { value: 'erp_only', label: 'ERP only' },
];

const differenceFilters: { value: DifferenceFilter; label: string }[] = [
    { value: 'all', label: 'All differences' },
    { value: 'zero', label: 'Only exact amount match' },
    { value: 'non_zero', label: 'Only amount difference' },
    { value: 'missing_side', label: 'Only missing on one side' },
];

const ANY_STATUS = '__any__';

function SummaryCard({ label, value, color }: { label: string; value: number; color: string }) {
    return (
        <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className={`mt-1 text-2xl font-bold ${color}`}>
                {value.toLocaleString()}
            </p>
        </div>
    );
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }
    return new Date(value).toLocaleDateString(undefined, {
        dateStyle: 'medium',
    });
}

function formatAmount(value: number | null): string {
    return value !== null ? value.toLocaleString() : '—';
}

function formatDiff(row: ReportRow): string {
    if (row.zwing_amount === null || row.erp_amount === null) {
        return '—';
    }
    const diff = row.zwing_amount - row.erp_amount;
    return diff > 0 ? `+${diff}` : String(diff);
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

export default function ExpenseCashReconciliationReport({
    session,
    summary,
    rows,
    pagination,
    filter,
    filters: initialFilters,
    statusOptions,
}: Props) {
    const [docQuery, setDocQuery] = useState(initialFilters.doc_query);
    const [siteQuery, setSiteQuery] = useState(initialFilters.site_query);
    const [zwingStatus, setZwingStatus] = useState(initialFilters.zwing_status || ANY_STATUS);
    const [erpStatus, setErpStatus] = useState(initialFilters.erp_status || ANY_STATUS);
    const [difference, setDifference] = useState<DifferenceFilter>(initialFilters.difference);

    const activeFilters = useMemo(() => {
        return {
            filter,
            doc_query: docQuery.trim(),
            site_query: siteQuery.trim(),
            zwing_status: zwingStatus === ANY_STATUS ? '' : zwingStatus,
            erp_status: erpStatus === ANY_STATUS ? '' : erpStatus,
            difference,
        };
    }, [difference, docQuery, erpStatus, filter, siteQuery, zwingStatus]);

    function buildQueryParams(page = 1) {
        return {
            filter: activeFilters.filter,
            doc_query: activeFilters.doc_query,
            site_query: activeFilters.site_query,
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
        if (activeFilters.doc_query !== '') {
            params.set('doc_query', activeFilters.doc_query);
        }
        if (activeFilters.site_query !== '') {
            params.set('site_query', activeFilters.site_query);
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
        router.get(report.url(session.id), buildQueryParams(1), { preserveScroll: false });
    }

    function clearFilters() {
        setDocQuery('');
        setSiteQuery('');
        setZwingStatus(ANY_STATUS);
        setErpStatus(ANY_STATUS);
        setDifference('all');
        router.get(report.url(session.id), { filter: 'all', page: 1 }, { preserveScroll: false });
    }

    const isFiltered =
        filter !== 'all' ||
        activeFilters.doc_query !== '' ||
        activeFilters.site_query !== '' ||
        activeFilters.zwing_status !== '' ||
        activeFilters.erp_status !== '' ||
        difference !== 'all';

    function goToPage(page: number) {
        router.get(report.url(session.id), buildQueryParams(page), { preserveScroll: true });
    }

    return (
        <>
            <Head title={`Expense & cash report — ${session.name}`} />

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
                                Expense & cash comparison report
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
                        href={`/expense-cash-reconciliation/${session.id}/report/export${exportQueryString !== '' ? `?${exportQueryString}` : ''}`}
                        download
                    >
                        <Button variant="outline" size="sm">
                            <Download className="size-4" />
                            Export CSV
                        </Button>
                    </a>
                </div>

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-7">
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
                        label="Date mismatch"
                        value={summary.date_mismatch}
                        color="text-destructive"
                    />
                    <SummaryCard
                        label="Status mismatch"
                        value={summary.status_mismatch}
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
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-6">
                        <div className="space-y-1.5">
                            <Label htmlFor="site-query">Site ID search</Label>
                            <Input
                                id="site-query"
                                placeholder="e.g. 101"
                                value={siteQuery}
                                onChange={(e) => setSiteQuery(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        applyAdvancedFilters();
                                    }
                                }}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="doc-query">Doc no search</Label>
                            <Input
                                id="doc-query"
                                placeholder="e.g. EXP-001"
                                value={docQuery}
                                onChange={(e) => setDocQuery(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        applyAdvancedFilters();
                                    }
                                }}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Zwing status</Label>
                            <Select value={zwingStatus} onValueChange={setZwingStatus}>
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Any Zwing status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ANY_STATUS}>Any Zwing status</SelectItem>
                                    {statusOptions.zwing.map((status) => (
                                        <SelectItem key={`zwing-${status}`} value={status}>
                                            {status}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label>ERP status</Label>
                            <Select value={erpStatus} onValueChange={setErpStatus}>
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Any ERP status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ANY_STATUS}>Any ERP status</SelectItem>
                                    {statusOptions.erp.map((status) => (
                                        <SelectItem key={`erp-${status}`} value={status}>
                                            {status}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label>Amount difference type</Label>
                            <Select value={difference} onValueChange={(value: DifferenceFilter) => setDifference(value)}>
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
                                        Site ID
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Doc no
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Zwing date
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        ERP date
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Zwing amount
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        ERP amount
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Difference
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Zwing status
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        ERP status
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Result
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
                                {rows.map((row, i) => {
                                    const diff =
                                        row.zwing_amount !== null &&
                                        row.erp_amount !== null
                                            ? row.zwing_amount - row.erp_amount
                                            : null;

                                    return (
                                        <tr key={i} className="hover:bg-muted/30">
                                            <td className="px-4 py-2.5">
                                                <div className="flex items-center gap-1">
                                                    <span className="font-mono text-xs">{row.site_id}</span>
                                                    <CopyIconButton text={row.site_id} label="Site ID" />
                                                </div>
                                            </td>
                                            <td className="px-4 py-2.5">
                                                <div className="flex items-center gap-1">
                                                    <span className="font-mono text-xs">{row.doc_no}</span>
                                                    <CopyIconButton text={row.doc_no} label="Doc no" />
                                                </div>
                                            </td>
                                            <td className="px-4 py-2.5 text-muted-foreground">
                                                {formatDate(row.zwing_date)}
                                            </td>
                                            <td className="px-4 py-2.5 text-muted-foreground">
                                                {formatDate(row.erp_date)}
                                            </td>
                                            <td className="px-4 py-2.5 text-right tabular-nums">{formatAmount(row.zwing_amount)}</td>
                                            <td className="px-4 py-2.5 text-right tabular-nums">{formatAmount(row.erp_amount)}</td>
                                            <td
                                                className={`px-4 py-2.5 text-right font-medium tabular-nums ${
                                                    diff === null
                                                        ? 'text-muted-foreground'
                                                        : diff === 0
                                                          ? 'text-green-600 dark:text-green-400'
                                                          : 'text-destructive'
                                                }`}
                                            >
                                                {formatDiff(row)}
                                            </td>
                                            <td className="px-4 py-2.5">
                                                {row.zwing_status ?? '—'}
                                            </td>
                                            <td className="px-4 py-2.5">
                                                {row.erp_status ?? '—'}
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
        </>
    );
}

ExpenseCashReconciliationReport.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Expense & cash reconciliation', href: index.url() },
        { title: 'Comparison report', href: report.url(0) },
    ],
};
