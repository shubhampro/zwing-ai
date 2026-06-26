import { Head, Link, router, usePage } from '@inertiajs/react';
import { BrainCircuit, Plus, RefreshCcw } from 'lucide-react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { retrain } from '@/actions/App/Http/Controllers/ModelTrainingController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { create, index } from '@/routes/model-training';

type DatasetRow = {
    dataset_key: string;
    name: string;
    columns: string[];
    target_columns?: string[];
    row_count: number;
    source_filename?: string | null;
    uploaded_by?: string | null;
    uploaded_at?: string | null;
};

type ModelRow = {
    model_name: string;
    target_column: string;
    problem_type: string;
    accuracy?: number;
    schema_source?: string;
};

type PageProps = {
    modelAiConfigured: boolean;
    datasets: DatasetRow[];
    models: ModelRow[];
    flash?: {
        success?: string | null;
        error?: string | null;
    };
};

function formatDate(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }
    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

export default function ModelTrainingIndex() {
    const {
        modelAiConfigured,
        datasets,
        models,
    } = usePage<PageProps>().props;
    const flash = usePage<PageProps>().props.flash;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error]);

    function handleRetrain(datasetKey: string) {
        router.post(
            retrain.url(),
            { dataset_key: datasetKey },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Model training" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <Heading
                        title="Model training"
                        description="Upload store credit note sheets to MongoDB and train prediction models automatically."
                    />
                    <Button asChild>
                        <Link href={create.url()}>
                            <Plus className="size-4" />
                            Upload sheet
                        </Link>
                    </Button>
                </div>

                {!modelAiConfigured && (
                    <div className="rounded-xl border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm text-amber-900 dark:text-amber-100">
                        Model-AI service is not configured. Set{' '}
                        <code className="rounded bg-background px-1 font-mono text-xs">
                            MODEL_AI_URL
                        </code>{' '}
                        in your environment.
                    </div>
                )}

                <section className="space-y-3">
                    <div>
                        <h2 className="text-sm font-semibold tracking-wide text-foreground uppercase">
                            MongoDB datasets
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Uploaded training sheets stored for model training.
                        </p>
                    </div>

                    {datasets.length === 0 ? (
                        <div className="rounded-xl border border-sidebar-border/70 px-4 py-8 text-center text-sm text-muted-foreground dark:border-sidebar-border">
                            <p>No datasets yet.</p>
                            <p className="mt-2">
                                Upload a CSV or Excel sheet, or seed data via{' '}
                                <code className="rounded bg-muted px-1 font-mono text-xs">
                                    python3 train_auto.py --seed-from-csv
                                </code>{' '}
                                on the model-ai server.
                            </p>
                            <Button asChild className="mt-4" variant="outline">
                                <Link href={create.url()}>Upload sheet</Link>
                            </Button>
                        </div>
                    ) : (
                        <div className="grid gap-4 lg:grid-cols-2">
                            {datasets.map((dataset) => (
                                <div
                                    key={dataset.dataset_key}
                                    className="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-medium">{dataset.name}</p>
                                            <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                                                {dataset.dataset_key}
                                            </p>
                                        </div>
                                        <Badge variant="secondary">
                                            {dataset.row_count.toLocaleString()} rows
                                        </Badge>
                                    </div>

                                    <div className="text-sm text-muted-foreground">
                                        <p>
                                            File: {dataset.source_filename ?? '—'}
                                        </p>
                                        <p>Uploaded: {formatDate(dataset.uploaded_at)}</p>
                                        {dataset.uploaded_by && (
                                            <p>By: {dataset.uploaded_by}</p>
                                        )}
                                    </div>

                                    <div>
                                        <p className="mb-1.5 text-xs font-medium text-muted-foreground">
                                            Target columns
                                        </p>
                                        <div className="flex flex-wrap gap-1.5">
                                        {(dataset.target_columns ?? []).length > 0 ? (
                                            (dataset.target_columns ?? []).map((col) => (
                                                <code
                                                    key={col}
                                                    className="rounded bg-violet-500/10 px-1.5 py-0.5 font-mono text-xs text-violet-700 dark:text-violet-300"
                                                >
                                                    {col}
                                                </code>
                                            ))
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                No saved target columns
                                            </span>
                                        )}
                                    </div>
                                    </div>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={!modelAiConfigured}
                                        onClick={() => handleRetrain(dataset.dataset_key)}
                                        className="w-fit"
                                    >
                                        <RefreshCcw className="size-4" />
                                        Retrain models
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}
                </section>

                <section className="space-y-3">
                    <div>
                        <h2 className="text-sm font-semibold tracking-wide text-foreground uppercase">
                            Trained models
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Models trained from the target columns selected during upload.
                        </p>
                    </div>

                    {models.length === 0 ? (
                        <div className="rounded-xl border border-sidebar-border/70 px-4 py-8 text-center text-sm text-muted-foreground dark:border-sidebar-border">
                            No trained models yet. Upload data to train automatically.
                        </div>
                    ) : (
                        <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/40 text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Target</th>
                                        <th className="px-4 py-3 font-medium">Type</th>
                                        <th className="px-4 py-3 font-medium">Accuracy</th>
                                        <th className="px-4 py-3 font-medium">Source</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {models.map((model) => (
                                        <tr
                                            key={model.model_name}
                                            className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                        >
                                            <td className="px-4 py-3 font-medium">
                                                {model.target_column}
                                            </td>
                                            <td className="px-4 py-3 capitalize">
                                                {model.problem_type}
                                            </td>
                                            <td className="px-4 py-3">
                                                {typeof model.accuracy === 'number'
                                                    ? `${Math.round(model.accuracy * 100)}%`
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                                {model.schema_source ?? 'csv'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <div className="flex items-center gap-2 rounded-xl border border-violet-500/20 bg-violet-500/5 px-4 py-3 text-sm">
                    <BrainCircuit className="size-4 text-violet-500" />
                    <span className="text-muted-foreground">
                        Trained models are used by the AI Assistant for chat predictions.
                    </span>
                </div>
            </div>
        </>
    );
}

ModelTrainingIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Model training', href: index.url() },
    ],
};
