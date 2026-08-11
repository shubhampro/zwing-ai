import { Head, Link, useForm } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Plus, Trash2, WandSparkles } from 'lucide-react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/PayloadComposerController';
import Heading from '@/components/heading';
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
import { formatDateTime } from '@/lib/datetime';
import { dashboard } from '@/routes';
import { create, edit, index, show } from '@/routes/payload-composers';

type ComposerRow = {
    id: number;
    name: string;
    description: string | null;
    scalar_count: number;
    slot_count: number;
    updated_at: string | null;
};

export default function PayloadComposersIndex({
    composers,
}: {
    composers: ComposerRow[];
}) {
    const [deleting, setDeleting] = useState<ComposerRow | null>(null);
    const { delete: deleteComposer, processing } = useForm();

    return (
        <>
            <Head title="Payload composers" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Payload composers"
                        description="Pick scalars + saved SQL slots, run against an org DB, get one nested JSON payload."
                    />
                    <Button size="sm" asChild>
                        <Link href={create.url()}>
                            <Plus className="size-4" />
                            New composer
                        </Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full min-w-[640px] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">
                                    Scalars
                                </th>
                                <th className="px-3 py-2 font-medium">Slots</th>
                                <th className="px-3 py-2 font-medium">
                                    Updated
                                </th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {composers.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No composers yet.{' '}
                                        <Link
                                            href={create.url()}
                                            className="text-foreground underline"
                                        >
                                            Create one
                                        </Link>
                                        .
                                    </td>
                                </tr>
                            )}
                            {composers.map((composer) => (
                                <tr
                                    key={composer.id}
                                    className="border-t border-sidebar-border/60"
                                >
                                    <td className="px-3 py-2">
                                        <Link
                                            href={show.url(composer.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {composer.name}
                                        </Link>
                                        {composer.description && (
                                            <p className="text-xs text-muted-foreground">
                                                {composer.description}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        {composer.scalar_count}
                                    </td>
                                    <td className="px-3 py-2">
                                        {composer.slot_count}
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {formatDateTime(composer.updated_at)}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-8"
                                                >
                                                    <MoreHorizontal className="size-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={show.url(
                                                            composer.id,
                                                        )}
                                                    >
                                                        <WandSparkles className="size-4" />
                                                        Generate
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={edit.url(
                                                            composer.id,
                                                        )}
                                                    >
                                                        <Pencil className="size-4" />
                                                        Edit
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    variant="destructive"
                                                    onClick={() =>
                                                        setDeleting(composer)
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
                open={deleting !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleting(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete composer?</DialogTitle>
                        <DialogDescription>
                            {deleting
                                ? `Remove “${deleting.name}”. This cannot be undone.`
                                : null}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleting(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={processing || deleting === null}
                            onClick={() => {
                                if (deleting === null) {
                                    return;
                                }

                                deleteComposer(destroy.url(deleting.id), {
                                    onSuccess: () => setDeleting(null),
                                });
                            }}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

PayloadComposersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Payload composers', href: index.url() },
    ],
};
