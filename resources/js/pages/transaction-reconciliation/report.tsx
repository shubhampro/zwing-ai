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
import { copyToClipboard } from '@/lib/copy-to-clipboard';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
    index,
    report,
    show,
} from '@/routes/transaction-reconciliation';

type MatchStatus =
    | 'matched'
    | 'code_mismatch'
    | 'type_mismatch'
    | 'amount_mismatch'
    | 'date_mismatch'
    | 'status_mismatch'
    | 'packet_not_in_erp'
    | 'packet_not_in_zwing';

type ReportRow = {
    txn_id: string;
    code: string | null;
    site_id: string | null;
    zwing_code: string | null;
    erp_code: string | null;
    zwing_type: string | null;
    erp_type: string | null;
    zwing_status: string | null;
    erp_status: string | null;
    zwing_date: string | null;
    erp_date: string | null;
    zwing_amount: string | number | null;
    erp_amount: string | number | null;
    match_status: MatchStatus;
};

type Summary = {
    total: number;
    matched: number;
    mismatch: number;
    code_mismatch: number;
    type_mismatch: number;
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

type StatusOptions = {
    zwing: string[];
    erp: string[];
};

type Props = {
    session: {
        id: number;
        name: string;
        v_id: number;
        status: string;
        type: string;
        type_label: string;
        uses_cash_columns: boolean;
    };
    summary: Summary;
    rows: ReportRow[];
    pagination: Pagination;
    filter: string;
    filters: {
        code_query: string;
        zwing_status: string;
        erp_status: string;
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
    code_mismatch: { label: 'Code mismatch', variant: 'destructive' },
    type_mismatch: { label: 'Type mismatch', variant: 'destructive' },
    amount_mismatch: { label: 'Amount mismatch', variant: 'destructive' },
    date_mismatch: { label: 'Date mismatch', variant: 'destructive' },
    status_mismatch: { label: 'Status mismatch', variant: 'destructive' },
    packet_not_in_erp: { label: 'Not found in ERP', variant: 'outline' },
    packet_not_in_zwing: { label: 'Not found in Zwing', variant: 'secondary' },
};

const filters: { value: string; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'matched', label: 'In both' },
    { value: 'mismatch', label: 'All mismatches' },
    { value: 'code_mismatch', label: 'Code mismatch' },
    { value: 'type_mismatch', label: 'Type mismatch' },
    { value: 'amount_mismatch', label: 'Amount mismatch' },
    { value: 'date_mismatch', label: 'Date mismatch' },
    { value: 'status_mismatch', label: 'Status mismatch' },
    { value: 'zwing_only', label: 'Not in ERP' },
    { value: 'erp_only', label: 'Not in Zwing' },
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

function valuesDiffer(
    zwing: string | null,
    erp: string | null,
): boolean {
    if (zwing === null || erp === null) {
        return zwing !== erp;
    }

    return zwing !== erp;
}

function compareCellClass(
    zwing: string | null,
    erp: string | null,
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

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(undefined, {
        dateStyle: 'medium',
    });
}

function formatAmount(value: string | number | null): string {
    if (value === null || value === '') {
        return '—';
    }

    const numeric = typeof value === 'number' ? value : Number(value);

    return Number.isNaN(numeric) ? String(value) : numeric.toLocaleString();
}

function amountAsString(value: string | number | null): string | null {
    if (value === null || value === '') {
        return null;
    }

    return String(value);
}

function CompareValue({
    value,
    copyLabel,
}: {
    value: string | null;
    copyLabel: string;
}) {
    if (value === null || value === '') {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div className="flex items-center gap-1">
            <span className="font-mono text-xs">{value}</span>
            <CopyIconButton text={value} label={copyLabel} />
        </div>
    );
}

function ComparisonRow({
    row,
    showCashColumns,
}: {
    row: ReportRow;
    showCashColumns: boolean;
}) {
    const { label, variant } = statusConfig[row.match_status];

    return (
        <tr className="divide-x divide-sidebar-border/50 hover:bg-muted/20">
            <td className="px-3 py-3 align-middle">
                <Badge variant={variant} className="text-xs whitespace-nowrap">
                    {label}
                </Badge>
            </td>
            <td className="px-3 py-3 align-middle font-mono text-xs">
                <CompareValue value={row.txn_id} copyLabel="Txn id" />
            </td>

            <td
                className={cn(
                    'px-3 py-3 align-middle',
                    ZWING_CELL,
                    compareCellClass(row.zwing_code, row.erp_code, 'zwing'),
                )}
            >
                <CompareValue value={row.zwing_code} copyLabel="Zwing code" />
            </td>
            <td
                className={cn(
                    'px-3 py-3 align-middle',
                    ZWING_CELL,
                    compareCellClass(row.zwing_type, row.erp_type, 'zwing'),
                )}
            >
                {row.zwing_type ?? '—'}
            </td>
            {showCashColumns && (
                <>
                    <td
                        className={cn(
                            'px-3 py-3 align-middle',
                            ZWING_CELL,
                            compareCellClass(
                                row.zwing_date,
                                row.erp_date,
                                'zwing',
                            ),
                        )}
                    >
                        {formatDate(row.zwing_date)}
                    </td>
                    <td
                        className={cn(
                            'px-3 py-3 align-middle font-mono text-xs',
                            ZWING_CELL,
                            compareCellClass(
                                amountAsString(row.zwing_amount),
                                amountAsString(row.erp_amount),
                                'zwing',
                            ),
                        )}
                    >
                        {formatAmount(row.zwing_amount)}
                    </td>
                </>
            )}
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
                    'px-3 py-3 align-middle',
                    ERP_CELL,
                    compareCellClass(row.zwing_code, row.erp_code, 'erp'),
                )}
            >
                <CompareValue value={row.erp_code} copyLabel="ERP code" />
            </td>
            <td
                className={cn(
                    'px-3 py-3 align-middle',
                    ERP_CELL,
                    compareCellClass(row.zwing_type, row.erp_type, 'erp'),
                )}
            >
                {row.erp_type ?? '—'}
            </td>
            {showCashColumns && (
                <>
                    <td
                        className={cn(
                            'px-3 py-3 align-middle',
                            ERP_CELL,
                            compareCellClass(
                                row.zwing_date,
                                row.erp_date,
                                'erp',
                            ),
                        )}
                    >
                        {formatDate(row.erp_date)}
                    </td>
                    <td
                        className={cn(
                            'px-3 py-3 align-middle font-mono text-xs',
                            ERP_CELL,
                            compareCellClass(
                                amountAsString(row.zwing_amount),
                                amountAsString(row.erp_amount),
                                'erp',
                            ),
                        )}
                    >
                        {formatAmount(row.erp_amount)}
                    </td>
                </>
            )}
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

export default function TransactionReconciliationReport({
    session,
    summary,
    rows,
    pagination,
    filter,
    filters: initialFilters,
    statusOptions,
}: Props) {
    const [codeQuery, setCodeQuery] = useState(initialFilters.code_query);
    const [zwingStatus, setZwingStatus] = useState(
        initialFilters.zwing_status || ANY_STATUS,
    );
    const [erpStatus, setErpStatus] = useState(
        initialFilters.erp_status || ANY_STATUS,
    );

    const activeFilters = useMemo(
        () => ({
            filter,
            code_query: codeQuery.trim(),
            zwing_status: zwingStatus === ANY_STATUS ? '' : zwingStatus,
            erp_status: erpStatus === ANY_STATUS ? '' : erpStatus,
        }),
        [codeQuery, erpStatus, filter, zwingStatus],
    );

    function buildQueryParams(page = 1) {
        return {
            filter: activeFilters.filter,
            code_query: activeFilters.code_query,
            zwing_status: activeFilters.zwing_status,
            erp_status: activeFilters.erp_status,
            page,
        };
    }

    const exportQueryString = useMemo(() => {
        const params = new URLSearchParams();
        if (activeFilters.filter !== 'all') {
            params.set('filter', activeFilters.filter);
        }
        if (activeFilters.code_query !== '') {
            params.set('code_query', activeFilters.code_query);
        }
        if (activeFilters.zwing_status !== '') {
            params.set('zwing_status', activeFilters.zwing_status);
        }
        if (activeFilters.erp_status !== '') {
            params.set('erp_status', activeFilters.erp_status);
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
        setCodeQuery('');
        setZwingStatus(ANY_STATUS);
        setErpStatus(ANY_STATUS);
        router.get(
            report.url(session.id),
            { filter: 'all', page: 1 },
            { preserveScroll: false },
        );
    }

    const isFiltered =
        filter !== 'all' ||
        activeFilters.code_query !== '' ||
        activeFilters.zwing_status !== '' ||
        activeFilters.erp_status !== '';

    function goToPage(page: number) {
        router.get(report.url(session.id), buildQueryParams(page), {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title={`${session.type_label} report — ${session.name}`} />

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
                                {session.name} · {session.type_label} · Vendor{' '}
                                {session.v_id}
                            </p>
                        </div>
                    </div>
                    <a
                        href={`/transaction-reconciliation/${session.id}/report/export${exportQueryString !== '' ? `?${exportQueryString}` : ''}`}
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
                        label="Code mismatch"
                        value={summary.code_mismatch}
                        color="text-amber-600 dark:text-amber-400"
                    />
                    {session.uses_cash_columns && (
                        <>
                            <SummaryCard
                                label="Amount mismatch"
                                value={summary.amount_mismatch}
                                color="text-amber-600 dark:text-amber-400"
                            />
                            <SummaryCard
                                label="Date mismatch"
                                value={summary.date_mismatch}
                                color="text-amber-600 dark:text-amber-400"
                            />
                        </>
                    )}
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
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="code-query">
                                Code / txn id search
                            </Label>
                            <Input
                                id="code-query"
                                placeholder="e.g. PCB8209012500001 or 17"
                                value={codeQuery}
                                onChange={(e) => setCodeQuery(e.target.value)}
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
                    {filters
                        .filter((item) => {
                            if (
                                !session.uses_cash_columns &&
                                (item.value === 'amount_mismatch' ||
                                    item.value === 'date_mismatch')
                            ) {
                                return false;
                            }

                            return true;
                        })
                        .map((item) => (
                        <Button
                            key={item.value}
                            variant={
                                filter === item.value ? 'default' : 'outline'
                            }
                            size="sm"
                            onClick={() => applyFilter(item.value)}
                        >
                            {item.label}
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
                                    <th
                                        rowSpan={2}
                                        className="border-r border-b bg-muted/50 px-3 py-3 text-left align-bottom font-medium"
                                    >
                                        Txn id
                                    </th>
                                    <th
                                        colSpan={
                                            session.uses_cash_columns ? 5 : 3
                                        }
                                        className={ZWING_HEAD}
                                    >
                                        Zwing
                                    </th>
                                    <th
                                        colSpan={
                                            session.uses_cash_columns ? 5 : 3
                                        }
                                        className={ERP_HEAD}
                                    >
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
                                        Code
                                    </th>
                                    <th
                                        className={cn(
                                            'px-3 py-2 font-medium',
                                            ZWING_CELL,
                                        )}
                                    >
                                        {session.uses_cash_columns
                                            ? 'Site'
                                            : 'Type'}
                                    </th>
                                    {session.uses_cash_columns && (
                                        <>
                                            <th
                                                className={cn(
                                                    'px-3 py-2 font-medium',
                                                    ZWING_CELL,
                                                )}
                                            >
                                                Date
                                            </th>
                                            <th
                                                className={cn(
                                                    'px-3 py-2 font-medium',
                                                    ZWING_CELL,
                                                )}
                                            >
                                                Amount
                                            </th>
                                        </>
                                    )}
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
                                        Code
                                    </th>
                                    <th
                                        className={cn(
                                            'px-3 py-2 font-medium',
                                            ERP_CELL,
                                        )}
                                    >
                                        {session.uses_cash_columns
                                            ? 'Site'
                                            : 'Type'}
                                    </th>
                                    {session.uses_cash_columns && (
                                        <>
                                            <th
                                                className={cn(
                                                    'px-3 py-2 font-medium',
                                                    ERP_CELL,
                                                )}
                                            >
                                                Date
                                            </th>
                                            <th
                                                className={cn(
                                                    'px-3 py-2 font-medium',
                                                    ERP_CELL,
                                                )}
                                            >
                                                Amount
                                            </th>
                                        </>
                                    )}
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
                                            colSpan={
                                                session.uses_cash_columns
                                                    ? 12
                                                    : 8
                                            }
                                            className="px-4 py-10 text-center text-sm text-muted-foreground"
                                        >
                                            No rows found for the selected
                                            filter.
                                        </td>
                                    </tr>
                                )}
                                {rows.map((row, index) => (
                                    <ComparisonRow
                                        key={`${row.txn_id}-${row.match_status}-${index}`}
                                        row={row}
                                        showCashColumns={
                                            session.uses_cash_columns
                                        }
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

TransactionReconciliationReport.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Transaction reconciliation', href: index.url() },
        { title: 'Comparison report', href: report.url(0) },
    ],
};
