import { Head, Link, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { create, index, store, update } from '@/routes/roles';

type RoleFormData = {
    id: number;
    name: string;
    permissions: string[];
    is_system: boolean;
};

export default function RoleFormPage({
    role,
    permissionGroups,
}: {
    role: RoleFormData | null;
    permissionGroups: Record<string, string[]>;
}) {
    const isEdit = role !== null;
    const { data, setData, post, put, processing, errors } = useForm({
        name: role?.name ?? '',
        permissions: role?.permissions ?? [],
    });

    function togglePermission(permission: string, checked: boolean) {
        if (checked) {
            setData('permissions', [...data.permissions, permission]);
            return;
        }

        setData(
            'permissions',
            data.permissions.filter((item) => item !== permission),
        );
    }

    function toggleGroup(permissions: string[], checked: boolean) {
        if (checked) {
            setData(
                'permissions',
                Array.from(new Set([...data.permissions, ...permissions])),
            );
            return;
        }

        setData(
            'permissions',
            data.permissions.filter((item) => !permissions.includes(item)),
        );
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();

        if (isEdit) {
            put(update.url(role.id));
            return;
        }

        post(store.url());
    }

    return (
        <>
            <Head title={isEdit ? `Edit ${role.name}` : 'New role'} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <Heading
                    className="mb-0"
                    title={isEdit ? `Edit ${role.name}` : 'New role'}
                    description="Assign seeded permissions to this role. New permission names require a code change."
                />

                <form
                    onSubmit={submit}
                    className="flex max-w-3xl flex-col gap-6"
                >
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="e.g. auditor"
                            autoFocus={!role?.is_system}
                            disabled={role?.is_system === true}
                        />
                        <p className="text-xs text-muted-foreground">
                            Letters, numbers, dashes and underscores only.
                        </p>
                        <InputError message={errors.name} />
                    </div>

                    <div className="space-y-4">
                        <div>
                            <Label>Permissions</Label>
                            <InputError message={errors.permissions} />
                        </div>

                        {Object.entries(permissionGroups).map(
                            ([group, permissions]) => {
                                const allChecked = permissions.every((p) =>
                                    data.permissions.includes(p),
                                );
                                const someChecked =
                                    !allChecked &&
                                    permissions.some((p) =>
                                        data.permissions.includes(p),
                                    );

                                return (
                                    <div
                                        key={group}
                                        className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                                    >
                                        <div className="mb-3 flex items-center gap-2">
                                            <Checkbox
                                                id={`group-${group}`}
                                                checked={
                                                    allChecked
                                                        ? true
                                                        : someChecked
                                                          ? 'indeterminate'
                                                          : false
                                                }
                                                onCheckedChange={(checked) =>
                                                    toggleGroup(
                                                        permissions,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            <Label
                                                htmlFor={`group-${group}`}
                                                className="cursor-pointer font-medium capitalize"
                                            >
                                                {group.replaceAll('-', ' ')}
                                            </Label>
                                        </div>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {permissions.map((permission) => (
                                                <div
                                                    key={permission}
                                                    className="flex items-center gap-2"
                                                >
                                                    <Checkbox
                                                        id={permission}
                                                        checked={data.permissions.includes(
                                                            permission,
                                                        )}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            togglePermission(
                                                                permission,
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                    />
                                                    <Label
                                                        htmlFor={permission}
                                                        className="cursor-pointer font-normal"
                                                    >
                                                        {permission}
                                                    </Label>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                );
                            },
                        )}
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>
                            {processing
                                ? 'Saving…'
                                : isEdit
                                  ? 'Save role'
                                  : 'Create role'}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href={index.url()}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

RoleFormPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Roles', href: index.url() },
        { title: 'Form', href: create.url() },
    ],
};
