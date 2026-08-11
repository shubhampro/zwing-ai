import { Head, Link } from '@inertiajs/react';
import { Copy, Loader2, Pencil } from 'lucide-react';
import { useMemo, useState, type FormEvent } from 'react';
import { toast } from 'sonner';
import { generate as generateAction } from '@/actions/App/Http/Controllers/PayloadComposerController';
import Heading from '@/components/heading';
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
import { copyToClipboard } from '@/lib/copy-to-clipboard';
import { dashboard } from '@/routes';
import { edit, index, show } from '@/routes/payload-composers';

type Composer = {
    id: number;
    name: string;
    description: string | null;
    scalars: Array<{
        key: string;
        required: boolean;
        default: string | null;
    }>;
    slots: Array<{
        id: number;
        key: string;
        saved_sql_query_id: number;
        saved_sql_query_title: string | null;
        shape: string;
        sort_order: number;
    }>;
};

type OrganizationOption = {
    id: number;
    name: string;
    ba_code: string;
    db_name: string;
    label: string;
};

type Props = {
    composer: Composer;
    bindingNames: string[];
    organizations: OrganizationOption[];
};

export default function PayloadComposersShow({
    composer,
    bindingNames,
    organizations,
}: Props) {
    const [organizationId, setOrganizationId] = useState(
        organizations[0] ? String(organizations[0].id) : '',
    );
    const [scalars, setScalars] = useState<Record<string, string>>(() => {
        const initial: Record<string, string> = {};

        for (const scalar of composer.scalars) {
            initial[scalar.key] = scalar.default ?? '';
        }

        return initial;
    });
    const [bindings, setBindings] = useState<Record<string, string>>(() => {
        const initial: Record<string, string> = {};

        for (const name of bindingNames) {
            initial[name] = '';
        }

        return initial;
    });
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [payload, setPayload] = useState<object | null>(null);
    const [meta, setMeta] = useState<{
        row_counts: Record<string, number>;
        database?: string;
    } | null>(null);

    const payloadText = useMemo(
        () => (payload ? JSON.stringify(payload, null, 2) : ''),
        [payload],
    );

    async function handleGenerate(e: FormEvent) {
        e.preventDefault();
        setProcessing(true);
        setErrors({});
        setPayload(null);
        setMeta(null);

        try {
            const xsrfToken = decodeURIComponent(
                document.cookie
                    .split('; ')
                    .find((row) => row.startsWith('XSRF-TOKEN='))
                    ?.split('=')[1] ?? '',
            );

            const response = await fetch(generateAction.url(composer.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken,
                },
                body: JSON.stringify({
                    organization_id: Number(organizationId),
                    scalars,
                    bindings,
                }),
            });

            const json = await response.json().catch(() => ({}));

            if (!response.ok) {
                if (json?.errors && typeof json.errors === 'object') {
                    const flat: Record<string, string> = {};

                    for (const [key, value] of Object.entries(json.errors)) {
                        flat[key] = Array.isArray(value)
                            ? String(value[0])
                            : String(value);
                    }

                    setErrors(flat);
                }

                toast.error(
                    json?.message ?? `Generate failed (${response.status})`,
                );
                return;
            }

            setPayload(json.payload ?? null);
            setMeta(json.meta ?? null);
            toast.success('Payload generated');
        } catch (error) {
            toast.error(
                error instanceof Error ? error.message : 'Generate failed',
            );
        } finally {
            setProcessing(false);
        }
    }

    return (
        <>
            <Head title={composer.name} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title={composer.name}
                        description={
                            composer.description ??
                            'Pick org MySQL (SSH), fill scalars + bindings, generate JSON.'
                        }
                    />
                    <Button size="sm" variant="outline" asChild>
                        <Link href={edit.url(composer.id)}>
                            <Pencil className="size-4" />
                            Edit
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-2 text-sm text-muted-foreground">
                    <p>
                        Slots:{' '}
                        {composer.slots
                            .map((slot) => {
                                const label =
                                    slot.key ||
                                    (slot.shape === 'object'
                                        ? '(root / like scalars)'
                                        : '(unnamed)');

                                return `${label} ← ${slot.saved_sql_query_title ?? 'query'} (${slot.shape})`;
                            })
                            .join(' · ')}
                    </p>
                </div>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,28rem)_minmax(0,1fr)] xl:items-start">
                    <form
                        onSubmit={handleGenerate}
                        className="grid gap-6"
                    >
                        <div className="space-y-1.5">
                            <Label>Organization (MySQL SSH)</Label>
                            <Select
                                value={organizationId}
                                onValueChange={setOrganizationId}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select organization" />
                                </SelectTrigger>
                                <SelectContent>
                                    {organizations.map((organization) => (
                                        <SelectItem
                                            key={organization.id}
                                            value={String(organization.id)}
                                        >
                                            {organization.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.organization_id} />
                        </div>

                        {composer.scalars.length > 0 && (
                            <div className="space-y-3">
                                <h2 className="text-sm font-medium">Scalars</h2>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {composer.scalars.map((scalar) => (
                                        <div
                                            key={scalar.key}
                                            className="space-y-1.5"
                                        >
                                            <Label
                                                htmlFor={`scalar-${scalar.key}`}
                                            >
                                                {scalar.key}
                                                {scalar.required ? ' *' : ''}
                                            </Label>
                                            <Input
                                                id={`scalar-${scalar.key}`}
                                                value={scalars[scalar.key] ?? ''}
                                                onChange={(e) =>
                                                    setScalars((prev) => ({
                                                        ...prev,
                                                        [scalar.key]:
                                                            e.target.value,
                                                    }))
                                                }
                                                className="font-mono text-sm"
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `scalars.${scalar.key}`
                                                    ]
                                                }
                                            />
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {bindingNames.length > 0 && (
                            <div className="space-y-3">
                                <h2 className="text-sm font-medium">
                                    SQL bindings
                                </h2>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {bindingNames.map((name) => (
                                        <div
                                            key={name}
                                            className="space-y-1.5"
                                        >
                                            <Label htmlFor={`binding-${name}`}>
                                                :{name}
                                            </Label>
                                            <Input
                                                id={`binding-${name}`}
                                                value={bindings[name] ?? ''}
                                                onChange={(e) =>
                                                    setBindings((prev) => ({
                                                        ...prev,
                                                        [name]: e.target.value,
                                                    }))
                                                }
                                                className="font-mono text-sm"
                                            />
                                            <InputError
                                                message={
                                                    errors[`bindings.${name}`]
                                                }
                                            />
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        <div>
                            <Button
                                type="submit"
                                disabled={
                                    processing ||
                                    organizationId === '' ||
                                    organizations.length === 0
                                }
                            >
                                {processing && (
                                    <Loader2 className="size-4 animate-spin" />
                                )}
                                Generate payload
                            </Button>
                        </div>
                    </form>

                    <div className="space-y-3 xl:sticky xl:top-4">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="text-sm font-medium">
                                    Generated JSON
                                </h2>
                                {meta ? (
                                    <p className="text-xs text-muted-foreground">
                                        DB: {meta.database ?? 'mysql_ssh'} ·
                                        Rows:{' '}
                                        {Object.entries(meta.row_counts)
                                            .map(
                                                ([key, count]) =>
                                                    `${key}=${count}`,
                                            )
                                            .join(', ')}
                                    </p>
                                ) : (
                                    <p className="text-xs text-muted-foreground">
                                        Fill left form, generate → JSON here.
                                    </p>
                                )}
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={payloadText === ''}
                                onClick={async () => {
                                    const ok =
                                        await copyToClipboard(payloadText);
                                    if (ok) {
                                        toast.success('Copied payload');
                                    } else {
                                        toast.error('Copy failed');
                                    }
                                }}
                            >
                                <Copy className="size-4" />
                                Copy
                            </Button>
                        </div>
                        <pre className="min-h-[20rem] max-h-[calc(100vh-12rem)] overflow-auto rounded-lg border border-sidebar-border/70 bg-muted/30 p-4 font-mono text-xs whitespace-pre-wrap">
                            {payloadText !== ''
                                ? payloadText
                                : '{\n  // waiting for generate\n}'}
                        </pre>
                    </div>
                </div>
            </div>
        </>
    );
}

PayloadComposersShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Payload composers', href: index.url() },
        { title: 'Generate', href: show.url(0) },
    ],
};
