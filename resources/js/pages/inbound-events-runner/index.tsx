import { Head } from '@inertiajs/react';
import { Loader2, Pause, Play, Trash2, Upload } from 'lucide-react';
import { useRef } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useInboundEventsRunner } from '@/hooks/use-inbound-events-runner';
import {
    clearRunner,
    loadCsv,
    parseCsvLogIds,
    resetRunner,
    runAll,
    setDelayMs,
    setParseError,
    stopRunner,
    type RunStatus,
} from '@/lib/inbound-events-runner-store';
import { dashboard } from '@/routes';
import { index } from '@/routes/inbound-events-runner';

function statusBadge(status: RunStatus) {
    switch (status) {
        case 'pending':
            return <Badge variant="outline">Pending</Badge>;
        case 'running':
            return (
                <Badge variant="secondary">
                    <Loader2 className="size-3 animate-spin" />
                    Running
                </Badge>
            );
        case 'success':
            return (
                <Badge className="bg-green-600 hover:bg-green-600">
                    Success
                </Badge>
            );
        case 'failed':
            return <Badge variant="destructive">Failed</Badge>;
        case 'skipped':
            return <Badge variant="outline">Skipped</Badge>;
    }
}

export default function InboundEventsRunnerIndex({
    queueNameList,
}: {
    queueNameList: string[];
}) {
    const { items, fileName, parseError, running, delayMs } =
        useInboundEventsRunner();
    const fileInputRef = useRef<HTMLInputElement>(null);

    function handleFileChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0] ?? null;

        if (!file) {
            return;
        }

        const reader = new FileReader();
        reader.onload = () => {
            try {
                const content = String(reader.result ?? '');
                const logIds = parseCsvLogIds(content);

                if (logIds.length === 0) {
                    setParseError(
                        'No log IDs found. CSV must contain an _id, log_id, or id column, or a single column of IDs.',
                    );
                    return;
                }

                loadCsv(file.name, logIds);
            } catch {
                setParseError('Failed to parse CSV file.');
            }
        };
        reader.onerror = () => setParseError('Failed to read file.');
        reader.readAsText(file);
    }

    const completedCount = items.filter(
        (item) =>
            item.status === 'success' ||
            item.status === 'failed' ||
            item.status === 'skipped',
    ).length;
    const successCount = items.filter(
        (item) => item.status === 'success',
    ).length;
    const failedCount = items.filter((item) => item.status === 'failed').length;
    const progress =
        items.length > 0
            ? Math.round((completedCount / items.length) * 100)
            : 0;
    const isComplete =
        items.length > 0 && !running && completedCount === items.length;

    function handleClear() {
        clearRunner();
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    }

    return (
        <>
            <Head title="Inbound Events Runner" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Inbound Events Runner"
                    description="Upload a CSV of log IDs and retry inbound events one by one, like a Postman collection runner."
                />

                {running && (
                    <div className="rounded-md border border-primary/30 bg-primary/5 px-4 py-3 text-sm">
                        Runner is active — you can switch menus and come back;
                        progress is preserved.
                    </div>
                )}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-4 lg:col-span-1">
                        <div className="flex flex-col gap-3 rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                            <div>
                                <p className="font-medium">Upload CSV</p>
                                <p className="mt-0.5 text-sm text-muted-foreground">
                                    File should contain an{' '}
                                    <code className="rounded bg-muted px-1 font-mono text-xs">
                                        _id
                                    </code>{' '}
                                    or{' '}
                                    <code className="rounded bg-muted px-1 font-mono text-xs">
                                        log_id
                                    </code>{' '}
                                    column.
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="csv-upload">CSV file</Label>
                                <input
                                    ref={fileInputRef}
                                    id="csv-upload"
                                    type="file"
                                    accept=".csv,.txt,text/csv"
                                    disabled={running}
                                    className="w-full text-sm text-foreground file:me-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:text-foreground"
                                    onChange={handleFileChange}
                                />
                                {fileName && (
                                    <p className="text-xs text-muted-foreground">
                                        Loaded: {fileName} ({items.length} IDs)
                                    </p>
                                )}
                            </div>

                            {parseError && (
                                <div className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                                    {parseError}
                                </div>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="delay-ms">
                                    Delay between requests (ms)
                                </Label>
                                <input
                                    id="delay-ms"
                                    type="number"
                                    min={0}
                                    max={10000}
                                    step={100}
                                    value={delayMs}
                                    disabled={running}
                                    onChange={(e) =>
                                        setDelayMs(Number(e.target.value))
                                    }
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                                />
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    disabled={items.length === 0 || running}
                                    onClick={() => void runAll()}
                                    className="flex-1 sm:flex-none"
                                >
                                    {running ? (
                                        <>
                                            <Loader2 className="size-4 animate-spin" />
                                            Running…
                                        </>
                                    ) : (
                                        <>
                                            <Play className="size-4" />
                                            Run All
                                        </>
                                    )}
                                </Button>

                                {running && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={stopRunner}
                                    >
                                        <Pause className="size-4" />
                                        Stop
                                    </Button>
                                )}

                                {items.length > 0 && !running && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={resetRunner}
                                    >
                                        Reset
                                    </Button>
                                )}

                                {isComplete && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={handleClear}
                                    >
                                        <Trash2 className="size-4" />
                                        Clear data
                                    </Button>
                                )}
                            </div>
                        </div>

                        <div className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                            <p className="mb-2 text-sm font-medium">
                                Queue name list
                            </p>
                            <div className="flex flex-wrap gap-1.5">
                                {queueNameList.map((name) => (
                                    <code
                                        key={name}
                                        className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs"
                                    >
                                        {name}
                                    </code>
                                ))}
                            </div>
                            <p className="mt-3 text-xs text-muted-foreground">
                                POST {`{ connect_url }`}/inbound/retry
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col gap-4 lg:col-span-2">
                        {items.length > 0 && (
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                                    <span className="text-xs text-muted-foreground">
                                        Total
                                    </span>
                                    <p className="text-2xl font-semibold tabular-nums">
                                        {items.length}
                                    </p>
                                </div>
                                <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                                    <span className="text-xs text-muted-foreground">
                                        Success
                                    </span>
                                    <p className="text-2xl font-semibold text-green-600 tabular-nums">
                                        {successCount}
                                    </p>
                                </div>
                                <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                                    <span className="text-xs text-muted-foreground">
                                        Failed
                                    </span>
                                    <p className="text-2xl font-semibold text-destructive tabular-nums">
                                        {failedCount}
                                    </p>
                                </div>
                                <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                                    <span className="text-xs text-muted-foreground">
                                        Progress
                                    </span>
                                    <p className="text-2xl font-semibold tabular-nums">
                                        {progress}%
                                    </p>
                                </div>
                            </div>
                        )}

                        {items.length > 0 && (
                            <div className="space-y-1">
                                <div className="h-2 overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full bg-primary transition-all duration-300"
                                        style={{ width: `${progress}%` }}
                                    />
                                </div>
                            </div>
                        )}

                        {items.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-sidebar-border/70 py-16 text-center dark:border-sidebar-border">
                                <Upload className="size-8 text-muted-foreground" />
                                <p className="text-sm text-muted-foreground">
                                    Upload a CSV file to get started
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                                <table className="w-full min-w-max text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">
                                                #
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                log_id
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Status
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                HTTP
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Response
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {items.map((item, index) => (
                                            <tr
                                                key={`${item.logId}-${index}`}
                                                className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                            >
                                                <td className="px-3 py-2 text-muted-foreground tabular-nums">
                                                    {index + 1}
                                                </td>
                                                <td className="px-3 py-2 font-mono text-xs">
                                                    {item.logId}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {statusBadge(item.status)}
                                                </td>
                                                <td className="px-3 py-2 tabular-nums">
                                                    {item.httpStatus ?? '—'}
                                                </td>
                                                <td className="max-w-md px-3 py-2">
                                                    {item.response ? (
                                                        <pre className="max-h-24 overflow-auto rounded bg-muted/60 p-2 font-mono text-xs break-all whitespace-pre-wrap">
                                                            {item.response}
                                                        </pre>
                                                    ) : item.error ? (
                                                        <pre className="max-h-24 overflow-auto rounded bg-destructive/10 p-2 font-mono text-xs break-all whitespace-pre-wrap text-destructive">
                                                            {item.error}
                                                        </pre>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

InboundEventsRunnerIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Inbound Events Runner', href: index.url() },
    ],
};
