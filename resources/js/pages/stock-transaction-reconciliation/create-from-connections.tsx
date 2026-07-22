import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { storeFromConnections } from '@/actions/App/Http/Controllers/StockTransactionReconciliationController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    create,
    createFromConnections,
    index,
} from '@/routes/stock-transaction-reconciliation';

type OrgConnection = {
    id: number;
    type: string;
    host_masked: string;
    is_active: boolean;
};

type OrganizationOption = {
    id: number;
    name: string;
    ba_code: string;
    vendor_id: number;
    has_db_name: boolean;
    pgsql_connections: OrgConnection[];
};

function connectionLabel(connection: OrgConnection): string {
    return `${connection.type} · ${connection.host_masked}`;
}

function defaultPgsqlConnectionId(
    organization: OrganizationOption | undefined,
): string {
    return organization?.pgsql_connections[0]?.id.toString() ?? '';
}

function pad2(value: number): string {
    return String(value).padStart(2, '0');
}

/** Fixed format matching backend `Y-m-d h:i A` — no locale APIs (hydration-safe after mount). */
function formatSessionStamp(date: Date = new Date()): string {
    let hour = date.getHours();
    const ampm = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12;

    return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())} ${pad2(hour)}:${pad2(date.getMinutes())} ${ampm}`;
}

function autoSessionName(organization: OrganizationOption | undefined): string {
    if (!organization) {
        return '';
    }

    return `${organization.name} · connection stock · ${formatSessionStamp()}`;
}

export default function StockReconciliationCreateFromConnections({
    organizations,
    suggestedSessionName,
}: {
    organizations: OrganizationOption[];
    suggestedSessionName: string;
}) {
    const initialOrganization = organizations[0];

    const { data, setData, post, processing, errors } = useForm({
        name: suggestedSessionName,
        organization_id: initialOrganization?.id?.toString() ?? '',
        pgsql_connection_id: defaultPgsqlConnectionId(initialOrganization),
        include_zwing: true,
        include_erp: true,
    });

    const selectedOrganization = useMemo(
        () =>
            organizations.find(
                (organization) =>
                    organization.id.toString() === data.organization_id,
            ) ?? null,
        [organizations, data.organization_id],
    );

    const pgsqlConnections = selectedOrganization?.pgsql_connections ?? [];

    function selectOrganization(organizationId: string) {
        const organization = organizations.find(
            (item) => item.id.toString() === organizationId,
        );

        setData((current) => ({
            ...current,
            organization_id: organizationId,
            pgsql_connection_id: defaultPgsqlConnectionId(organization),
            name: autoSessionName(organization),
        }));
    }

    const canSubmit =
        data.organization_id !== '' &&
        (data.include_zwing || data.include_erp) &&
        (!data.include_erp || data.pgsql_connection_id !== '');

    function submit(e: React.FormEvent) {
        e.preventDefault();

        if (!canSubmit) {
            return;
        }

        post(storeFromConnections.url());
    }

    return (
        <>
            <Head title="New reconciliation from connections" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            New reconciliation from connections
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Zwing uses shared mysql_ssh + org db_name. ERP uses
                            org Postgres connection. CSV upload unchanged.
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={create.url()}>Use CSV upload instead</Link>
                    </Button>
                </div>

                {organizations.length === 0 ? (
                    <p className="rounded-md border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                        No organizations with a MySQL database name. Attach a
                        Zwing vendor (with db_name) first. Add a pgsql
                        connection on the org for ERP pull.
                    </p>
                ) : (
                    <form onSubmit={submit} className="flex flex-col gap-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="session-name">
                                    Session name
                                </Label>
                                <Input
                                    id="session-name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="Auto-filled from organization"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Auto-filled when org changes. Edit anytime.
                                    Blank on submit uses auto name.
                                </p>
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label>
                                    Organization{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Select
                                    value={data.organization_id}
                                    onValueChange={selectOrganization}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select organization" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {organizations.map((organization) => (
                                            <SelectItem
                                                key={organization.id}
                                                value={organization.id.toString()}
                                            >
                                                {organization.name} · Vendor{' '}
                                                {organization.vendor_id}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.organization_id} />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div
                                className={`flex flex-col gap-3 rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border ${!data.include_zwing ? 'opacity-60' : ''}`}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="font-medium">
                                            Zwing stock (MySQL)
                                        </p>
                                        <p className="mt-0.5 text-sm text-muted-foreground">
                                            Shared mysql_ssh tunnel + org
                                            db_name
                                        </p>
                                    </div>
                                    <Checkbox
                                        checked={data.include_zwing}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'include_zwing',
                                                checked === true,
                                            )
                                        }
                                    />
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    No mysql connection pick. Uses default
                                    mysql_ssh from env and this
                                    organization&apos;s database name.
                                </p>
                            </div>

                            <div
                                className={`flex flex-col gap-3 rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border ${!data.include_erp ? 'opacity-60' : ''}`}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="font-medium">
                                            ERP stock (Postgres)
                                        </p>
                                        <p className="mt-0.5 text-sm text-muted-foreground">
                                            Sites from mysql_ssh stores, stock
                                            from org pgsql
                                        </p>
                                    </div>
                                    <Checkbox
                                        checked={data.include_erp}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'include_erp',
                                                checked === true,
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Postgres connection</Label>
                                    <Select
                                        value={data.pgsql_connection_id}
                                        onValueChange={(value) =>
                                            setData(
                                                'pgsql_connection_id',
                                                value,
                                            )
                                        }
                                        disabled={!data.include_erp}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select pgsql connection" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {pgsqlConnections.map(
                                                (connection) => (
                                                    <SelectItem
                                                        key={connection.id}
                                                        value={connection.id.toString()}
                                                    >
                                                        <span className="font-mono text-xs tracking-tight">
                                                            {connectionLabel(
                                                                connection,
                                                            )}
                                                        </span>
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.pgsql_connection_id}
                                    />
                                    {pgsqlConnections.length === 0 && (
                                        <p className="text-xs text-muted-foreground">
                                            No active pgsql connection on this
                                            organization.
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>

                        <InputError message={errors.include_zwing} />

                        <div className="flex gap-2">
                            <Button
                                type="submit"
                                disabled={!canSubmit || processing}
                            >
                                {processing
                                    ? 'Starting…'
                                    : 'Start connection pull'}
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={index.url()}>Cancel</Link>
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}

StockReconciliationCreateFromConnections.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Stock reconciliation', href: index.url() },
        {
            title: 'From connections',
            href: createFromConnections.url(),
        },
    ],
};
