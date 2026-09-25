import { Head, Link, router } from '@inertiajs/react';
import { Eye, Search } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
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
import { formatDateTime } from '@/lib/datetime';
import { dashboard } from '@/routes';
import { index, show } from '@/routes/sf-cases';
import { formatNumber } from '../sf-mbr/format';

type CaseRow = {
    id: number;
    case_number: string;
    subject: string;
    status: string;
    priority: string | null;
    type: string | null;
    account_name: string | null;
    group_name: string | null;
    owner_name: string | null;
    agent_name: string | null;
    is_closed: boolean;
    is_spam: boolean;
    created_at_sf: string | null;
    resolved_at_sf: string | null;
};

type Pagination = {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
};

type Filters = {
    q: string;
    status: string;
    priority: string;
};

const ANY = '__any__';

function dash(value: string | null | undefined): string {
    return value && value !== '' ? value : '—';
}

export default function SalesforceCasesIndex({
    cases,
    pagination,
    filters,
    statuses,
    priorities,
}: {
    cases: CaseRow[];
    pagination: Pagination;
    filters: Filters;
    statuses: string[];
    priorities: string[];
}) {
    const [q, setQ] = useState(filters.q);
    const [status, setStatus] = useState(filters.status || ANY);
    const [priority, setPriority] = useState(filters.priority || ANY);

    function applyFilters(page = 1) {
        router.get(
            index.url(),
            {
                q: q.trim() === '' ? undefined : q.trim(),
                status: status === ANY ? undefined : status,
                priority: priority === ANY ? undefined : priority,
                page,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function clearFilters() {
        setQ('');
        setStatus(ANY);
        setPriority(ANY);
        router.get(index.url());
    }

    return (
        <>
            <Head title="SF Cases" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="SF Cases"
                    description="Local Salesforce dump. Search case #, subject, account, owner, agent, Jira."
                />

                <form
                    className="grid gap-3 rounded-lg border border-sidebar-border/70 p-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto] dark:border-sidebar-border"
                    onSubmit={(event) => {
                        event.preventDefault();
                        applyFilters(1);
                    }}
                >
                    <div className="space-y-1.5">
                        <Label htmlFor="q">Search</Label>
                        <Input
                            id="q"
                            value={q}
                            onChange={(event) => setQ(event.target.value)}
                            placeholder="Case #, subject, account…"
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="status">Status</Label>
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger id="status">
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ANY}>
                                    All statuses
                                </SelectItem>
                                {statuses.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="priority">Priority</Label>
                        <Select value={priority} onValueChange={setPriority}>
                            <SelectTrigger id="priority">
                                <SelectValue placeholder="All priorities" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ANY}>
                                    All priorities
                                </SelectItem>
                                {priorities.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex items-end gap-2">
                        <Button type="submit">
                            <Search className="size-4" />
                            Search
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={clearFilters}
                        >
                            Clear
                        </Button>
                    </div>
                </form>

                <p className="text-sm text-muted-foreground">
                    {formatNumber(pagination.total)} tickets
                    {filters.q !== '' ? ` · “${filters.q}”` : ''}
                    {filters.status !== '' ? ` · ${filters.status}` : ''}
                    {filters.priority !== '' ? ` · ${filters.priority}` : ''}
                </p>

                <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full min-w-[72rem] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Case</th>
                                <th className="px-3 py-2 font-medium">
                                    Subject
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Account
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Priority
                                </th>
                                <th className="px-3 py-2 font-medium">Group</th>
                                <th className="px-3 py-2 font-medium">Owner</th>
                                <th className="px-3 py-2 font-medium">
                                    Created
                                </th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {cases.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={9}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No local cases match. Pull dump or clear
                                        search.
                                    </td>
                                </tr>
                            ) : (
                                cases.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                    >
                                        <td className="px-3 py-2 font-medium tabular-nums">
                                            <Link
                                                href={show.url(row.case_number)}
                                                className="text-primary underline-offset-4 hover:underline"
                                            >
                                                {row.case_number}
                                            </Link>
                                        </td>
                                        <td className="max-w-[22rem] px-3 py-2">
                                            <div className="truncate">
                                                {row.subject}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {dash(row.type)}
                                                {row.is_spam ? ' · spam' : ''}
                                            </div>
                                        </td>
                                        <td className="max-w-[14rem] truncate px-3 py-2">
                                            {dash(row.account_name)}
                                        </td>
                                        <td className="px-3 py-2">
                                            <Badge
                                                variant={
                                                    row.is_closed
                                                        ? 'secondary'
                                                        : 'default'
                                                }
                                            >
                                                {row.status}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2">
                                            {dash(row.priority)}
                                        </td>
                                        <td className="px-3 py-2">
                                            {dash(row.group_name)}
                                        </td>
                                        <td className="px-3 py-2">
                                            <div>{dash(row.owner_name)}</div>
                                            <div className="text-xs text-muted-foreground">
                                                {dash(row.agent_name)}
                                            </div>
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {formatDateTime(row.created_at_sf)}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={show.url(
                                                        row.case_number,
                                                    )}
                                                >
                                                    <Eye className="size-4" />
                                                    View
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {pagination.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Page {pagination.current_page} of{' '}
                            {pagination.last_page}
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={pagination.current_page <= 1}
                                onClick={() =>
                                    applyFilters(pagination.current_page - 1)
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
                                    applyFilters(pagination.current_page + 1)
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

SalesforceCasesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'SF Cases', href: index() },
    ],
};
