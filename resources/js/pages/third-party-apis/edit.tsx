import { Head, Link, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { update } from '@/actions/App/Http/Controllers/ThirdPartyApiController';
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
import { index, show } from '@/routes/organizations';
import { edit, index as apisIndex } from '@/routes/third-party-apis';

type ApiParam = { key: string; csv_column: string; required: boolean; default: string };
type Connection = {
    id: number;
    organization_id: number;
    base_url: string;
    is_active: boolean;
    organization?: { id: number; name: string; ba_code: string };
};

type Props = {
    api: { id: number; name: string; path: string; method: string; params: ApiParam[]; auth_header_name: string; is_active: boolean };
    connections: Connection[];
    httpMethods: Record<string, string>;
};

export default function ThirdPartyApisEdit({ api, connections, httpMethods }: Props) {
    const methodEntries = Object.entries(httpMethods);
    const { data, setData, put, processing, errors } = useForm({
        name: api.name,
        path: api.path,
        method: api.method,
        params: api.params.map((p) => ({
            key: p.key ?? '',
            csv_column: p.csv_column ?? p.key ?? '',
            required: Boolean(p.required),
            default: p.default ?? '',
        })),
        auth_header_name: api.auth_header_name,
        is_active: api.is_active,
    });

    function updateParam(index: number, field: keyof ApiParam, value: string | boolean) {
        const params = [...data.params];
        params[index] = { ...params[index], [field]: value };
        setData('params', params);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(update.url(api.id));
    }

    return (
        <>
            <Head title={`Edit API — ${api.name}`} />

            <div className="flex max-w-2xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold">Edit API template</h1>
                    <p className="text-sm text-muted-foreground">
                        Base URL + token configured per org under{' '}
                        <Link href={index.url()} className="underline">
                            Organizations → View & APIs
                        </Link>
                        .
                    </p>
                </div>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="name">Name</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-[1fr_auto]">
                        <div className="space-y-1.5">
                            <Label htmlFor="path">Path</Label>
                            <Input id="path" value={data.path} onChange={(e) => setData('path', e.target.value)} className="font-mono text-sm" />
                            <InputError message={errors.path} />
                        </div>
                        <div className="space-y-1.5 sm:w-36">
                            <Label>Method</Label>
                            <Select value={data.method} onValueChange={(v) => setData('method', v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {methodEntries.map(([value, label]) => (
                                        <SelectItem key={value} value={value}>{label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <Label>Request params</Label>
                            <Button type="button" variant="outline" size="sm" onClick={() => setData('params', [...data.params, { key: '', csv_column: '', required: false, default: '' }])}>
                                <Plus className="size-4" /> Add param
                            </Button>
                        </div>
                        {data.params.map((param, index) => (
                            <div key={index} className="grid gap-2 rounded-md border p-3 sm:grid-cols-2">
                                <Input value={param.key} onChange={(e) => updateParam(index, 'key', e.target.value)} placeholder="Param key" />
                                <Input value={param.csv_column} onChange={(e) => updateParam(index, 'csv_column', e.target.value)} placeholder="CSV column" />
                            </div>
                        ))}
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="auth_header_name">Auth header name</Label>
                        <Input id="auth_header_name" value={data.auth_header_name} onChange={(e) => setData('auth_header_name', e.target.value)} />
                    </div>

                    <Button type="submit" disabled={processing}>Update template</Button>
                </form>

                {connections.length > 0 && (
                    <section className="border-t pt-4">
                        <h2 className="mb-2 text-sm font-medium">Connected organizations</h2>
                        <ul className="space-y-2 text-sm">
                            {connections.map((connection) => (
                                <li key={connection.id} className="flex items-center justify-between rounded-md border px-3 py-2">
                                    <span>
                                        {connection.organization?.name} — <span className="font-mono text-xs">{connection.base_url}</span>
                                    </span>
                                    <Link href={show.url(connection.organization_id)} className="text-xs underline">
                                        Manage
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

ThirdPartyApisEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Third party APIs', href: apisIndex.url() },
        { title: 'Edit', href: edit.url(0) },
    ],
};
