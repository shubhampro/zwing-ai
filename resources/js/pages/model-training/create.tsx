import { Head, useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useState } from 'react';
import { uploadCsv } from '@/actions/App/Http/Controllers/ModelTrainingController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { create, index } from '@/routes/model-training';

const MAX_FILE_SIZE_MB = 512;
const MAX_FILE_SIZE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024;

type PageProps = {
    modelAiConfigured: boolean;
    defaultDatasetKey: string;
};

type SheetPreview = {
    columns: string[];
    suggested_targets: string[];
    row_count: number;
};

function getXsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

export default function ModelTrainingCreate({
    modelAiConfigured,
    defaultDatasetKey,
}: PageProps) {
    const [preview, setPreview] = useState<SheetPreview | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewError, setPreviewError] = useState<string | null>(null);

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
        dataset_key: string;
        training_csv: File | null;
        auto_train: boolean;
        target_columns: string[];
    }>({
        name: '',
        dataset_key: defaultDatasetKey,
        training_csv: null,
        auto_train: true,
        target_columns: [],
    });

    async function loadPreview(file: File) {
        setPreviewLoading(true);
        setPreviewError(null);

        const formData = new FormData();
        formData.append('training_csv', file);

        try {
            const res = await fetch('/model-training/preview', {
                method: 'POST',
                headers: {
                    'X-XSRF-TOKEN': getXsrfToken(),
                    Accept: 'application/json',
                },
                body: formData,
            });

            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(json?.message ?? `Preview failed (${res.status})`);
            }

            const nextPreview = json as SheetPreview;
            setPreview(nextPreview);
            setData(
                'target_columns',
                nextPreview.suggested_targets.length > 0
                    ? nextPreview.suggested_targets
                    : nextPreview.columns.slice(0, 3),
            );
        } catch (err) {
            setPreview(null);
            setData('target_columns', []);
            setPreviewError(
                err instanceof Error ? err.message : 'Could not preview sheet',
            );
        } finally {
            setPreviewLoading(false);
        }
    }

    function handleFileChange(file: File | null) {
        clearErrors('training_csv');
        setPreview(null);
        setPreviewError(null);
        setData('target_columns', []);

        if (file && file.size > MAX_FILE_SIZE_BYTES) {
            setError(
                'training_csv',
                `File is too large (${(file.size / 1024 / 1024).toFixed(1)} MB). Maximum allowed size is ${MAX_FILE_SIZE_MB} MB.`,
            );
            setData('training_csv', null);
            return;
        }

        setData('training_csv', file);

        if (file && modelAiConfigured) {
            void loadPreview(file);
        }
    }

    function toggleTarget(column: string, checked: boolean) {
        if (checked) {
            setData('target_columns', [...data.target_columns, column]);
            return;
        }
        setData(
            'target_columns',
            data.target_columns.filter((item) => item !== column),
        );
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!data.training_csv) {
            return;
        }
        if (data.auto_train && data.target_columns.length === 0) {
            setError('target_columns', 'Select at least one target column.');
            return;
        }
        post(uploadCsv.url(), { forceFormData: true });
    }

    return (
        <>
            <Head title="Upload training sheet" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Upload training sheet
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Upload your sheet, choose which columns should become
                        prediction targets, and train models automatically.
                    </p>
                </div>

                {!modelAiConfigured && (
                    <div className="rounded-xl border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm text-amber-900 dark:text-amber-100">
                        Model-AI service is not configured. Upload will fail until{' '}
                        <code className="rounded bg-background px-1 font-mono text-xs">
                            MODEL_AI_URL
                        </code>{' '}
                        is set.
                    </div>
                )}

                <form onSubmit={submit} className="flex max-w-3xl flex-col gap-6">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="dataset-name">
                                Dataset name{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="dataset-name"
                                placeholder="e.g. Store credit notes May 2026"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="dataset-key">
                                Dataset key{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="dataset-key"
                                placeholder="store_credit_notes"
                                value={data.dataset_key}
                                onChange={(e) =>
                                    setData(
                                        'dataset_key',
                                        e.target.value.toLowerCase().replace(/\s+/g, '_'),
                                    )
                                }
                            />
                            <InputError message={errors.dataset_key} />
                        </div>
                    </div>

                    <div className="flex flex-col gap-3 rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <div>
                            <p className="font-medium">Training sheet</p>
                            <p className="mt-0.5 text-sm text-muted-foreground">
                                CSV or Excel file with your training rows
                            </p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="training-csv">File</Label>
                            <input
                                id="training-csv"
                                name="training_csv"
                                type="file"
                                accept=".csv,.txt,.xlsx,.xls,text/csv"
                                className="w-full text-sm text-foreground file:me-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:text-foreground"
                                onChange={(e) =>
                                    handleFileChange(e.target.files?.[0] ?? null)
                                }
                            />
                            <InputError message={errors.training_csv} />
                            {previewError && (
                                <p className="text-sm text-destructive">{previewError}</p>
                            )}
                        </div>

                        {previewLoading && (
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Loader2 className="size-4 animate-spin" />
                                Reading sheet columns…
                            </div>
                        )}

                        {preview && (
                            <div className="space-y-3 rounded-md bg-muted/60 px-3 py-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <p className="text-sm font-medium">
                                        Target columns
                                    </p>
                                    <span className="text-xs text-muted-foreground">
                                        {preview.row_count.toLocaleString()} rows ·{' '}
                                        {preview.columns.length} columns
                                    </span>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Select the columns you want the AI to predict.
                                    One model will be trained per selected column.
                                </p>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {preview.columns.map((column) => {
                                        const checked = data.target_columns.includes(column);
                                        const suggested =
                                            preview.suggested_targets.includes(column);

                                        return (
                                            <label
                                                key={column}
                                                className="flex cursor-pointer items-start gap-2 rounded-md border border-background bg-background px-3 py-2"
                                            >
                                                <Checkbox
                                                    checked={checked}
                                                    onCheckedChange={(value) =>
                                                        toggleTarget(column, value === true)
                                                    }
                                                />
                                                <span className="min-w-0 flex-1">
                                                    <span className="block font-mono text-xs">
                                                        {column}
                                                    </span>
                                                    {suggested && (
                                                        <span className="text-xs text-muted-foreground">
                                                            Suggested target
                                                        </span>
                                                    )}
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                                <InputError message={errors.target_columns} />
                            </div>
                        )}

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="auto-train"
                                checked={data.auto_train}
                                onCheckedChange={(checked) =>
                                    setData('auto_train', checked === true)
                                }
                            />
                            <Label htmlFor="auto-train" className="font-normal">
                                Train models automatically after upload
                            </Label>
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
                    </div>

                    <div>
                        <Button
                            type="submit"
                            size="lg"
                            disabled={
                                processing ||
                                !data.training_csv ||
                                !modelAiConfigured ||
                                previewLoading ||
                                (data.auto_train && data.target_columns.length === 0)
                            }
                            className="w-full md:w-auto md:min-w-48"
                        >
                            {processing ? 'Uploading…' : 'Upload & train'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

ModelTrainingCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Model training', href: index.url() },
        { title: 'Upload sheet', href: create.url() },
    ],
};
