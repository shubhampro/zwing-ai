import { Head, useForm } from '@inertiajs/react';
import { useRef } from 'react';
import { flushSync } from 'react-dom';
import { uploadCsv } from '@/actions/App/Http/Controllers/InvoiceReconciliationController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

export default function InvoiceReconciliationCreate() {
    const zwingInputRef = useRef<HTMLInputElement>(null);
    const erpInputRef = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        post,
        processing,
        errors,
        progress,
        setError,
        clearErrors,
    } = useForm<{
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

    const hasBothCsvs = data.zwing_csv !== null && data.erp_csv !== null;

    function handleFileChange(
        field: 'zwing_csv' | 'erp_csv',
        file: File | null,
    ) {
        clearErrors(field);
        if (file && file.size > MAX_FILE_SIZE_BYTES) {
            setError(
                field,
                `File is too large (${(file.size / 1024 / 1024).toFixed(1)} MB). Maximum allowed size is ${MAX_FILE_SIZE_MB} MB.`,
            );
            setData(field, null);
            return;
        }
        setData(field, file);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();

        const zwing_csv = zwingInputRef.current?.files?.[0] ?? null;
        const erp_csv = erpInputRef.current?.files?.[0] ?? null;

        if (!zwing_csv || !erp_csv || processing) {
            return;
        }

        flushSync(() => {
            setData({
                name: data.name,
                v_id: data.v_id,
                zwing_csv,
                erp_csv,
            });
        });

        post(uploadCsv.url(), { forceFormData: true });
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
                        Upload Zwing (POS) and ERP invoice exports. Both files
                        are required. One row per invoice — multiple Mop Ref ids
                        go in{' '}
                        <code className="rounded bg-muted px-1 font-mono text-xs">
                            ref_id
                        </code>{' '}
                        separated by{' '}
                        <code className="rounded bg-muted px-1 font-mono text-xs">
                            -
                        </code>{' '}
                        (e.g.{' '}
                        <code className="rounded bg-muted px-1 font-mono text-xs">
                            22-21
                        </code>
                        ). Rows match on the same{' '}
                        <code className="rounded bg-muted px-1 font-mono text-xs">
                            invoice_id
                        </code>{' '}
                        and each Mop Ref id.
                    </p>
                </div>

                <form onSubmit={submit} className="flex flex-col gap-6">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="session-name">
                                Session name{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="session-name"
                                placeholder="e.g. May 2026 invoice check"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                            />
                            <InputError message={errors.name} />
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
                                value={data.v_id}
                                onChange={(e) =>
                                    setData('v_id', e.target.value)
                                }
                            />
                            <InputError message={errors.v_id} />
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
                                <InputError message={errors.zwing_csv} />
                            </div>
                            <RequiredColumnsHint />
                            {progress && (
                                <div className="space-y-1">
                                    <p className="text-xs text-muted-foreground">
                                        Uploading…
                                    </p>
                                    <progress
                                        className="h-1.5 w-full overflow-hidden rounded"
                                        value={progress.percentage}
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
                                <InputError message={errors.erp_csv} />
                            </div>
                            <RequiredColumnsHint />
                            {progress && (
                                <div className="space-y-1">
                                    <p className="text-xs text-muted-foreground">
                                        Uploading…
                                    </p>
                                    <progress
                                        className="h-1.5 w-full overflow-hidden rounded"
                                        value={progress.percentage}
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
                            disabled={processing || !hasBothCsvs}
                            className="w-full md:w-auto md:min-w-48"
                        >
                            {processing ? 'Working…' : 'Proceed'}
                        </Button>
                    </div>
                </form>
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
