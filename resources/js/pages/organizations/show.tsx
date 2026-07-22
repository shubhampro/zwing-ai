import { Head, Link, useForm } from '@inertiajs/react';
import { Database, Pencil, Plug, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    destroyForOrganization as destroyConnection,
    storeForOrganization as storeConnection,
    updateForOrganization as updateConnection,
} from '@/actions/App/Http/Controllers/OrganizationThirdPartyApiController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { edit, index, show } from '@/routes/organizations';
import { index as databaseConnectionsIndex } from '@/routes/organizations/database-connections';

type Connection = { id: number; base_url: string; is_active: boolean };
type ApiApp = {
    id: number;
    name: string;
    path: string;
    method: string;
    auth_header_name: string;
    param_count: number;
    connection: Connection | null;
};

type Organization = {
    id: number;
    name: string;
    ba_code: string;
    vendor_id: number;
};

function ConnectionForm({
    organizationId,
    api,
    connection,
    onCancel,
}: {
    organizationId: number;
    api: ApiApp;
    connection: Connection | null;
    onCancel?: () => void;
}) {
    const isEdit = connection !== null;
    const form = useForm({
        third_party_api_id: String(api.id),
        base_url: connection?.base_url ?? '',
        auth_token: '',
        is_active: connection?.is_active ?? true,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (isEdit && connection) {
            form.put(
                updateConnection.url({
                    organization: organizationId,
                    organizationThirdPartyApi: connection.id,
                }),
                {
                    onSuccess: () => onCancel?.(),
                },
            );
            return;
        }
        form.post(storeConnection.url(organizationId), {
            onSuccess: () => form.reset('auth_token'),
        });
    }

    return (
        <form
            onSubmit={submit}
            className="mt-3 flex flex-col gap-3 border-t pt-3"
        >
            <div className="space-y-1">
                <Label className="text-xs">Base URL</Label>
                <Input
                    value={form.data.base_url}
                    onChange={(e) => form.setData('base_url', e.target.value)}
                    placeholder="https://api.example.com"
                />
                <InputError message={form.errors.base_url} />
            </div>
            <div className="space-y-1">
                <Label className="text-xs">
                    Token ({api.auth_header_name})
                    {isEdit ? ' — leave blank to keep' : ''}
                </Label>
                <Input
                    type="password"
                    value={form.data.auth_token}
                    onChange={(e) => form.setData('auth_token', e.target.value)}
                />
                <InputError
                    message={
                        form.errors.auth_token || form.errors.third_party_api_id
                    }
                />
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
            <div className="flex gap-2">
                <Button type="submit" size="sm" disabled={form.processing}>
                    {isEdit ? 'Update connection' : 'Save connection'}
                </Button>
                {onCancel && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onCancel}
                    >
                        Cancel
                    </Button>
                )}
            </div>
        </form>
    );
}

export default function OrganizationsShow({
    organization,
    apiApps,
    canManageDatabaseConnections = false,
}: {
    organization: Organization;
    apiApps: ApiApp[];
    canManageDatabaseConnections?: boolean;
}) {
    const [editingApiId, setEditingApiId] = useState<number | null>(null);
    const { delete: deleteConnection, processing } = useForm();

    return (
        <>
            <Head title={organization.name} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {organization.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            BA {organization.ba_code} · Vendor{' '}
                            {organization.vendor_id}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {canManageDatabaseConnections && (
                            <Link
                                href={databaseConnectionsIndex.url(
                                    organization.id,
                                )}
                            >
                                <Button variant="outline" size="sm">
                                    <Database className="size-4" />
                                    Database connections
                                </Button>
                            </Link>
                        )}
                        <Link href={edit.url(organization.id)}>
                            <Button variant="outline" size="sm">
                                <Pencil className="size-4" />
                                Edit details
                            </Button>
                        </Link>
                    </div>
                </div>

                <section className="flex flex-col gap-4">
                    <div>
                        <h2 className="flex items-center gap-2 text-lg font-semibold">
                            <Plug className="size-5" />
                            API connections
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Set base URL + token per app template for this
                            organization.
                        </p>
                    </div>

                    {apiApps.length === 0 && (
                        <p className="rounded-md border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                            No API templates yet. Add templates under Third
                            party APIs first.
                        </p>
                    )}

                    <div className="grid gap-4 lg:grid-cols-2">
                        {apiApps.map((api) => (
                            <div
                                key={api.id}
                                className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <p className="font-medium">
                                            {api.name}
                                        </p>
                                        <p className="mt-1 font-mono text-xs text-muted-foreground">
                                            {api.method} {api.path}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {api.param_count} params · header{' '}
                                            {api.auth_header_name}
                                        </p>
                                    </div>
                                    <Badge
                                        variant={
                                            api.connection?.is_active
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {api.connection
                                            ? api.connection.is_active
                                                ? 'Connected'
                                                : 'Inactive'
                                            : 'Not set'}
                                    </Badge>
                                </div>

                                {api.connection && editingApiId !== api.id ? (
                                    <div className="mt-3 border-t pt-3">
                                        <p className="text-xs text-muted-foreground">
                                            Base URL
                                        </p>
                                        <p className="font-mono text-sm">
                                            {api.connection.base_url}
                                        </p>
                                        <p className="mt-2 font-mono text-xs text-muted-foreground">
                                            Full: {api.connection.base_url}
                                            {api.path}
                                        </p>
                                        <div className="mt-3 flex gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setEditingApiId(api.id)
                                                }
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="text-destructive"
                                                disabled={processing}
                                                onClick={() =>
                                                    deleteConnection(
                                                        destroyConnection.url({
                                                            organization:
                                                                organization.id,
                                                            organizationThirdPartyApi:
                                                                api.connection!
                                                                    .id,
                                                        }),
                                                    )
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                                Remove
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <ConnectionForm
                                        organizationId={organization.id}
                                        api={api}
                                        connection={
                                            editingApiId === api.id
                                                ? api.connection
                                                : null
                                        }
                                        onCancel={
                                            editingApiId === api.id
                                                ? () => setEditingApiId(null)
                                                : undefined
                                        }
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                </section>
            </div>
        </>
    );
}

OrganizationsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Organizations', href: index.url() },
        { title: 'View', href: show.url(0) },
    ],
};
