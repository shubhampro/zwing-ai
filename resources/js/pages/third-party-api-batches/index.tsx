import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, MoreHorizontal, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/ThirdPartyApiBatchController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dashboard } from '@/routes';
import { index as apisIndex } from '@/routes/third-party-apis';
import { create, index, show } from '@/routes/third-party-api-batches';

type BatchStatus = 'pending' | 'processing' | 'completed' | 'failed';

type BatchRow = {
    id: number;
    name: string;
    file_name: string | null;
    row_count: number | null;
    processed_count: number;
    success_count: number;
    failed_count: number;
    skipped_count: number;
    status: BatchStatus;
    completed_at: string | null;
    created_at: string;
    organizationThirdPartyApi?: {
        id: number;
        base_url: string;
        thirdPartyApi?: { id: number; name: string; method: string };
        organization?: { id: number; name: string; ba_code: string };
    };
};

const statusVariant: Record<BatchStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    pending: 'secondary',
    processing: 'outline',
    completed: 'default',
    failed: 'destructive',
};

export default function ThirdPartyApiBatchesIndex({ batches }: { batches: BatchRow[] }) {
    const [deletingBatch, setDeletingBatch] = useState<BatchRow | null>(null);
    const { delete: deleteBatch, processing } = useForm();

    return (
        <>
            <Head title="API batches" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="API batches"
                        description="CSV-driven bulk runs against any configured third party API."
                    />
                    <Button size="sm" asChild>
                        <Link href={create.url()}>
                            <Plus className="size-4" />
                            New batch
                        </Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full min-w-[720px] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Batch</th>
                                <th className="px-3 py-2 font-medium">API</th>
                                <th className="px-3 py-2 font-medium">Rows</th>
                                <th className="px-3 py-2 font-medium">Success</th>
                                <th className="px-3 py-2 font-medium">Failed</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {batches.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-3 py-8 text-center text-muted-foreground">
                                        No batches yet.{' '}
                                        <Link href={create.url()} className="text-foreground underline">
                                            Run one
                                        </Link>
                                        .
                                    </td>
                                </tr>
                            )}
                            {batches.map((batch) => (
                                <tr
                                    key={batch.id}
                                    className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                >
                                    <td className="px-3 py-2 font-medium">{batch.name}</td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {batch.organizationThirdPartyApi?.thirdPartyApi?.name ?? '—'}
                                        {batch.organizationThirdPartyApi?.thirdPartyApi?.method && (
                                            <Badge variant="outline" className="ml-2">
                                                {batch.organizationThirdPartyApi.thirdPartyApi.method}
                                            </Badge>
                                        )}
                                        {batch.organizationThirdPartyApi?.organization && (
                                            <span className="ml-2 text-muted-foreground">
                                                {batch.organizationThirdPartyApi.organization.name}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 tabular-nums">{batch.row_count ?? '—'}</td>
                                    <td className="px-3 py-2 tabular-nums text-green-600">{batch.success_count}</td>
                                    <td className="px-3 py-2 tabular-nums text-destructive">{batch.failed_count}</td>
                                    <td className="px-3 py-2">
                                        <Badge variant={statusVariant[batch.status]}>{batch.status}</Badge>
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="sm">
                                                    <MoreHorizontal className="size-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <Link href={show.url(batch.id)}>
                                                        <Eye className="size-4" />
                                                        View batch
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    className="text-destructive focus:text-destructive"
                                                    onSelect={() => setDeletingBatch(batch)}
                                                >
                                                    <Trash2 className="size-4" />
                                                    Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog open={deletingBatch !== null} onOpenChange={(open) => !open && setDeletingBatch(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete batch?</DialogTitle>
                        <DialogDescription>
                            Remove <span className="font-medium text-foreground">{deletingBatch?.name}</span>.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeletingBatch(null)} disabled={processing}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={processing || !deletingBatch}
                            onClick={() => {
                                if (!deletingBatch) return;
                                deleteBatch(destroy.url(deletingBatch.id), {
                                    onSuccess: () => setDeletingBatch(null),
                                });
                            }}
                        >
                            {processing ? 'Deleting…' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ThirdPartyApiBatchesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Third party APIs', href: apisIndex.url() },
        { title: 'Batches', href: index.url() },
    ],
};
