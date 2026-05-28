import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Search, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import {
    index,
    show,
    zwingLogs,
} from '@/routes/stock-transaction-reconciliation';

type LogRow = {
    id: number;
    v_id: number;
    site_code: string;
    icode: string;
    batch_no: string;
    sprefcode: string;
    doc_no: string;
    enttype: string;
    qty: number;
};

type Pagination = {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
};

type Props = {
    session: { id: number; name: string; v_id: number; status: string };
    rows: LogRow[];
    pagination: Pagination;
    search: string;
};

export default function StockTransactionReconciliationZwingLogs({
    session,
    rows,
    pagination,
    search,
}: Props) {
    const [searchValue, setSearchValue] = useState(search);

    function applySearch(e: React.FormEvent) {
        e.preventDefault();
        router.get(
            zwingLogs.url(session.id),
            { search: searchValue, page: 1 },
            { preserveScroll: false },
        );
    }

    function clearSearch() {
        setSearchValue('');
        router.get(zwingLogs.url(session.id), {}, { preserveScroll: false });
    }

    function goToPage(page: number) {
        router.get(
            zwingLogs.url(session.id),
            { search, page },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={`Zwing logs — ${session.name}`} />

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
                                Zwing logs
                                <span className="ml-2 font-mono text-base text-muted-foreground">
                                    #{session.id}
                                </span>
                            </h1>
                            <p className="mt-0.5 text-sm text-muted-foreground">
                                {session.name} · Vendor ID {session.v_id}
                            </p>
                        </div>
                    </div>

                    <form
                        onSubmit={applySearch}
                        className="flex items-center gap-2"
                    >
                        <div className="relative">
                            <Search className="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                className="h-8 w-56 pl-8 text-sm"
                                placeholder="Search icode, site, doc…"
                                value={searchValue}
                                onChange={(e) => setSearchValue(e.target.value)}
                            />
                        </div>
                        {search !== '' && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={clearSearch}
                                className="gap-1 text-muted-foreground"
                            >
                                <X className="size-3.5" />
                                Clear
                            </Button>
                        )}
                    </form>
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
                                        Doc no
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Entry type
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Qty
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Vendor ID
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
                                            {search !== ''
                                                ? 'No rows match your search.'
                                                : 'No log rows found for this session.'}
                                        </td>
                                    </tr>
                                )}
                                {rows.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-2.5">
                                            {row.site_code || '—'}
                                        </td>
                                        <td className="px-4 py-2.5 font-mono text-xs">
                                            {row.icode || '—'}
                                        </td>
                                        <td className="px-4 py-2.5 text-muted-foreground">
                                            {row.batch_no || '—'}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            {row.sprefcode || '—'}
                                        </td>
                                        <td className="px-4 py-2.5 font-mono text-xs">
                                            {row.doc_no || '—'}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            {row.enttype || '—'}
                                        </td>
                                        <td className="px-4 py-2.5 text-right tabular-nums">
                                            {Number(row.qty).toLocaleString()}
                                        </td>
                                        <td className="px-4 py-2.5 text-right text-muted-foreground tabular-nums">
                                            {row.v_id}
                                        </td>
                                    </tr>
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

StockTransactionReconciliationZwingLogs.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Stock reconciliation', href: index.url() },
        { title: 'Session details', href: show.url(0) },
        { title: 'Zwing logs', href: zwingLogs.url(0) },
    ],
};
