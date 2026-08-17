import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { store } from '@/actions/App/Http/Controllers/TransactionReconciliationController';
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
import { create, index } from '@/routes/transaction-reconciliation';

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

type TransactionType = {
    key: string;
    label: string;
    available: boolean;
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

function formatSessionStamp(date: Date = new Date()): string {
    let hour = date.getHours();
    const ampm = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12;

    return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())} ${pad2(hour)}:${pad2(date.getMinutes())} ${ampm}`;
}

function autoSessionName(
    organization: OrganizationOption | undefined,
    typeLabel: string | undefined,
): string {
    if (!organization || !typeLabel) {
        return '';
    }

    return `${organization.name} · ${typeLabel} · ${formatSessionStamp()}`;
}

export default function TransactionReconciliationCreate({
    organizations,
    types,
}: {
    organizations: OrganizationOption[];
    types: TransactionType[];
    suggestedSessionName?: string;
}) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: '',
        organization_id: '',
        pgsql_connection_id: '',
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

    const selectedType = types.find((type) => type.key === data.type);
    const pgsqlConnections = selectedOrganization?.pgsql_connections ?? [];
    const hasOrganization = data.organization_id !== '';
    const hasType = data.type !== '';

    function selectOrganization(organizationId: string) {
        const organization = organizations.find(
            (item) => item.id.toString() === organizationId,
        );

        setData((current) => ({
            ...current,
            organization_id: organizationId,
            pgsql_connection_id: defaultPgsqlConnectionId(organization),
            type: '',
            name: '',
        }));
    }

    function selectType(typeKey: string) {
        const type = types.find((item) => item.key === typeKey);

        setData((current) => ({
            ...current,
            type: typeKey,
            name: autoSessionName(
                selectedOrganization ?? undefined,
                type?.label,
            ),
        }));
    }

    const canSubmit =
        hasOrganization &&
        hasType &&
        (data.include_zwing || data.include_erp) &&
        (!data.include_erp || data.pgsql_connection_id !== '');

    function submit(e: React.FormEvent) {
        e.preventDefault();

        if (!canSubmit) {
            return;
        }

        post(store.url());
    }

    return (
        <>
            <Head title="New transaction reconciliation" />

            <div className="flex flex-col gap-8 p-4 md:p-8">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        New reconciliation
                    </h1>
                    <p className="mt-2 text-base text-muted-foreground">
                        Select organization, then transaction type, then create
                        the pull.
                    </p>
                </div>

                {organizations.length === 0 ? (
                    <p className="rounded-lg border border-dashed px-6 py-12 text-center text-base text-muted-foreground">
                        No organizations with a MySQL database name. Attach a
                        Zwing vendor first. Add a pgsql connection for ERP.
                    </p>
                ) : (
                    <form
                        onSubmit={submit}
                        className="flex max-w-4xl flex-col gap-8"
                    >
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label className="text-sm">
                                    1. Organization{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Select
                                    value={data.organization_id}
                                    onValueChange={selectOrganization}
                                >
                                    <SelectTrigger className="h-11">
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

                            <div className="space-y-2">
                                <Label className="text-sm">
                                    2. Transaction type{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Select
                                    value={data.type}
                                    onValueChange={selectType}
                                    disabled={!hasOrganization}
                                >
                                    <SelectTrigger className="h-11">
                                        <SelectValue placeholder="Packet / GRN / GRT / SPT / CASH" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {types.map((type) => (
                                            <SelectItem
                                                key={type.key}
                                                value={type.key}
                                                disabled={!type.available}
                                            >
                                                {type.label}
                                                {!type.available
                                                    ? ' (soon)'
                                                    : ''}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.type} />
                                {!hasOrganization && (
                                    <p className="text-sm text-muted-foreground">
                                        Select organization first.
                                    </p>
                                )}
                            </div>
                        </div>

                        {hasOrganization && hasType && (
                            <>
                                <div className="space-y-2">
                                    <Label
                                        htmlFor="session-name"
                                        className="text-sm"
                                    >
                                        3. Session name
                                    </Label>
                                    <Input
                                        id="session-name"
                                        className="h-11"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <label className="flex min-h-24 items-start gap-3 rounded-lg border border-sidebar-border/70 p-5 text-base dark:border-sidebar-border">
                                        <Checkbox
                                            className="mt-1"
                                            checked={data.include_zwing}
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'include_zwing',
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <span>
                                            <span className="font-medium">
                                                Zwing
                                            </span>
                                            <span className="mt-1 block text-sm text-muted-foreground">
                                                mysql_ssh + org db_name
                                            </span>
                                        </span>
                                    </label>

                                    <label className="flex min-h-24 flex-col gap-4 rounded-lg border border-sidebar-border/70 p-5 text-base dark:border-sidebar-border">
                                        <span className="flex items-start gap-3">
                                            <Checkbox
                                                className="mt-1"
                                                checked={data.include_erp}
                                                onCheckedChange={(checked) =>
                                                    setData(
                                                        'include_erp',
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            <span>
                                                <span className="font-medium">
                                                    ERP
                                                </span>
                                                <span className="mt-1 block text-sm text-muted-foreground">
                                                    org pgsql connection
                                                </span>
                                            </span>
                                        </span>

                                        {data.include_erp && (
                                            <div className="space-y-2 pl-7">
                                                <Label className="text-sm">
                                                    Postgres connection
                                                </Label>
                                                <Select
                                                    value={
                                                        data.pgsql_connection_id
                                                    }
                                                    onValueChange={(value) =>
                                                        setData(
                                                            'pgsql_connection_id',
                                                            value,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger className="h-11">
                                                        <SelectValue placeholder="Select pgsql connection" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {pgsqlConnections.map(
                                                            (connection) => (
                                                                <SelectItem
                                                                    key={
                                                                        connection.id
                                                                    }
                                                                    value={connection.id.toString()}
                                                                >
                                                                    <span className="font-mono text-sm">
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
                                                    message={
                                                        errors.pgsql_connection_id
                                                    }
                                                />
                                            </div>
                                        )}
                                    </label>
                                </div>

                                <InputError message={errors.include_zwing} />

                                <div className="flex gap-3">
                                    <Button
                                        type="submit"
                                        size="lg"
                                        disabled={!canSubmit || processing}
                                    >
                                        {processing
                                            ? 'Starting…'
                                            : `Create ${selectedType?.label ?? ''} pull`}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="lg"
                                        asChild
                                    >
                                        <Link href={index.url()}>Cancel</Link>
                                    </Button>
                                </div>
                            </>
                        )}
                    </form>
                )}
            </div>
        </>
    );
}

TransactionReconciliationCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Transaction reconciliation', href: index.url() },
        { title: 'New pull', href: create.url() },
    ],
};
