import { Head, Link, useForm } from '@inertiajs/react';
import { Database, Pencil, Plus, Trash2, Unplug } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import {
    destroy as destroyDatabaseConnection,
    store as storeDatabaseConnection,
    test as testDatabaseConnection,
    update as updateDatabaseConnection,
} from '@/actions/App/Http/Controllers/OrganizationDatabaseConnectionController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { resolveExternalQueryResponse, xsrfToken } from '@/lib/external-query';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import {
    index as organizationsIndex,
    show as organizationShow,
} from '@/routes/organizations';
import { index as databaseConnectionsIndex } from '@/routes/organizations/database-connections';

type DatabaseConnection = {
    id: number;
    type: string;
    username: string;
    host: string | null;
    port: number | null;
    is_active: boolean;
};

type Organization = {
    id: number;
    name: string;
    ba_code: string;
    vendor_id: number;
};

function DatabaseConnectionDialog({
    organizationId,
    connection,
    types,
    usedTypes,
    open,
    onOpenChange,
}: {
    organizationId: number;
    connection: DatabaseConnection | null;
    types: string[];
    usedTypes: string[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const isEdit = connection !== null;
    const availableTypes = isEdit
        ? types
        : types.filter((type) => !usedTypes.includes(type));

    const form = useForm({
        type: connection?.type ?? availableTypes[0] ?? '',
        database_name: '',
        username: connection?.username ?? '',
        password: '',
        host: connection?.host ?? '',
        port: connection?.port?.toString() ?? '',
        is_active: connection?.is_active ?? true,
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData({
            type: connection?.type ?? availableTypes[0] ?? '',
            database_name: '',
            username: connection?.username ?? '',
            password: '',
            host: connection?.host ?? '',
            port: connection?.port?.toString() ?? '',
            is_active: connection?.is_active ?? true,
        });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reset only when dialog opens / target changes
    }, [open, connection?.id]);

    function submit(e: React.FormEvent) {
        e.preventDefault();

        if (isEdit && connection) {
            form.put(
                updateDatabaseConnection.url({
                    organization: organizationId,
                    organizationDatabaseConnection: connection.id,
                }),
                {
                    onSuccess: () => onOpenChange(false),
                },
            );
            return;
        }

        form.post(storeDatabaseConnection.url(organizationId), {
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {isEdit
                            ? 'Edit database connection'
                            : 'Add database connection'}
                    </DialogTitle>
                    <DialogDescription>
                        Per-org host + login. App connects through shared SSH
                        bastion (not direct from this machine).
                    </DialogDescription>
                </DialogHeader>

                {availableTypes.length === 0 && !isEdit ? (
                    <p className="text-sm text-muted-foreground">
                        All connection types already configured for this
                        organization.
                    </p>
                ) : (
                    <form
                        onSubmit={submit}
                        className="flex flex-col gap-3"
                        id="database-connection-form"
                    >
                        <div className="space-y-1">
                            <Label className="text-xs">Type</Label>
                            <Select
                                value={form.data.type}
                                onValueChange={(value) =>
                                    form.setData('type', value)
                                }
                                disabled={isEdit}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableTypes.map((type) => (
                                        <SelectItem key={type} value={type}>
                                            {type}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.type} />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">
                                Database name
                                {isEdit ? ' — leave blank to keep' : ''}
                            </Label>
                            <Input
                                type="password"
                                value={form.data.database_name}
                                onChange={(e) =>
                                    form.setData(
                                        'database_name',
                                        e.target.value,
                                    )
                                }
                                autoComplete="off"
                            />
                            <InputError message={form.errors.database_name} />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">Username</Label>
                            <Input
                                value={form.data.username}
                                onChange={(e) =>
                                    form.setData('username', e.target.value)
                                }
                                autoComplete="off"
                            />
                            <InputError message={form.errors.username} />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">
                                Password
                                {isEdit ? ' — leave blank to keep' : ''}
                            </Label>
                            <Input
                                type="password"
                                value={form.data.password}
                                onChange={(e) =>
                                    form.setData('password', e.target.value)
                                }
                                autoComplete="new-password"
                            />
                            <InputError message={form.errors.password} />
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-1">
                                <Label className="text-xs">
                                    Remote DB host (via SSH)
                                </Label>
                                <Input
                                    value={form.data.host}
                                    onChange={(e) =>
                                        form.setData('host', e.target.value)
                                    }
                                    placeholder="pgflex-....postgres.database.azure.com"
                                />
                                <InputError message={form.errors.host} />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs">
                                    Remote DB port
                                </Label>
                                <Input
                                    type="number"
                                    value={form.data.port}
                                    onChange={(e) =>
                                        form.setData('port', e.target.value)
                                    }
                                    placeholder="5432"
                                />
                                <InputError message={form.errors.port} />
                            </div>
                        </div>
                        <label className="flex items-center gap-2 text-xs">
                            <input
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(e) =>
                                    form.setData('is_active', e.target.checked)
                                }
                                className="size-4 rounded border"
                            />
                            Active
                        </label>
                    </form>
                )}

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    {(isEdit || availableTypes.length > 0) && (
                        <Button
                            type="submit"
                            form="database-connection-form"
                            disabled={form.processing}
                        >
                            {isEdit ? 'Update connection' : 'Save connection'}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function DeleteConnectionDialog({
    organizationId,
    connection,
    open,
    onOpenChange,
}: {
    organizationId: number;
    connection: DatabaseConnection | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { delete: deleteConnection, processing } = useForm({});

    function confirm() {
        if (!connection) {
            return;
        }

        const connectionId = connection.id;

        deleteConnection(
            destroyDatabaseConnection.url({
                organization: organizationId,
                organizationDatabaseConnection: connectionId,
            }),
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            },
        );
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove database connection?</DialogTitle>
                    <DialogDescription>
                        This will permanently remove the{' '}
                        <span className="font-medium text-foreground">
                            {connection?.type ?? 'database'}
                        </span>{' '}
                        connection for this organization. This action cannot be
                        undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={confirm}
                        disabled={processing || connection === null}
                    >
                        {processing ? 'Removing…' : 'Remove'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function OrganizationDatabaseConnections({
    organization,
    databaseConnections,
    databaseConnectionTypes,
}: {
    organization: Organization;
    databaseConnections: DatabaseConnection[];
    databaseConnectionTypes: string[];
}) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingConnection, setEditingConnection] =
        useState<DatabaseConnection | null>(null);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deletingConnection, setDeletingConnection] =
        useState<DatabaseConnection | null>(null);
    const [testingConnectionId, setTestingConnectionId] = useState<
        number | null
    >(null);
    const testing = testingConnectionId !== null;

    const usedTypes = databaseConnections.map((connection) => connection.type);
    const canAdd = databaseConnectionTypes.some(
        (type) => !usedTypes.includes(type),
    );

    function openCreate() {
        setEditingConnection(null);
        setDialogOpen(true);
    }

    function openEdit(connection: DatabaseConnection) {
        setEditingConnection(connection);
        setDialogOpen(true);
    }

    function openDelete(connection: DatabaseConnection) {
        setDeletingConnection(connection);
        setDeleteDialogOpen(true);
    }

    async function runTest(connection: DatabaseConnection) {
        setTestingConnectionId(connection.id);

        try {
            const response = await fetch(
                testDatabaseConnection.url({
                    organization: organization.id,
                    organizationDatabaseConnection: connection.id,
                }),
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                },
            );

            if (!response.ok && response.status !== 202) {
                throw new Error(`Connection test failed (${response.status})`);
            }

            const settled = await resolveExternalQueryResponse(response);
            const result = settled.result as {
                ok?: boolean;
                message?: string;
                latency_ms?: number | null;
            };

            const latency =
                typeof result.latency_ms === 'number'
                    ? ` (${result.latency_ms} ms)`
                    : '';
            const message = `${result.message ?? 'Connection test finished.'}${latency}`;

            if (result.ok) {
                toast.success(message);
            } else {
                toast.error(message);
            }
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Connection test failed.',
            );
        } finally {
            setTestingConnectionId(null);
        }
    }

    return (
        <>
            <Head title={`${organization.name} — Database connections`} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Database connections"
                        description={`${organization.name} · BA ${organization.ba_code} · Vendor ${organization.vendor_id}`}
                    />
                    <div className="flex gap-2">
                        <Link href={organizationShow.url(organization.id)}>
                            <Button variant="outline" size="sm">
                                Back to organization
                            </Button>
                        </Link>
                        <Button
                            size="sm"
                            onClick={openCreate}
                            disabled={!canAdd}
                        >
                            <Plus className="size-4" />
                            Add connection
                        </Button>
                    </div>
                </div>

                {databaseConnections.length === 0 ? (
                    <p className="rounded-md border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                        No database connections yet. Add a mysql or pgsql login
                        for this organization.
                    </p>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {databaseConnections.map((connection) => (
                            <div
                                key={connection.id}
                                className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <p className="flex items-center gap-2 font-medium">
                                            <Database className="size-4" />
                                            {connection.type}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            User {connection.username}
                                            {connection.host
                                                ? ` · ${connection.host}${connection.port ? `:${connection.port}` : ''} via SSH`
                                                : ' · default remote host via SSH'}
                                        </p>
                                    </div>
                                    <Badge
                                        variant={
                                            connection.is_active
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {connection.is_active
                                            ? 'Active'
                                            : 'Inactive'}
                                    </Badge>
                                </div>
                                <div className="mt-3 flex flex-wrap gap-2 border-t pt-3">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        disabled={
                                            testing &&
                                            testingConnectionId ===
                                                connection.id
                                        }
                                        onClick={() => runTest(connection)}
                                    >
                                        <Unplug className="size-4" />
                                        {testing &&
                                        testingConnectionId === connection.id
                                            ? 'Testing…'
                                            : 'Test connection'}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => openEdit(connection)}
                                    >
                                        <Pencil className="size-4" />
                                        Edit
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        className="text-destructive"
                                        onClick={() => openDelete(connection)}
                                    >
                                        <Trash2 className="size-4" />
                                        Remove
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <DatabaseConnectionDialog
                organizationId={organization.id}
                connection={editingConnection}
                types={databaseConnectionTypes}
                usedTypes={usedTypes}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
            />

            <DeleteConnectionDialog
                organizationId={organization.id}
                connection={deletingConnection}
                open={deleteDialogOpen}
                onOpenChange={(open) => {
                    setDeleteDialogOpen(open);
                    if (!open) {
                        setDeletingConnection(null);
                    }
                }}
            />
        </>
    );
}

OrganizationDatabaseConnections.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Organizations', href: organizationsIndex.url() },
        { title: 'Organization', href: organizationShow.url(0) },
        {
            title: 'Database connections',
            href: databaseConnectionsIndex.url(0),
        },
    ],
};
