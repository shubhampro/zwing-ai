import { Head, Link, useForm } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/OrganizationController';
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
import { dashboard } from '@/routes';
import { create, edit, index } from '@/routes/organizations';

type Organization = {
    id: number;
    name: string;
    ba_code: string;
    vendor_id: number;
    created_at: string;
};

function DeleteDialog({
    organization,
    open,
    onOpenChange,
}: {
    organization: Organization;
    open: boolean;
    onOpenChange: (v: boolean) => void;
}) {
    const { delete: deleteOrg, processing } = useForm();

    function confirm() {
        deleteOrg(destroy.url(organization.id), {
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete organization?</DialogTitle>
                    <DialogDescription>
                        This will permanently delete{' '}
                        <span className="font-medium text-foreground">
                            "{organization.name}"
                        </span>
                        . This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={confirm}
                        disabled={processing}
                    >
                        {processing ? 'Deleting…' : 'Delete'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function OrganizationsIndex({
    organizations,
}: {
    organizations: Organization[];
}) {
    const [deletingOrg, setDeletingOrg] = useState<Organization | null>(null);

    return (
        <>
            <Head title="Organizations" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Organizations"
                        description="Manage organizations, BA codes and vendor mappings."
                    />
                    <Button size="sm" asChild>
                        <Link href={create.url()}>New organization</Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full min-w-[560px] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">#</th>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">
                                    BA Code
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Vendor ID
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Created at
                                </th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {organizations.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No organizations found.{' '}
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
                            {organizations.map((org) => (
                                <tr
                                    key={org.id}
                                    className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                >
                                    <td className="px-3 py-2 font-mono text-xs text-muted-foreground">
                                        {org.id}
                                    </td>
                                    <td className="px-3 py-2 font-medium">
                                        {org.name}
                                    </td>
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {org.ba_code}
                                    </td>
                                    <td className="px-3 py-2 tabular-nums">
                                        {org.vendor_id}
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {new Date(
                                            org.created_at,
                                        ).toLocaleDateString(undefined, {
                                            dateStyle: 'medium',
                                        })}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="cursor-pointer"
                                                >
                                                    <MoreHorizontal className="size-4" />
                                                    <span className="sr-only">
                                                        Actions
                                                    </span>
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={edit.url(org.id)}
                                                    >
                                                        <Pencil className="size-4" />
                                                        Edit
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    className="text-destructive focus:text-destructive"
                                                    onSelect={() =>
                                                        setDeletingOrg(org)
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

            {deletingOrg && (
                <DeleteDialog
                    organization={deletingOrg}
                    open={deletingOrg !== null}
                    onOpenChange={(v) => {
                        if (!v) setDeletingOrg(null);
                    }}
                />
            )}
        </>
    );
}

OrganizationsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Organizations', href: index.url() },
    ],
};
