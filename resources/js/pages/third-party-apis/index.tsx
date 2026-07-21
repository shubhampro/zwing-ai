import { Head, Link, useForm } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/ThirdPartyApiController';
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
import { index as batchesIndex } from '@/routes/third-party-api-batches';
import { create, edit, index } from '@/routes/third-party-apis';

type ApiRow = {
    id: number;
    name: string;
    path: string;
    method: string;
    param_count: number;
    auth_header_name: string;
    is_active: boolean;
    connection_count: number;
    created_at: string | null;
};

export default function ThirdPartyApisIndex({ apis }: { apis: ApiRow[] }) {
    const [deletingApi, setDeletingApi] = useState<ApiRow | null>(null);
    const { delete: deleteApi, processing } = useForm();

    return (
        <>
            <Head title="Third party APIs" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Third party APIs"
                        description="Reusable API templates — path, method, params. Assign base URL + token per org under Organizations → View."
                    />
                    <div className="flex gap-2">
                        <Button size="sm" variant="outline" asChild>
                            <Link href={batchesIndex.url()}>API batches</Link>
                        </Button>
                        <Button size="sm" asChild>
                            <Link href={create.url()}>
                                <Plus className="size-4" />
                                Add API template
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full min-w-[720px] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">
                                    Method
                                </th>
                                <th className="px-3 py-2 font-medium">Path</th>
                                <th className="px-3 py-2 font-medium">
                                    Params
                                </th>
                                <th className="px-3 py-2 font-medium">Orgs</th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {apis.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No API templates.{' '}
                                        <Link
                                            href={create.url()}
                                            className="text-foreground underline"
                                        >
                                            Add one
                                        </Link>
                                        .
                                    </td>
                                </tr>
                            )}
                            {apis.map((api) => (
                                <tr
                                    key={api.id}
                                    className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                >
                                    <td className="px-3 py-2 font-medium">
                                        {api.name}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge variant="outline">
                                            {api.method}
                                        </Badge>
                                    </td>
                                    <td className="max-w-xs truncate px-3 py-2 font-mono text-xs">
                                        {api.path}
                                    </td>
                                    <td className="px-3 py-2 tabular-nums">
                                        {api.param_count}
                                    </td>
                                    <td className="px-3 py-2 tabular-nums">
                                        {api.connection_count}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge
                                            variant={
                                                api.is_active
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {api.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    <MoreHorizontal className="size-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={edit.url(api.id)}
                                                    >
                                                        <Pencil className="size-4" />
                                                        Edit template & orgs
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    className="text-destructive focus:text-destructive"
                                                    onSelect={() =>
                                                        setDeletingApi(api)
                                                    }
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

            <Dialog
                open={deletingApi !== null}
                onOpenChange={(open) => !open && setDeletingApi(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete API template?</DialogTitle>
                        <DialogDescription>
                            Remove{' '}
                            <span className="font-medium text-foreground">
                                {deletingApi?.name}
                            </span>{' '}
                            and all org connections.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeletingApi(null)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={processing || !deletingApi}
                            onClick={() => {
                                if (!deletingApi) return;
                                deleteApi(destroy.url(deletingApi.id), {
                                    onSuccess: () => setDeletingApi(null),
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

ThirdPartyApisIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Third party APIs', href: index.url() },
    ],
};
