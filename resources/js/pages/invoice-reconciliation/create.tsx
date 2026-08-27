import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useRef } from 'react';
import { flushSync } from 'react-dom';
import {
    storeFromConnections,
    uploadCsv,
} from '@/actions/App/Http/Controllers/InvoiceReconciliationController';
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
import { create, index } from '@/routes/invoice-reconciliation';

const REQUIRED_COLUMNS = [
    'invoice_id',
    'ref_id',
    'total_amount',
    'status',
] as const;

const MAX_FILE_SIZE_MB = 512;
const MAX_FILE_SIZE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024;

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

function isoDate(date: Date): string {
    return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
}

function defaultDateFrom(): string {
    const date = new Date();
    date.setDate(1);

    return isoDate(date);
}

function defaultDateTo(): string {
    return isoDate(new Date());
}

function formatSessionStamp(date: Date = new Date()): string {
    let hour = date.getHours();
    const ampm = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12;

    return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())} ${pad2(hour)}:${pad2(date.getMinutes())} ${ampm}`;
}

function autoSessionName(
    organization: OrganizationOption | undefined,
    dateFrom: string,
    dateTo: string,
): string {
    if (!organization || dateFrom === '' || dateTo === '') {
        return '';
    }

    return `${organization.name} · Invoice · ${dateFrom} to ${dateTo} · ${formatSessionStamp()}`;
}

function RequiredColumnsHint() {
    return (
        <div className="rounded-md bg-muted/60 px-3 py-2">
            <p className="mb-1.5 text-xs font-medium text-muted-foreground">
                Required columns
            </p>
            <div className="flex flex-wrap gap-1.5">
                {REQUIRED_COLUMNS.map((col) => (
                    <code
                        key={col}
                        className="rounded bg-background px-1.5 py-0.5 font-mono text-xs"
                    >
                        {col === 'ref_id' ? 'ref_id (Mop Ref id)' : col}
                    </code>
                ))}
            </div>
            <p className="mt-2 text-xs text-muted-foreground">
                Multiple Mop Ref ids on one invoice → separate with{' '}
                <code className="rounded bg-muted px-1 font-mono text-xs">
                    -
                </code>{' '}
                (e.g.{' '}
                <code className="rounded bg-muted px-1 font-mono text-xs">
                    22-21
                </code>
                )
            </p>
            <pre className="mt-1 overflow-x-auto rounded bg-background px-2 py-1.5 font-mono text-xs">
                {`invoice_id,ref_id,total_amount,status\nPMM3001252800002,22-21,55000,Void`}
            </pre>
        </div>
    );
}

export default function InvoiceReconciliationCreate({
    organizations = [],
}: {
    organizations?: OrganizationOption[];
}) {
    const zwingInputRef = useRef<HTMLInputElement>(null);
    const erpInputRef = useRef<HTMLInputElement>(null);

    const csvForm = useForm<{
        name: string;
        v_id: string;
        zwing_csv: File | null;
        erp_csv: File | null;
    }>({
        name: '',
        v_id: '',
        zwing_csv: null,
        erp_csv: null,
    });

    const connectionForm = useForm({
        name: '',
        organization_id: '',
        pgsql_connection_id: '',
        date_from: defaultDateFrom(),
        date_to: defaultDateTo(),
        include_zwing: true,
        include_erp: true,
    });

    const sourceForm = useForm({
        source: 'connection' as 'connection' | 'csv',
    });

    const selectedOrganization = useMemo(
        () =>
            organizations.find(
                (organization) =>
                    organization.id.toString() ===
                    connectionForm.data.organization_id,
            ) ?? null,
        [organizations, connectionForm.data.organization_id],
    );

    const pgsqlConnections = selectedOrganization?.pgsql_connections ?? [];
    const hasOrganization = connectionForm.data.organization_id !== '';
    const hasDates =
        connectionForm.data.date_from !== '' &&
        connectionForm.data.date_to !== '';

    function selectOrganization(organizationId: string) {
        const organization = organizations.find(
            (item) => item.id.toString() === organizationId,
        );

        connectionForm.setData((current) => ({
            ...current,
            organization_id: organizationId,
            pgsql_connection_id: defaultPgsqlConnectionId(organization),
            name: autoSessionName(
                organization,
                current.date_from,
                current.date_to,
            ),
        }));
    }

    function updateDate(field: 'date_from' | 'date_to', value: string) {
        connectionForm.setData((current) => {
            const next = { ...current, [field]: value };
            const organization = organizations.find(
                (item) => item.id.toString() === next.organization_id,
            );

            return {
                ...next,
                name: autoSessionName(
                    organization,
                    next.date_from,
                    next.date_to,
                ),
            };
        });
    }

    const canSubmitConnection =
        hasOrganization &&
        hasDates &&
        (connectionForm.data.include_zwing ||
            connectionForm.data.include_erp) &&
        (!connectionForm.data.include_erp ||
            connectionForm.data.pgsql_connection_id !== '');

    const hasBothCsvs =
        csvForm.data.zwing_csv !== null && csvForm.data.erp_csv !== null;

    function handleFileChange(
        field: 'zwing_csv' | 'erp_csv',
        file: File | null,
    ) {
        csvForm.clearErrors(field);
        if (file && file.size > MAX_FILE_SIZE_BYTES) {
            csvForm.setError(
                field,
                `File is too large (${(file.size / 1024 / 1024).toFixed(1)} MB). Maximum allowed size is ${MAX_FILE_SIZE_MB} MB.`,
            );
            csvForm.setData(field, null);
            return;
        }
        csvForm.setData(field, file);
    }

    function submitConnection(e: React.FormEvent) {
        e.preventDefault();

        if (!canSubmitConnection) {
            return;
        }

        connectionForm.post(storeFromConnections.url());
    }

    function submitCsv(e: React.FormEvent) {
        e.preventDefault();

        const zwing_csv = zwingInputRef.current?.files?.[0] ?? null;
        const erp_csv = erpInputRef.current?.files?.[0] ?? null;

        if (!zwing_csv || !erp_csv || csvForm.processing) {
            return;
        }

        flushSync(() => {
            csvForm.setData({
                name: csvForm.data.name,
                v_id: csvForm.data.v_id,
                zwing_csv,
                erp_csv,
            });
        });

        csvForm.post(uploadCsv.url(), { forceFormData: true });
    }

    return (
        <>
            <Head title="New invoice reconciliation" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        New invoice reconciliation
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Pull from organization connections with a date range, or
                        upload Zwing and ERP CSVs.
                    </p>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant={
                            sourceForm.data.source === 'connection'
                                ? 'default'
                                : 'outline'
                        }
                        size="sm"
                        onClick={() => sourceForm.setData('source', 'connection')}
                    >
                        Connection
                    </Button>
                    <Button
                        type="button"
                        variant={
                            sourceForm.data.source === 'csv'
                                ? 'default'
                                : 'outline'
                        }
                        size="sm"
                        onClick={() => sourceForm.setData('source', 'csv')}
                    >
                        CSV
                    </Button>
                </div>

                {sourceForm.data.source === 'connection' ? (
                    organizations.length === 0 ? (
                        <p className="rounded-lg border border-dashed px-6 py-12 text-center text-base text-muted-foreground">
                            No organizations with a MySQL database name. Attach
                            a Zwing vendor first. Add a pgsql connection for
                            ERP.
                        </p>
                    ) : (
                        <form
                            onSubmit={submitConnection}
                            className="flex max-w-4xl flex-col gap-8"
                        >
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label className="text-sm">
                                        1. Organization{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Select
                                        value={
                                            connectionForm.data.organization_id
                                        }
                                        onValueChange={selectOrganization}
                                    >
                                        <SelectTrigger className="h-11">
                                            <SelectValue placeholder="Select organization" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {organizations.map(
                                                (organization) => (
                                                    <SelectItem
                                                        key={organization.id}
                                                        value={organization.id.toString()}
                                                    >
                                                        {organization.name} ·
                                                        Vendor{' '}
                                                        {organization.vendor_id}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={
                                            connectionForm.errors
                                                .organization_id
                                        }
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="date-from"
                                            className="text-sm"
                                        >
                                            2. Date from{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="date-from"
                                            type="date"
                                            className="h-11"
                                            value={
                                                connectionForm.data.date_from
                                            }
                                            onChange={(e) =>
                                                updateDate(
                                                    'date_from',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                connectionForm.errors.date_from
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="date-to"
                                            className="text-sm"
                                        >
                                            Date to{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="date-to"
                                            type="date"
                                            className="h-11"
                                            value={connectionForm.data.date_to}
                                            onChange={(e) =>
                                                updateDate(
                                                    'date_to',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                connectionForm.errors.date_to
                                            }
                                        />
                                    </div>
                                </div>
                            </div>

                            {hasOrganization && (
                                <>
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="connection-session-name"
                                            className="text-sm"
                                        >
                                            3. Session name
                                        </Label>
                                        <Input
                                            id="connection-session-name"
                                            className="h-11"
                                            value={connectionForm.data.name}
                                            onChange={(e) =>
                                                connectionForm.setData(
                                                    'name',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={connectionForm.errors.name}
                                        />
                                    </div>

                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <label className="flex min-h-24 items-start gap-3 rounded-lg border border-sidebar-border/70 p-5 text-base dark:border-sidebar-border">
                                            <Checkbox
                                                className="mt-1"
                                                checked={
                                                    connectionForm.data
                                                        .include_zwing
                                                }
                                                onCheckedChange={(checked) =>
                                                    connectionForm.setData(
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
                                                    checked={
                                                        connectionForm.data
                                                            .include_erp
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        connectionForm.setData(
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

                                            {connectionForm.data
                                                .include_erp && (
                                                <div className="space-y-2 pl-7">
                                                    <Label className="text-sm">
                                                        Postgres connection
                                                    </Label>
                                                    <Select
                                                        value={
                                                            connectionForm.data
                                                                .pgsql_connection_id
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            connectionForm.setData(
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
                                                                (
                                                                    connection,
                                                                ) => (
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
                                                            connectionForm
                                                                .errors
                                                                .pgsql_connection_id
                                                        }
                                                    />
                                                </div>
                                            )}
                                        </label>
                                    </div>

                                    <InputError
                                        message={
                                            connectionForm.errors.include_zwing
                                        }
                                    />

                                    <div className="flex gap-3">
                                        <Button
                                            type="submit"
                                            size="lg"
                                            disabled={
                                                !canSubmitConnection ||
                                                connectionForm.processing
                                            }
                                        >
                                            {connectionForm.processing
                                                ? 'Starting…'
                                                : 'Create invoice pull'}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="lg"
                                            asChild
                                        >
                                            <Link href={index.url()}>
                                                Cancel
                                            </Link>
                                        </Button>
                                    </div>
                                </>
                            )}
                        </form>
                    )
                ) : (
                    <form
                        onSubmit={submitCsv}
                        className="flex flex-col gap-6"
                    >
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="session-name">
                                    Session name{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="session-name"
                                    placeholder="e.g. May 2026 invoice check"
                                    value={csvForm.data.name}
                                    onChange={(e) =>
                                        csvForm.setData('name', e.target.value)
                                    }
                                />
                                <InputError message={csvForm.errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="session-vid">
                                    Vendor ID{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="session-vid"
                                    type="number"
                                    min={1}
                                    placeholder="e.g. 147"
                                    value={csvForm.data.v_id}
                                    onChange={(e) =>
                                        csvForm.setData('v_id', e.target.value)
                                    }
                                />
                                <InputError message={csvForm.errors.v_id} />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="flex flex-col gap-3 rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                                <div>
                                    <p className="font-medium">Zwing (POS)</p>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        MySQL / vendor-side invoice export
                                    </p>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="invoice-csv-zwing">
                                        CSV file
                                    </Label>
                                    <input
                                        ref={zwingInputRef}
                                        id="invoice-csv-zwing"
                                        name="zwing_csv"
                                        type="file"
                                        accept=".csv,.txt,text/csv"
                                        className="w-full text-sm text-foreground file:me-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:text-foreground"
                                        onChange={(e) =>
                                            handleFileChange(
                                                'zwing_csv',
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={csvForm.errors.zwing_csv}
                                    />
                                </div>
                                <RequiredColumnsHint />
                                {csvForm.progress && (
                                    <div className="space-y-1">
                                        <p className="text-xs text-muted-foreground">
                                            Uploading…
                                        </p>
                                        <progress
                                            className="h-1.5 w-full overflow-hidden rounded"
                                            value={csvForm.progress.percentage}
                                            max="100"
                                        />
                                    </div>
                                )}
                            </div>

                            <div className="flex flex-col gap-3 rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                                <div>
                                    <p className="font-medium">ERP</p>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        PostgreSQL / central invoice export
                                    </p>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="invoice-csv-erp">
                                        CSV file
                                    </Label>
                                    <input
                                        ref={erpInputRef}
                                        id="invoice-csv-erp"
                                        name="erp_csv"
                                        type="file"
                                        accept=".csv,.txt,text/csv"
                                        className="w-full text-sm text-foreground file:me-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:text-foreground"
                                        onChange={(e) =>
                                            handleFileChange(
                                                'erp_csv',
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={csvForm.errors.erp_csv}
                                    />
                                </div>
                                <RequiredColumnsHint />
                                {csvForm.progress && (
                                    <div className="space-y-1">
                                        <p className="text-xs text-muted-foreground">
                                            Uploading…
                                        </p>
                                        <progress
                                            className="h-1.5 w-full overflow-hidden rounded"
                                            value={csvForm.progress.percentage}
                                            max="100"
                                        />
                                    </div>
                                )}
                            </div>
                        </div>

                        <div>
                            <Button
                                type="submit"
                                size="lg"
                                disabled={csvForm.processing || !hasBothCsvs}
                                className="w-full md:w-auto md:min-w-48"
                            >
                                {csvForm.processing ? 'Working…' : 'Proceed'}
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}

InvoiceReconciliationCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Invoice reconciliation', href: index.url() },
        { title: 'New reconciliation', href: create.url() },
    ],
};
