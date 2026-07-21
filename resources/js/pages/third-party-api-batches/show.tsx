import { Head, Link, router, useForm, usePoll } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, Download, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import {
    retryFailed,
    retryItem,
} from '@/actions/App/Http/Controllers/ThirdPartyApiBatchController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import { index as apisIndex } from '@/routes/third-party-apis';
import { index, show } from '@/routes/third-party-api-batches';

type BatchStatus = 'pending' | 'processing' | 'completed' | 'failed';
type ItemStatus = 'pending' | 'success' | 'failed' | 'skipped';

type Attempt = {
    id: number;
    attempt_number: number;
    request_method: string;
    request_url: string;
    request_headers: Record<string, string>;
    request_body: Record<string, string>;
    http_status: number | null;
    response_body: string | null;
    error_message: string | null;
    created_at: string | null;
};

type Row = {
    id: number;
    payload: Record<string, string>;
    status: ItemStatus;
    http_status: number | null;
    response_body: string | null;
    error_message: string | null;
    processed_at: string | null;
    attempts: Attempt[];
};

const filters = [
    { value: 'all', label: 'All' },
    { value: 'success', label: 'Success' },
    { value: 'failed', label: 'Failed' },
    { value: 'pending', label: 'Pending' },
    { value: 'skipped', label: 'Skipped' },
] as const;

const statusVariant: Record<
    ItemStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    success: 'default',
    failed: 'destructive',
    pending: 'outline',
    skipped: 'secondary',
};

type Props = {
    batch: {
        id: number;
        name: string;
        file_name: string | null;
        row_count: number | null;
        processed_count: number;
        success_count: number;
        failed_count: number;
        skipped_count: number;
        defaults: Record<string, string>;
        status: BatchStatus;
        completed_at: string | null;
        organizationThirdPartyApi?: {
            id: number;
            base_url: string;
            thirdPartyApi?: {
                id: number;
                name: string;
                path: string;
                method: string;
            };
            organization?: { id: number; name: string; ba_code: string };
        };
    };
    paramKeys: string[];
    summary: {
        total: number;
        success: number;
        failed: number;
        skipped: number;
        pending: number;
    };
    rows: Row[];
    pagination: {
        total: number;
        per_page: number;
        current_page: number;
        last_page: number;
    };
    filter: string;
    search: string;
};

export default function ThirdPartyApiBatchesShow({
    batch,
    paramKeys,
    summary,
    rows,
    pagination,
    filter,
    search: initialSearch,
}: Props) {
    const [search, setSearch] = useState(initialSearch);
    const [openRowId, setOpenRowId] = useState<number | null>(null);
    const { post: retryOne, processing: retryingOne } = useForm();
    const { post: retryAll, processing: retryingAll } = useForm();

    const isRunning =
        batch.status === 'pending' || batch.status === 'processing';
    usePoll(
        2000,
        { only: ['batch', 'summary', 'rows', 'pagination'] },
        { autoStart: isRunning },
    );

    function apply(filterValue: string, page = 1) {
        router.get(
            show.url(batch.id),
            { filter: filterValue, search, page },
            { preserveScroll: true, preserveState: true },
        );
    }

    const exportUrl = `/third-party-api-batches/${batch.id}/report/export?filter=${filter}${search ? `&search=${encodeURIComponent(search)}` : ''}`;

    return (
        <>
            <Head title={`Batch — ${batch.name}`} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link href={index.url()}>
                            <Button variant="outline" size="icon">
                                <ArrowLeft className="size-4" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-xl font-semibold">
                                {batch.name}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {
                                    batch.organizationThirdPartyApi
                                        ?.thirdPartyApi?.name
                                }{' '}
                                (
                                {
                                    batch.organizationThirdPartyApi
                                        ?.thirdPartyApi?.method
                                }
                                ){' · '}
                                {
                                    batch.organizationThirdPartyApi
                                        ?.organization?.name
                                }
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {summary.failed > 0 && (
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={retryingAll}
                                onClick={() =>
                                    retryAll(retryFailed.url(batch.id))
                                }
                            >
                                <RotateCcw className="size-4" />
                                Retry failed ({summary.failed})
                            </Button>
                        )}
                        <a href={exportUrl} download>
                            <Button variant="outline" size="sm">
                                <Download className="size-4" />
                                Export
                            </Button>
                        </a>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Badge>{batch.status}</Badge>
                    {batch.file_name && (
                        <span className="font-mono text-xs text-muted-foreground">
                            {batch.file_name}
                        </span>
                    )}
                </div>

                <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    {[
                        ['Rows', batch.row_count ?? '—'],
                        ['Processed', batch.processed_count],
                        ['Success', batch.success_count],
                        ['Failed', batch.failed_count],
                        ['Skipped', batch.skipped_count],
                    ].map(([label, value]) => (
                        <div
                            key={label}
                            className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                        >
                            <dt className="text-xs text-muted-foreground">
                                {label}
                            </dt>
                            <dd className="text-2xl font-semibold tabular-nums">
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>

                <div className="flex flex-wrap gap-2">
                    {filters.map((f) => (
                        <Button
                            key={f.value}
                            variant={filter === f.value ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => apply(f.value)}
                        >
                            {f.label}
                            <span className="ml-1 text-muted-foreground tabular-nums">
                                (
                                {summary[f.value as keyof typeof summary] ??
                                    summary.total}
                                )
                            </span>
                        </Button>
                    ))}
                </div>

                <div className="flex gap-2">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search payload…"
                        className="max-w-xs"
                    />
                    <Button variant="outline" onClick={() => apply(filter)}>
                        Search
                    </Button>
                </div>

                <div className="flex flex-col gap-3">
                    {rows.length === 0 && (
                        <p className="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                            No rows match this filter.
                        </p>
                    )}

                    {rows.map((row) => (
                        <Collapsible
                            key={row.id}
                            open={openRowId === row.id}
                            onOpenChange={(open) =>
                                setOpenRowId(open ? row.id : null)
                            }
                            className="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
                        >
                            <div className="flex flex-wrap items-center gap-3 p-3">
                                <CollapsibleTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="gap-2 px-2"
                                    >
                                        <ChevronDown className="size-4 transition-transform [[data-state=open]_&]:rotate-180" />
                                        <span className="font-mono text-xs">
                                            {paramKeys
                                                .map(
                                                    (key) => row.payload?.[key],
                                                )
                                                .filter(Boolean)
                                                .join(' · ') ||
                                                `Row #${row.id}`}
                                        </span>
                                    </Button>
                                </CollapsibleTrigger>
                                <Badge variant={statusVariant[row.status]}>
                                    {row.status}
                                </Badge>
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    HTTP {row.http_status ?? '—'}
                                </span>
                                {row.error_message && (
                                    <span className="truncate text-xs text-destructive">
                                        {row.error_message}
                                    </span>
                                )}
                                <span className="ml-auto text-xs text-muted-foreground">
                                    {row.attempts.length} attempt
                                    {row.attempts.length === 1 ? '' : 's'}
                                </span>
                                {(row.status === 'failed' ||
                                    row.status === 'success') && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={retryingOne}
                                        onClick={() =>
                                            retryOne(
                                                retryItem.url({
                                                    thirdPartyApiBatch:
                                                        batch.id,
                                                    thirdPartyApiBatchItem:
                                                        row.id,
                                                }),
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <RotateCcw className="size-4" />
                                        Retry
                                    </Button>
                                )}
                            </div>

                            <CollapsibleContent className="border-t px-3 pb-3">
                                <div className="flex flex-col gap-4 pt-3">
                                    {row.attempts.length === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            No attempt history yet.
                                        </p>
                                    )}
                                    {row.attempts.map((attempt) => (
                                        <div
                                            key={attempt.id}
                                            className="rounded-md bg-muted/40 p-3 text-xs"
                                        >
                                            <div className="mb-2 flex flex-wrap items-center gap-2">
                                                <Badge variant="outline">
                                                    Attempt{' '}
                                                    {attempt.attempt_number}
                                                </Badge>
                                                <span className="font-mono">
                                                    {attempt.request_method}{' '}
                                                    {attempt.request_url}
                                                </span>
                                                <span className="tabular-nums">
                                                    HTTP{' '}
                                                    {attempt.http_status ?? '—'}
                                                </span>
                                                {attempt.created_at && (
                                                    <span className="text-muted-foreground">
                                                        {attempt.created_at}
                                                    </span>
                                                )}
                                            </div>
                                            <div className="grid gap-3 lg:grid-cols-2">
                                                <div>
                                                    <p className="mb-1 font-medium">
                                                        Request headers
                                                    </p>
                                                    <pre className="overflow-x-auto rounded border bg-background p-2 font-mono">
                                                        {JSON.stringify(
                                                            attempt.request_headers,
                                                            null,
                                                            2,
                                                        )}
                                                    </pre>
                                                    <p className="mt-2 mb-1 font-medium">
                                                        Request body
                                                    </p>
                                                    <pre className="overflow-x-auto rounded border bg-background p-2 font-mono">
                                                        {JSON.stringify(
                                                            attempt.request_body,
                                                            null,
                                                            2,
                                                        )}
                                                    </pre>
                                                </div>
                                                <div>
                                                    <p className="mb-1 font-medium">
                                                        Response
                                                    </p>
                                                    <pre className="max-h-48 overflow-auto rounded border bg-background p-2 font-mono whitespace-pre-wrap">
                                                        {attempt.response_body ??
                                                            attempt.error_message ??
                                                            '—'}
                                                    </pre>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CollapsibleContent>
                        </Collapsible>
                    ))}
                </div>

                {pagination.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Page {pagination.current_page} of{' '}
                            {pagination.last_page}
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={pagination.current_page <= 1}
                                onClick={() =>
                                    apply(filter, pagination.current_page - 1)
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
                                    apply(filter, pagination.current_page + 1)
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

ThirdPartyApiBatchesShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Third party APIs', href: apisIndex.url() },
        { title: 'Batches', href: index.url() },
        { title: 'Batch', href: show.url(0) },
    ],
};
