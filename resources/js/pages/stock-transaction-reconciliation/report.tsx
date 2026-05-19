import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Download, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
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

type Props = {
    session: { id: number; name: string; v_id: number; status: string };
    summary: Summary;
    rows: ReportRow[];
    pagination: Pagination;
    filter: string;
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

export default function StockTransactionReconciliationReport({
    session,
    summary,
    rows,
    pagination,
    filter,
}: Props) {
    function applyFilter(value: string) {
        router.get(
            report.url(session.id),
            { filter: value, page: 1 },
            { preserveScroll: false },
        );
    }

    function clearFilters() {
        router.get(report.url(session.id), {}, { preserveScroll: false });
    }

    const isFiltered = filter !== 'all';

    function goToPage(page: number) {
        router.get(
            report.url(session.id),
            { filter, page },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={`Report — ${session.name}`} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                {/* Header */}
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
                        href={`/stock-transaction-reconciliation/${session.id}/report/export${filter !== 'all' ? `?filter=${filter}` : ''}`}
                        download
                    >
                        <Button variant="outline" size="sm">
                            <Download className="size-4" />
                            Export CSV
                        </Button>
                    </a>
                </div>

                {/* Summary cards */}
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

                {/* Filter tabs */}
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

                {/* Table */}
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
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={9}
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
                                            <td className="px-4 py-2.5 font-mono text-xs">
                                                {row.icode}
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
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
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

StockTransactionReconciliationReport.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Stock reconciliation', href: index.url() },
        { title: 'Session details', href: show.url(0) },
        { title: 'Comparison report', href: report.url(0) },
    ],
};
