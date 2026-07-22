import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { uploadCsv } from '@/actions/App/Http/Controllers/StockTransactionReconciliationController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import {
    create,
    createFromConnections,
    index,
} from '@/routes/stock-transaction-reconciliation';

const STOCK_REQUIRED_COLUMNS = [
    'site_code',
    'barcode',
    'icode',
    'batch_no',
    'sprefcode',
    'stock_point_name',
    'qty',
] as const;

const LOG_REQUIRED_COLUMNS = [
    'site_code',
    'icode',
    'batch_no',
    'sprefcode',
    'doc_no',
    'enttype',
    'qty',
] as const;

const MAX_FILE_SIZE_MB = 512;
const MAX_FILE_SIZE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024;

function ColumnsHint({
    title,
    columns,
}: {
    title: string;
    columns: readonly string[];
}) {
    return (
        <div className="rounded-md bg-muted/60 px-3 py-2">
            <p className="mb-1.5 text-xs font-medium text-muted-foreground">
                {title}
            </p>
            <div className="flex flex-wrap gap-1.5">
                {columns.map((col) => (
                    <code
                        key={col}
                        className="rounded bg-background px-1.5 py-0.5 font-mono text-xs"
                    >
                        {col}
                    </code>
                ))}
            </div>
        </div>
    );
}

export default function StockTransactionReconciliationCreate() {
    const [includeZwingStock, setIncludeZwingStock] = useState(true);
    const [includeErpStock, setIncludeErpStock] = useState(true);
    const [includeZwingLogs, setIncludeZwingLogs] = useState(false);
    const [includeErpLogs, setIncludeErpLogs] = useState(false);

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
        zwing_log_csv: File | null;
        erp_log_csv: File | null;
    }>({
        name: '',
        v_id: '',
        zwing_csv: null,
        erp_csv: null,
        zwing_log_csv: null,
        erp_log_csv: null,
    });

    const canSubmit =
        (includeZwingStock && data.zwing_csv !== null) ||
        (includeErpStock && data.erp_csv !== null);

    function handleFileChange(
        field: 'zwing_csv' | 'erp_csv' | 'zwing_log_csv' | 'erp_log_csv',
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

    function toggleZwingStock(checked: boolean) {
        setIncludeZwingStock(checked);
        if (!checked) {
            clearErrors('zwing_csv');
            setData('zwing_csv', null);
        }
    }

    function toggleErpStock(checked: boolean) {
        setIncludeErpStock(checked);
        if (!checked) {
            clearErrors('erp_csv');
            setData('erp_csv', null);
        }
    }

    function toggleZwingLogs(checked: boolean) {
        setIncludeZwingLogs(checked);
        if (!checked) {
            clearErrors('zwing_log_csv');
            setData('zwing_log_csv', null);
        }
    }

    function toggleErpLogs(checked: boolean) {
        setIncludeErpLogs(checked);
        if (!checked) {
            clearErrors('erp_log_csv');
            setData('erp_log_csv', null);
        }
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!canSubmit) {
            return;
        }
        post(uploadCsv.url(), { forceFormData: true });
    }

    return (
        <>
            <Head title="New reconciliation" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            New reconciliation
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Upload Zwing and/or ERP stock exports. Log files are
                            optional.
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={createFromConnections.url()}>
                            Use connections instead
                        </Link>
                    </Button>
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
                                placeholder="e.g. May 2026 stock check"
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

                    <div>
                        <h2 className="text-sm font-medium">Stock CSVs</h2>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            At least one stock file is required.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div
                            className={`flex flex-col gap-3 rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border ${!includeZwingStock ? 'opacity-60' : ''}`}
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="font-medium">Zwing (POS)</p>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        MySQL / vendor-side stock export
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="include-zwing-stock"
                                        checked={includeZwingStock}
                                        onCheckedChange={(checked) =>
                                            toggleZwingStock(checked === true)
                                        }
                                    />
                                    <Label
                                        htmlFor="include-zwing-stock"
                                        className="cursor-pointer text-sm font-normal"
                                    >
                                        Upload
                                    </Label>
                                </div>
                            </div>
                            {includeZwingStock && (
                                <>
                                    <div className="space-y-2">
                                        <Label htmlFor="reconciliation-csv-zwing">
                                            CSV file
                                        </Label>
                                        <input
                                            id="reconciliation-csv-zwing"
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
                                            message={errors.zwing_csv}
                                        />
                                    </div>
                                    <ColumnsHint
                                        title="Required columns"
                                        columns={STOCK_REQUIRED_COLUMNS}
                                    />
                                </>
                            )}
                        </div>

                        <div
                            className={`flex flex-col gap-3 rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border ${!includeErpStock ? 'opacity-60' : ''}`}
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="font-medium">ERP</p>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        PostgreSQL / central inventory export
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="include-erp-stock"
                                        checked={includeErpStock}
                                        onCheckedChange={(checked) =>
                                            toggleErpStock(checked === true)
                                        }
                                    />
                                    <Label
                                        htmlFor="include-erp-stock"
                                        className="cursor-pointer text-sm font-normal"
                                    >
                                        Upload
                                    </Label>
                                </div>
                            </div>
                            {includeErpStock && (
                                <>
                                    <div className="space-y-2">
                                        <Label htmlFor="reconciliation-csv-erp">
                                            CSV file
                                        </Label>
                                        <input
                                            id="reconciliation-csv-erp"
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
                                    <ColumnsHint
                                        title="Required columns"
                                        columns={STOCK_REQUIRED_COLUMNS}
                                    />
                                </>
                            )}
                        </div>
                    </div>

                    <div>
                        <h2 className="text-sm font-medium">Log CSVs</h2>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            Optional transaction logs for Zwing and ERP.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div
                            className={`flex flex-col gap-3 rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border ${!includeZwingLogs ? 'opacity-60' : ''}`}
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="font-medium">Zwing logs</p>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        Optional Zwing transaction log export
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="include-zwing-logs"
                                        checked={includeZwingLogs}
                                        onCheckedChange={(checked) =>
                                            toggleZwingLogs(checked === true)
                                        }
                                    />
                                    <Label
                                        htmlFor="include-zwing-logs"
                                        className="cursor-pointer text-sm font-normal"
                                    >
                                        Upload
                                    </Label>
                                </div>
                            </div>
                            {includeZwingLogs && (
                                <>
                                    <div className="space-y-2">
                                        <Label htmlFor="reconciliation-log-zwing">
                                            Log CSV file
                                        </Label>
                                        <input
                                            id="reconciliation-log-zwing"
                                            name="zwing_log_csv"
                                            type="file"
                                            accept=".csv,.txt,text/csv"
                                            className="w-full text-sm text-foreground file:me-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:text-foreground"
                                            onChange={(e) =>
                                                handleFileChange(
                                                    'zwing_log_csv',
                                                    e.target.files?.[0] ?? null,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors.zwing_log_csv}
                                        />
                                    </div>
                                    <ColumnsHint
                                        title="Required columns"
                                        columns={LOG_REQUIRED_COLUMNS}
                                    />
                                </>
                            )}
                        </div>

                        <div
                            className={`flex flex-col gap-3 rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border ${!includeErpLogs ? 'opacity-60' : ''}`}
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="font-medium">ERP logs</p>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        Optional ERP transaction log export
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="include-erp-logs"
                                        checked={includeErpLogs}
                                        onCheckedChange={(checked) =>
                                            toggleErpLogs(checked === true)
                                        }
                                    />
                                    <Label
                                        htmlFor="include-erp-logs"
                                        className="cursor-pointer text-sm font-normal"
                                    >
                                        Upload
                                    </Label>
                                </div>
                            </div>
                            {includeErpLogs && (
                                <>
                                    <div className="space-y-2">
                                        <Label htmlFor="reconciliation-log-erp">
                                            Log CSV file
                                        </Label>
                                        <input
                                            id="reconciliation-log-erp"
                                            name="erp_log_csv"
                                            type="file"
                                            accept=".csv,.txt,text/csv"
                                            className="w-full text-sm text-foreground file:me-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:text-foreground"
                                            onChange={(e) =>
                                                handleFileChange(
                                                    'erp_log_csv',
                                                    e.target.files?.[0] ?? null,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors.erp_log_csv}
                                        />
                                    </div>
                                    <ColumnsHint
                                        title="Required columns"
                                        columns={LOG_REQUIRED_COLUMNS}
                                    />
                                </>
                            )}
                        </div>
                    </div>

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

                    <div>
                        <Button
                            type="submit"
                            size="lg"
                            disabled={processing || !canSubmit}
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

StockTransactionReconciliationCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Stock reconciliation', href: index.url() },
        { title: 'New reconciliation', href: create.url() },
    ],
};
