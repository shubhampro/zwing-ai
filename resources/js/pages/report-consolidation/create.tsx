import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { store } from '@/actions/App/Http/Controllers/ReportConsolidationController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { create, index } from '@/routes/report-consolidation';

type OrganizationOption = {
    id: number;
    name: string;
    ba_code: string;
    vendor_id: number;
    has_db_name: boolean;
};

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

    return `${organization.name} · Invoice vs MOP · ${dateFrom} to ${dateTo} · ${formatSessionStamp()}`;
}

export default function ReportConsolidationCreate({
    organizations = [],
}: {
    organizations?: OrganizationOption[];
}) {
    const form = useForm({
        name: '',
        organization_id: '',
        date_from: defaultDateFrom(),
        date_to: defaultDateTo(),
    });

    const selectedOrganization = useMemo(
        () =>
            organizations.find(
                (organization) =>
                    organization.id.toString() === form.data.organization_id,
            ) ?? null,
        [organizations, form.data.organization_id],
    );

    const hasOrganization = form.data.organization_id !== '';
    const hasDates = form.data.date_from !== '' && form.data.date_to !== '';
    const canSubmit = hasOrganization && hasDates;

    function selectOrganization(organizationId: string) {
        const organization = organizations.find(
            (item) => item.id.toString() === organizationId,
        );

        form.setData((current) => ({
            ...current,
            organization_id: organizationId,
            name: autoSessionName(
                organization,
                current.date_from,
                current.date_to,
            ),
        }));
    }

    function updateDate(field: 'date_from' | 'date_to', value: string) {
        form.setData((current) => {
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

    function submit(e: React.FormEvent) {
        e.preventDefault();

        if (!canSubmit) {
            return;
        }

        form.post(store.url());
    }

    return (
        <>
            <Head title="New report consolidation" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        New report consolidation
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Pull Invoice and MOP reports from Zwing for a date
                        range. Totals are compared per invoice.
                    </p>
                </div>

                {organizations.length === 0 ? (
                    <p className="rounded-lg border border-dashed px-6 py-12 text-center text-base text-muted-foreground">
                        No organizations with a MySQL database name. Attach a
                        Zwing vendor first.
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
                                    value={form.data.organization_id}
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
                                <InputError
                                    message={form.errors.organization_id}
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
                                        value={form.data.date_from}
                                        onChange={(e) =>
                                            updateDate(
                                                'date_from',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.date_from}
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
                                        value={form.data.date_to}
                                        onChange={(e) =>
                                            updateDate(
                                                'date_to',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={form.errors.date_to} />
                                </div>
                            </div>
                        </div>

                        {hasOrganization && (
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
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                />
                                <InputError message={form.errors.name} />
                            </div>
                        )}

                        <div>
                            <Button
                                type="submit"
                                disabled={!canSubmit || form.processing}
                            >
                                {form.processing
                                    ? 'Starting…'
                                    : 'Pull Invoice and MOP'}
                            </Button>
                            {selectedOrganization &&
                                !selectedOrganization.has_db_name && (
                                    <p className="mt-2 text-sm text-destructive">
                                        Selected organization has no MySQL
                                        database name.
                                    </p>
                                )}
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}

ReportConsolidationCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Report consolidation', href: index.url() },
        { title: 'New consolidation', href: create.url() },
    ],
};
