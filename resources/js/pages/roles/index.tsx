import { Head, Link, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { dashboard } from '@/routes';
import { create, destroy, edit, index } from '@/routes/roles';

type RoleRow = {
    id: number;
    name: string;
    permissions_count: number;
    users_count: number;
    is_system: boolean;
};

function DeleteDialog({
    role,
    open,
    onOpenChange,
}: {
    role: RoleRow;
    open: boolean;
    onOpenChange: (v: boolean) => void;
}) {
    const { delete: deleteRole, processing, errors } = useForm();

    function confirm() {
        deleteRole(destroy.url(role.id), {
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete role?</DialogTitle>
                    <DialogDescription>
                        This will permanently delete{' '}
                        <span className="font-medium text-foreground">
                            "{role.name}"
                        </span>
                        . This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <InputError message={errors.role} />
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

export default function RolesIndex({ roles }: { roles: RoleRow[] }) {
    const [deletingRole, setDeletingRole] = useState<RoleRow | null>(null);

    return (
        <>
            <Head title="Roles" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        className="mb-0"
                        title="Roles"
                        description="Create roles and assign seeded permissions."
                    />
                    <Button size="sm" asChild className="sm:shrink-0">
                        <Link href={create.url()}>
                            <Plus className="size-4" />
                            New role
                        </Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full min-w-[640px] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">
                                    Permissions
                                </th>
                                <th className="px-3 py-2 font-medium">Users</th>
                                <th className="px-3 py-2 font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {roles.map((role) => (
                                <tr
                                    key={role.id}
                                    className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                >
                                    <td className="px-3 py-2">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">
                                                {role.name}
                                            </span>
                                            {role.is_system && (
                                                <Badge variant="secondary">
                                                    system
                                                </Badge>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {role.permissions_count}
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {role.users_count}
                                    </td>
                                    <td className="px-3 py-2">
                                        <div className="flex items-center gap-1">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                asChild
                                            >
                                                <Link href={edit.url(role.id)}>
                                                    <Pencil className="size-4" />
                                                    Edit
                                                </Link>
                                            </Button>
                                            {!role.is_system && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        setDeletingRole(role)
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                    Delete
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {deletingRole && (
                <DeleteDialog
                    role={deletingRole}
                    open={deletingRole !== null}
                    onOpenChange={(v) => {
                        if (!v) {
                            setDeletingRole(null);
                        }
                    }}
                />
            )}
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Roles', href: index.url() },
    ],
};
