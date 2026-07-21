import { Head, router, useForm, usePage } from '@inertiajs/react';
import { MoreHorizontal, RotateCcw, Trash2 } from 'lucide-react';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import {
    destroy as softDestroy,
    forceDestroy,
    index,
    restore as restoreUser,
} from '@/routes/users';
import { update as updateRole } from '@/routes/users/role';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    created_at: string | null;
    deleted_at: string | null;
};

type DeleteMode = 'soft' | 'force';

function RoleForm({ user, roles }: { user: UserRow; roles: string[] }) {
    const { data, setData, put, processing, errors } = useForm({
        role: user.role,
    });

    if (user.deleted_at) {
        return <span className="text-muted-foreground">{user.role}</span>;
    }

    return (
        <form
            className="flex items-center gap-2"
            onSubmit={(e) => {
                e.preventDefault();
                put(updateRole.url(user.id));
            }}
        >
            <Select
                value={data.role}
                onValueChange={(value) => setData('role', value)}
            >
                <SelectTrigger className="w-36" size="sm">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {roles.map((role) => (
                        <SelectItem key={role} value={role}>
                            {role}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Button type="submit" size="sm" disabled={processing}>
                {processing ? 'Saving…' : 'Save'}
            </Button>
            <InputError message={errors.role} />
        </form>
    );
}

function DeleteDialog({
    user,
    mode,
    open,
    onOpenChange,
}: {
    user: UserRow;
    mode: DeleteMode;
    open: boolean;
    onOpenChange: (v: boolean) => void;
}) {
    const { delete: deleteUser, processing, errors } = useForm();

    function confirm() {
        const url =
            mode === 'soft'
                ? softDestroy.url(user.id)
                : forceDestroy.url(user.id);

        deleteUser(url, {
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {mode === 'soft'
                            ? 'Soft delete user?'
                            : 'Permanently delete user?'}
                    </DialogTitle>
                    <DialogDescription>
                        {mode === 'soft' ? (
                            <>
                                Soft delete{' '}
                                <span className="font-medium text-foreground">
                                    {user.name}
                                </span>
                                . They lose access but can be restored later.
                            </>
                        ) : (
                            <>
                                Permanently delete{' '}
                                <span className="font-medium text-foreground">
                                    {user.name}
                                </span>
                                . This cannot be undone.
                            </>
                        )}
                    </DialogDescription>
                </DialogHeader>
                <InputError message={errors.user} />
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
                        {processing
                            ? 'Deleting…'
                            : mode === 'soft'
                              ? 'Soft delete'
                              : 'Permanent delete'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function UserActions({
    user,
    onDelete,
}: {
    user: UserRow;
    onDelete: (user: UserRow, mode: DeleteMode) => void;
}) {
    const currentUserId = usePage().props.auth.user.id;
    const isSelf = Number(user.id) === Number(currentUserId);

    function restore() {
        router.post(restoreUser.url(user.id));
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="sm" className="cursor-pointer">
                    <MoreHorizontal className="size-4" />
                    <span className="sr-only">Actions</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {isSelf ? (
                    <DropdownMenuItem disabled>
                        Your account — use Profile to delete
                    </DropdownMenuItem>
                ) : user.deleted_at ? (
                    <>
                        <DropdownMenuItem onSelect={restore}>
                            <RotateCcw className="size-4" />
                            Restore
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            className="text-destructive focus:text-destructive"
                            onSelect={() => onDelete(user, 'force')}
                        >
                            <Trash2 className="size-4" />
                            Permanent delete
                        </DropdownMenuItem>
                    </>
                ) : (
                    <>
                        <DropdownMenuItem
                            onSelect={() => onDelete(user, 'soft')}
                        >
                            <Trash2 className="size-4" />
                            Soft delete
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            className="text-destructive focus:text-destructive"
                            onSelect={() => onDelete(user, 'force')}
                        >
                            <Trash2 className="size-4" />
                            Permanent delete
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default function UsersIndex({
    users,
    roles,
}: {
    users: UserRow[];
    roles: string[];
}) {
    const [pendingDelete, setPendingDelete] = useState<{
        user: UserRow;
        mode: DeleteMode;
    } | null>(null);
    const pageErrors = usePage().props.errors as Record<string, string>;

    return (
        <>
            <Head title="Users" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <Heading
                    className="mb-0"
                    title="Users"
                    description="Assign roles, soft delete, or permanently remove users."
                />

                <InputError message={pageErrors.user} />

                <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full min-w-[820px] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">Email</th>
                                <th className="px-3 py-2 font-medium">Role</th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Created
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr
                                    key={user.id}
                                    className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                >
                                    <td className="px-3 py-2 font-medium">
                                        {user.name}
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {user.email}
                                    </td>
                                    <td className="px-3 py-2">
                                        <RoleForm user={user} roles={roles} />
                                    </td>
                                    <td className="px-3 py-2">
                                        {user.deleted_at ? (
                                            <Badge variant="secondary">
                                                Soft deleted
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline">
                                                Active
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {user.created_at
                                            ? new Date(
                                                  user.created_at,
                                              ).toLocaleDateString(undefined, {
                                                  dateStyle: 'medium',
                                              })
                                            : '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        <UserActions
                                            user={user}
                                            onDelete={(target, mode) =>
                                                setPendingDelete({
                                                    user: target,
                                                    mode,
                                                })
                                            }
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {pendingDelete && (
                <DeleteDialog
                    user={pendingDelete.user}
                    mode={pendingDelete.mode}
                    open={pendingDelete !== null}
                    onOpenChange={(v) => {
                        if (!v) {
                            setPendingDelete(null);
                        }
                    }}
                />
            )}
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Users', href: index.url() },
    ],
};
