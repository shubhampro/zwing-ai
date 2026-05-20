import { Head, useForm } from '@inertiajs/react';
import { Loader2, Play } from 'lucide-react';
import { useEffect, useState } from 'react';
import { databases as databasesAction, check } from '@/actions/App/Http/Controllers/TransactionCheckerController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import { index } from '@/routes/transaction-checker';

type CheckForm = {
    connection: string;
    transaction_type: string;
    org_id: string;
    database: string;
};

type Organization = {
    id: number;
    label: string;
};

type Summary = {
    total: number;
    matched: number;
    mismatch: number;
    missing_details: number;
};

type ResultRow = Record<string, string | number | null>;

type CheckResult = {
    summary: Summary;
    rows: ResultRow[];
};

function SummaryCard({ label, value, variant = 'default' }: { label: string; value: number; variant?: 'default' | 'secondary' | 'destructive' | 'outline' }) {
    return (
        <div className="flex flex-col gap-1 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <span className="text-xs text-muted-foreground">{label}</span>
            <div className="flex items-center gap-2">
                <span className="text-2xl font-semibold tabular-nums">{value}</span>
                {value > 0 && variant !== 'default' && (
                    <Badge variant={variant} className="text-xs">!</Badge>
                )}
            </div>
        </div>
    );
}

export default function TransactionCheckerIndex({
    connections,
    transactionTypes,
    organizations,
}: {
    connections: Record<string, string>;
    transactionTypes: Record<string, string>;
    organizations: Organization[];
}) {
    const { data, setData, processing, errors } = useForm<CheckForm>({
        connection: '',
        transaction_type: '',
        org_id: '',
        database: '',
    });

    const [availableDatabases, setAvailableDatabases] = useState<string[]>([]);
    const [loadingDbs, setLoadingDbs] = useState(false);
    const [dbError, setDbError] = useState<string | null>(null);

    useEffect(() => {
        if (!data.org_id) {
            setAvailableDatabases([]);
            setData('database', '');
            return;
        }
        setLoadingDbs(true);
        setDbError(null);
        setAvailableDatabases([]);
        setData('database', '');

        fetch(databasesAction.url({ query: { org_id: data.org_id } }), {
            headers: { Accept: 'application/json' },
        })
            .then((res) => res.json())
            .then((json: { databases: string[] }) => {
                setAvailableDatabases(json.databases ?? []);
            })
            .catch((err: unknown) => {
                setDbError(err instanceof Error ? err.message : 'Failed to load databases');
            })
            .finally(() => setLoadingDbs(false));
    }, [data.org_id]);

    const [result, setResult] = useState<CheckResult | null>(null);
    const [runError, setRunError] = useState<string | null>(null);
    const [running, setRunning] = useState(false);

    async function handleRun(e: React.FormEvent) {
        e.preventDefault();
        setRunError(null);
        setResult(null);
        setRunning(true);

        try {
            const res = await fetch(check.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    Accept: 'application/json',
                },
                body: JSON.stringify(data),
            });

            if (!res.ok) {
                const json = await res.json().catch(() => ({}));
                setRunError(json?.message ?? `Request failed (${res.status})`);
                return;
            }

            const json: CheckResult = await res.json();
            setResult(json);
        } catch (err) {
            setRunError(err instanceof Error ? err.message : 'Unknown error');
        } finally {
            setRunning(false);
        }
    }

    const isReady = data.connection !== '' && data.transaction_type !== '' && data.org_id !== '' && data.database !== '';
    const resultColumns = result?.rows.length ? Object.keys(result.rows[0]) : [];

    return (
        <>
            <Head title="Transaction Checker" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Transaction Checker"
                    description="Select a database connection and transaction type to inspect and validate transaction data."
                />

                {/* Filter form */}
                <form onSubmit={handleRun} className="flex flex-col gap-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {/* Connection */}
                        <div className="space-y-2">
                            <Label htmlFor="connection">
                                Connection <span className="text-destructive">*</span>
                            </Label>
                            <Select
                                value={data.connection}
                                onValueChange={(v) => setData('connection', v)}
                            >
                                <SelectTrigger id="connection" className="w-full">
                                    <SelectValue placeholder="Select connection…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(connections).map(([key, label]) => (
                                        <SelectItem key={key} value={key}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.connection && (
                                <p className="text-xs text-destructive">{errors.connection}</p>
                            )}
                        </div>

                        {/* Transaction type */}
                        <div className="space-y-2">
                            <Label htmlFor="transaction_type">
                                Transaction Type <span className="text-destructive">*</span>
                            </Label>
                            <Select
                                value={data.transaction_type}
                                onValueChange={(v) => setData('transaction_type', v)}
                            >
                                <SelectTrigger id="transaction_type" className="w-full">
                                    <SelectValue placeholder="Select transaction…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(transactionTypes).map(([key, label]) => (
                                        <SelectItem key={key} value={key}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.transaction_type && (
                                <p className="text-xs text-destructive">{errors.transaction_type}</p>
                            )}
                        </div>

                        {/* Organization */}
                        <div className="space-y-2">
                            <Label htmlFor="org_id">
                                Organization <span className="text-destructive">*</span>
                            </Label>
                            <Select
                                value={data.org_id}
                                onValueChange={(v) => setData('org_id', v)}
                            >
                                <SelectTrigger id="org_id" className="w-full">
                                    <SelectValue placeholder="Select organization…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {organizations.map((org) => (
                                        <SelectItem key={org.id} value={String(org.id)}>
                                            {org.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.org_id && (
                                <p className="text-xs text-destructive">{errors.org_id}</p>
                            )}
                        </div>

                        {/* Database */}
                        <div className="space-y-2">
                            <Label htmlFor="database">
                                Database <span className="text-destructive">*</span>
                            </Label>
                            <Select
                                value={data.database}
                                onValueChange={(v) => setData('database', v)}
                                disabled={!data.org_id || loadingDbs || availableDatabases.length === 0}
                            >
                                <SelectTrigger id="database" className="w-full">
                                    <SelectValue
                                        placeholder={
                                            !data.org_id
                                                ? 'Select org first…'
                                                : loadingDbs
                                                  ? 'Loading…'
                                                  : availableDatabases.length === 0
                                                    ? 'No databases found'
                                                    : 'Select database…'
                                        }
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableDatabases.map((db) => (
                                        <SelectItem key={db} value={db}>
                                            {db}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {dbError && (
                                <p className="text-xs text-destructive">{dbError}</p>
                            )}
                            {errors.database && (
                                <p className="text-xs text-destructive">{errors.database}</p>
                            )}
                        </div>
                    </div>

                    <div>
                        <Button
                            type="submit"
                            disabled={!isReady || running || processing}
                            className="w-full sm:w-auto sm:min-w-36"
                        >
                            {running ? (
                                <>
                                    <Loader2 className="size-4 animate-spin" />
                                    Running…
                                </>
                            ) : (
                                <>
                                    <Play className="size-4" />
                                    Run Check
                                </>
                            )}
                        </Button>
                    </div>
                </form>

                {/* Error */}
                {runError && (
                    <div className="rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                        {runError}
                    </div>
                )}

                {/* Results */}
                {result && (
                    <div className="flex flex-col gap-4">
                        {/* Summary cards */}
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <SummaryCard label="Total" value={result.summary.total} />
                            <SummaryCard label="Matched" value={result.summary.matched} />
                            <SummaryCard label="Mismatch" value={result.summary.mismatch} variant="destructive" />
                            <SummaryCard label="Missing Details" value={result.summary.missing_details} variant="outline" />
                        </div>

                        {/* Results table */}
                        {result.rows.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                No records found for the selected filters.
                            </p>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                                <table className="w-full min-w-max text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            {resultColumns.map((col) => (
                                                <th
                                                    key={col}
                                                    className="px-3 py-2 font-medium capitalize"
                                                >
                                                    {col.replace(/_/g, ' ')}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {result.rows.map((row, i) => (
                                            <tr
                                                key={i}
                                                className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                            >
                                                {resultColumns.map((col) => (
                                                    <td
                                                        key={col}
                                                        className="px-3 py-2 tabular-nums"
                                                    >
                                                        {row[col] ?? '—'}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                <p className="border-t border-sidebar-border/70 px-3 py-2 text-xs text-muted-foreground dark:border-sidebar-border">
                                    Showing {result.rows.length} of {result.summary.total} records
                                </p>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

TransactionCheckerIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Transaction Checker', href: index.url() },
    ],
};
