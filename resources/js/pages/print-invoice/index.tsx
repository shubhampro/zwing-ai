import { Head } from '@inertiajs/react';
import { Eye, Loader2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { preview as previewAction } from '@/actions/App/Http/Controllers/PrintInvoiceController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { index } from '@/routes/print-invoice';

type DefaultPayload = {
    url: string;
    tenant_id: string;
    token: string;
    bodyJson: string;
};

type PreviewResponse = {
    success: boolean;
    html?: string;
    status?: number;
    message?: string;
};

type PreviewFormat = 'thermal' | 'html';

const TEXTAREA_CLASS =
    'border-input placeholder:text-muted-foreground flex min-h-[180px] w-full rounded-md border bg-transparent px-3 py-2 font-mono text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

function getXsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

function detectPreviewFormat(html: string): PreviewFormat {
    const lower = html.toLowerCase();
    const preTagCount = lower.match(/<pre[\s>]/g)?.length ?? 0;

    const isThermal =
        /width:\s*350px/i.test(html) ||
        (preTagCount >= 2 && !/<!doctype/i.test(html));

    return isThermal ? 'thermal' : 'html';
}

function HtmlInvoicePreview({ html }: { html: string }) {
    return (
        <div className="overflow-x-auto overflow-y-visible bg-zinc-100 p-4 dark:bg-zinc-900/40">
            <div className="mx-auto w-full min-w-0 max-w-[210mm] bg-white px-3 py-4 shadow-md ring-1 ring-black/10 md:px-6 md:py-6 dark:ring-white/10">
                <div
                    className="a4-invoice-preview w-full [&_img]:max-w-full [&_table]:w-full"
                    dangerouslySetInnerHTML={{ __html: html }}
                />
            </div>
        </div>
    );
}

function ThermalInvoicePreview({ html }: { html: string }) {
    return (
        <div className="flex justify-center overflow-visible bg-zinc-100 p-6 dark:bg-zinc-900/40">
            <div
                className="bg-white shadow-sm ring-1 ring-black/5 dark:ring-white/10"
                dangerouslySetInnerHTML={{ __html: html }}
            />
        </div>
    );
}

function InvoicePreview({
    html,
    format,
}: {
    html: string;
    format: PreviewFormat;
}) {
    if (format === 'thermal') {
        return <ThermalInvoicePreview html={html} />;
    }

    return <HtmlInvoicePreview html={html} />;
}

export default function PrintInvoiceIndex({
    defaultPayload,
}: {
    defaultPayload: DefaultPayload;
}) {
    const previewRef = useRef<HTMLElement>(null);
    const [url, setUrl] = useState(defaultPayload.url);
    const [tenantId, setTenantId] = useState(defaultPayload.tenant_id);
    const [token, setToken] = useState(defaultPayload.token);
    const [bodyJson, setBodyJson] = useState(defaultPayload.bodyJson);
    const [bodyJsonError, setBodyJsonError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [previewHtml, setPreviewHtml] = useState<string | null>(null);
    const [previewFormat, setPreviewFormat] = useState<PreviewFormat | null>(
        null,
    );
    const [responseStatus, setResponseStatus] = useState<number | null>(null);

    useEffect(() => {
        if (!previewHtml || !previewRef.current) {
            return;
        }

        previewRef.current.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest',
        });
    }, [previewHtml]);

    async function handlePreview(e: React.FormEvent): Promise<void> {
        e.preventDefault();
        setError(null);
        setBodyJsonError(null);
        setLoading(true);
        setPreviewHtml(null);
        setPreviewFormat(null);
        setResponseStatus(null);

        let parsedBody: Record<string, unknown>;

        try {
            parsedBody = JSON.parse(bodyJson) as Record<string, unknown>;
        } catch {
            setBodyJsonError('Invalid JSON. Please check the request body.');
            setLoading(false);
            return;
        }

        if (
            parsedBody === null ||
            Array.isArray(parsedBody) ||
            typeof parsedBody !== 'object'
        ) {
            setBodyJsonError('Request body must be a JSON object.');
            setLoading(false);
            return;
        }

        try {
            const res = await fetch(previewAction.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    url,
                    tenant_id: tenantId,
                    token,
                    body: parsedBody,
                }),
            });

            const json: PreviewResponse = await res.json().catch(() => ({
                success: false,
                message: 'Invalid response from server.',
            }));

            if (!res.ok || !json.success) {
                setError(json.message ?? `Request failed (${res.status})`);
                return;
            }

            const html = json.html ?? '';
            setPreviewHtml(html);
            setPreviewFormat(detectPreviewFormat(html));
            setResponseStatus(json.status ?? res.status);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Unknown error');
        } finally {
            setLoading(false);
        }
    }

    return (
        <>
            <Head title="Print Invoice" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Print Invoice"
                    description="Preview thermal roll or full HTML invoice responses."
                />

                <div className="grid items-start gap-6 xl:grid-cols-2">
                    <form
                        onSubmit={(e) => {
                            void handlePreview(e);
                        }}
                        className="flex flex-col gap-4"
                    >
                        <section className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <h2 className="mb-3 text-sm font-semibold tracking-wide uppercase">
                                Request
                            </h2>

                            <div className="flex flex-col gap-4">
                                <div className="space-y-1.5">
                                    <Label htmlFor="api-url">URL</Label>
                                    <Input
                                        id="api-url"
                                        type="url"
                                        value={url}
                                        onChange={(e) => setUrl(e.target.value)}
                                        placeholder="https://aks-prod.api.gozwing.com/pos/print/invoice"
                                        required
                                    />
                                </div>

                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="tenant-id">
                                            x-tenant-id
                                        </Label>
                                        <Input
                                            id="tenant-id"
                                            value={tenantId}
                                            onChange={(e) =>
                                                setTenantId(e.target.value)
                                            }
                                            required
                                        />
                                    </div>
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="auth-token">
                                        Authorization (Bearer token)
                                    </Label>
                                    <Input
                                        id="auth-token"
                                        value={token}
                                        onChange={(e) =>
                                            setToken(e.target.value)
                                        }
                                        placeholder="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
                                        required
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="request-body">
                                        Body (JSON)
                                    </Label>
                                    <textarea
                                        id="request-body"
                                        value={bodyJson}
                                        onChange={(e) => {
                                            setBodyJson(e.target.value);
                                            setBodyJsonError(null);
                                        }}
                                        rows={12}
                                        spellCheck={false}
                                        className={TEXTAREA_CLASS}
                                        required
                                    />
                                    {bodyJsonError && (
                                        <p className="text-sm text-destructive">
                                            {bodyJsonError}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </section>

                        <div>
                            <Button type="submit" disabled={loading}>
                                {loading ? (
                                    <>
                                        <Loader2 className="size-4 animate-spin" />
                                        Fetching preview…
                                    </>
                                ) : (
                                    <>
                                        <Eye className="size-4" />
                                        Preview invoice
                                    </>
                                )}
                            </Button>
                        </div>

                        {error && (
                            <div className="rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                                {error}
                            </div>
                        )}
                    </form>

                    <section
                        ref={previewRef}
                        className="min-w-0 overflow-visible rounded-lg border border-sidebar-border/70 xl:sticky xl:top-4 xl:self-start dark:border-sidebar-border"
                    >
                        <div className="flex items-center justify-between gap-2 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                            <div className="flex items-center gap-2">
                                <h2 className="text-sm font-semibold tracking-wide uppercase">
                                    Preview
                                </h2>
                                {previewFormat && (
                                    <Badge variant="outline">
                                        {previewFormat === 'thermal'
                                            ? 'Thermal'
                                            : 'A4'}
                                    </Badge>
                                )}
                            </div>
                            {responseStatus !== null && (
                                <Badge variant="secondary">
                                    {responseStatus}
                                </Badge>
                            )}
                        </div>

                        {!previewHtml && !loading && (
                            <div className="flex min-h-[420px] items-center justify-center p-6 text-center text-sm text-muted-foreground">
                                Submit the request to preview thermal or HTML
                                invoice output here.
                            </div>
                        )}

                        {loading && (
                            <div className="flex min-h-[420px] items-center justify-center p-6 text-sm text-muted-foreground">
                                <Loader2 className="mr-2 size-4 animate-spin" />
                                Loading preview…
                            </div>
                        )}

                        {previewHtml && previewFormat && !loading && (
                            <InvoicePreview
                                html={previewHtml}
                                format={previewFormat}
                            />
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

PrintInvoiceIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Print Invoice', href: index.url() },
    ],
};
