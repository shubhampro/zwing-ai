import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useRef } from 'react';
import { uploadCsv } from '@/actions/App/Http/Controllers/ThirdPartyApiBatchController';
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
import { show as organizationShow } from '@/routes/organizations';
import { index as apisIndex } from '@/routes/third-party-apis';
import { create, index } from '@/routes/third-party-api-batches';

type ApiParam = { key: string; csv_column: string; required: boolean };
type OrganizationOption = { id: number; name: string; ba_code: string };
type ConnectionOption = {
    id: number;
    organization_id: number;
    api_name: string;
    method: string;
    params: ApiParam[];
};

type Props = {
    organizations: OrganizationOption[];
    connections: ConnectionOption[];
};

function firstConnectionId(organizationId: string, connections: ConnectionOption[]): string {
    const match = connections.find((c) => String(c.organization_id) === organizationId);

    return match ? String(match.id) : '';
}

export default function ThirdPartyApiBatchesCreate({ organizations, connections }: Props) {
    const fileRef = useRef<HTMLInputElement>(null);
    const defaultOrganizationId = organizations[0] ? String(organizations[0].id) : '';

    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        organization_id: string;
        organization_third_party_api_id: string;
        defaults: Record<string, string>;
        csv: File | null;
    }>({
        name: '',
        organization_id: defaultOrganizationId,
        organization_third_party_api_id: firstConnectionId(defaultOrganizationId, connections),
        defaults: {},
        csv: null,
    });

    const orgConnections = useMemo(
        () => connections.filter((c) => String(c.organization_id) === data.organization_id),
        [connections, data.organization_id],
    );

    const selected = useMemo(
        () => connections.find((c) => String(c.id) === data.organization_third_party_api_id),
        [connections, data.organization_third_party_api_id],
    );

    const optionalParams = useMemo(() => selected?.params.filter((p) => !p.required) ?? [], [selected]);
    const requiredColumns = useMemo(() => selected?.params.filter((p) => p.required).map((p) => p.csv_column) ?? [], [selected]);

    function selectOrganization(organizationId: string) {
        setData({
            ...data,
            organization_id: organizationId,
            organization_third_party_api_id: firstConnectionId(organizationId, connections),
            defaults: {},
        });
    }

    function selectConnection(connectionId: string) {
        setData({
            ...data,
            organization_third_party_api_id: connectionId,
            defaults: {},
        });
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(uploadCsv.url(), { forceFormData: true });
    }

    const canSubmit = Boolean(data.csv && data.organization_third_party_api_id);

    return (
        <>
            <Head title="New API batch" />
            <div className="flex max-w-xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold">New API batch</h1>
                    <p className="text-sm text-muted-foreground">Select organization, then API connection with base URL + token.</p>
                </div>

                {requiredColumns.length > 0 && (
                    <div className="rounded-md bg-muted/60 px-3 py-2 text-xs">
                        Required CSV: <code className="font-mono">{requiredColumns.join(', ')}</code>
                    </div>
                )}

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="space-y-1.5">
                        <Label>Batch name</Label>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>

                    <div className="space-y-1.5">
                        <Label>Organization</Label>
                        <Select
                            value={data.organization_id}
                            onValueChange={selectOrganization}
                            disabled={organizations.length === 0}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select organization" />
                            </SelectTrigger>
                            <SelectContent>
                                {organizations.map((org) => (
                                    <SelectItem key={org.id} value={String(org.id)}>
                                        {org.name} (BA {org.ba_code})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-1.5">
                        <Label>API</Label>
                        <Select
                            value={data.organization_third_party_api_id}
                            onValueChange={selectConnection}
                            disabled={orgConnections.length === 0}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select API" />
                            </SelectTrigger>
                            <SelectContent>
                                {orgConnections.map((connection) => (
                                    <SelectItem key={connection.id} value={String(connection.id)}>
                                        {connection.api_name} ({connection.method})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.organization_third_party_api_id} />
                        {data.organization_id && orgConnections.length === 0 && (
                            <p className="text-xs text-muted-foreground">
                                No active API connections for this org.{' '}
                                <Link href={organizationShow.url(Number(data.organization_id))} className="underline">
                                    Configure under Organizations → View
                                </Link>
                                .
                            </p>
                        )}
                    </div>

                    {optionalParams.map((param) => (
                        <div key={param.key} className="space-y-1">
                            <Label className="text-xs">Default: {param.key}</Label>
                            <Input
                                value={data.defaults[param.key] ?? ''}
                                onChange={(e) => setData('defaults', { ...data.defaults, [param.key]: e.target.value })}
                            />
                        </div>
                    ))}

                    <div className="space-y-1.5">
                        <Label>CSV file</Label>
                        <Input type="file" accept=".csv" ref={fileRef} onChange={(e) => setData('csv', e.target.files?.[0] ?? null)} />
                        <InputError message={errors.csv} />
                    </div>

                    <Button type="submit" disabled={processing || !canSubmit}>
                        {processing ? 'Uploading…' : 'Start batch'}
                    </Button>
                </form>
            </div>
        </>
    );
}

ThirdPartyApiBatchesCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Third party APIs', href: apisIndex.url() },
        { title: 'Batches', href: index.url() },
        { title: 'New batch', href: create.url() },
    ],
};
