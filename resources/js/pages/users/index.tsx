import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import { index } from '@/routes/users';
import { update as updateRole } from '@/routes/users/role';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    created_at: string | null;
};

function RoleForm({ user, roles }: { user: UserRow; roles: string[] }) {
    const { data, setData, put, processing, errors } = useForm({
        role: user.role,
    });

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

export default function UsersIndex({
    users,
    roles,
}: {
    users: UserRow[];
    roles: string[];
}) {
    return (
        <>
            <Head title="Users" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <Heading
                    className="mb-0"
                    title="Users"
                    description="Assign global roles that control access across the app."
                />

                <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full min-w-[720px] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">Email</th>
                                <th className="px-3 py-2 font-medium">Role</th>
                                <th className="px-3 py-2 font-medium">
                                    Created
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
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {user.created_at
                                            ? new Date(
                                                  user.created_at,
                                              ).toLocaleDateString(undefined, {
                                                  dateStyle: 'medium',
                                              })
                                            : '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Users', href: index.url() },
    ],
};
