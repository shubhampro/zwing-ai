import { Head, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { run } from '@/actions/App/Http/Controllers/QueryTableController';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/SavedQueryController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import { index as queryTableIndex } from '@/routes/query-table';
import type { ActiveDatabaseContext, SavedQuerySummary } from '@/types';

type QueryRunResponse = {
    columns: string[];
    rows: Record<string, unknown>[];
    row_count: number;
    truncated: boolean;
};

function getXsrfToken(): string {
    const entry = document.cookie.split('; ').find((row) => row.startsWith('XSRF-TOKEN='));

    return entry ? decodeURIComponent(entry.split('=')[1]) : '';
}

const defaultSql = `SELECT *
FROM your_table t
INNER JOIN other_table o ON o.id = t.foreign_id
WHERE t.status = :status
LIMIT 100`;

const defaultBindingsJson = `{
  "status": "active"
}`;

function parseBindingsJson(text: string): { ok: true; bindings: Record<string, unknown> } | { ok: false; error: string } {
    const trimmed = text.trim();
    if (trimmed === '') {
        return { ok: true, bindings: {} };
    }

    try {
        const parsed: unknown = JSON.parse(trimmed);
        if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
            return { ok: false, error: 'Parameters must be a JSON object, e.g. { "id": 1 }.' };
        }

        return { ok: true, bindings: parsed as Record<string, unknown> };
    } catch {
        return { ok: false, error: 'Invalid JSON for parameters.' };
    }
}

function formatBindingsJson(bindings: Record<string, unknown>): string {
    if (Object.keys(bindings).length === 0) {
        return '{}';
    }

    return `${JSON.stringify(bindings, null, 2)}\n`;
}

export default function QueryTableIndex() {
    const page = usePage<{
        activeDatabaseContext: ActiveDatabaseContext | null;
        savedQueries: SavedQuerySummary[];
    }>();
    const activeContext = page.props.activeDatabaseContext;
    const initialSaved = page.props.savedQueries;

    const [sql, setSql] = useState(defaultSql);
    const [bindingsText, setBindingsText] = useState(defaultBindingsJson);
    const [parseError, setParseError] = useState<string | null>(null);
    const [apiError, setApiError] = useState<string | null>(null);
    const [saveError, setSaveError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [result, setResult] = useState<QueryRunResponse | null>(null);

    const [saveName, setSaveName] = useState('');
    const [editingSavedQueryId, setEditingSavedQueryId] = useState<number | null>(null);
    const [savedList, setSavedList] = useState<SavedQuerySummary[]>(initialSaved);

    useEffect(() => {
        setSavedList(page.props.savedQueries);
    }, [page.props.savedQueries]);

    const runQuery = useCallback(async () => {
        setParseError(null);
        setApiError(null);
        setResult(null);

        const parsed = parseBindingsJson(bindingsText);
        if (!parsed.ok) {
            setParseError(parsed.error);

            return;
        }

        setLoading(true);
        try {
            const response = await fetch(run.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ query: sql, bindings: parsed.bindings }),
            });

            const payload = (await response.json()) as QueryRunResponse & { message?: string };

            if (!response.ok) {
                setApiError(payload.message ?? 'Request failed.');

                return;
            }

            setResult({
                columns: payload.columns,
                rows: payload.rows,
                row_count: payload.row_count,
                truncated: payload.truncated,
            });
        } catch {
            setApiError('Network error. Try again.');
        } finally {
            setLoading(false);
        }
    }, [bindingsText, sql]);

    const saveOrUpdate = useCallback(async () => {
        setSaveError(null);
        const name = saveName.trim();
        if (name === '') {
            setSaveError('Enter a name to save this query.');

            return;
        }

        const parsed = parseBindingsJson(bindingsText);
        if (!parsed.ok) {
            setSaveError(parsed.error);

            return;
        }

        setSaving(true);
        try {
            const body = { name, sql, bindings: parsed.bindings };

            if (editingSavedQueryId !== null) {
                const response = await fetch(update.url(editingSavedQueryId), {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });
                const payload = (await response.json()) as { data?: SavedQuerySummary; message?: string };
                if (!response.ok) {
                    setSaveError(payload.message ?? 'Could not update.');
                    return;
                }
                if (payload.data) {
                    setSavedList((prev) => {
                        const next = prev.filter((q) => q.id !== payload.data!.id);
                        return [payload.data!, ...next];
                    });
                }
            } else {
                const response = await fetch(store.url(), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });
                const payload = (await response.json()) as { data?: SavedQuerySummary; message?: string };
                if (!response.ok) {
                    setSaveError(payload.message ?? 'Could not save.');
                    return;
                }
                if (payload.data) {
                    setSavedList((prev) => [payload.data!, ...prev]);
                    setEditingSavedQueryId(payload.data.id);
                    setSaveName(payload.data.name);
                }
            }
        } catch {
            setSaveError('Network error. Try again.');
        } finally {
            setSaving(false);
        }
    }, [bindingsText, editingSavedQueryId, saveName, sql]);

    const loadSaved = useCallback((row: SavedQuerySummary) => {
        setSql(row.sql);
        setBindingsText(formatBindingsJson(row.bindings));
        setSaveName(row.name);
        setEditingSavedQueryId(row.id);
        setParseError(null);
        setApiError(null);
        setSaveError(null);
        setResult(null);
    }, []);

    const deleteSaved = useCallback(async (id: number) => {
        setSaveError(null);
        try {
            const response = await fetch(destroy.url(id), {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                const payload = (await response.json().catch(() => ({}))) as { message?: string };
                setSaveError(payload.message ?? 'Could not delete.');

                return;
            }
            setSavedList((prev) => prev.filter((q) => q.id !== id));
            if (editingSavedQueryId === id) {
                setEditingSavedQueryId(null);
                setSaveName('');
            }
        } catch {
            setSaveError('Network error. Try again.');
        }
    }, [editingSavedQueryId]);

    const clearSavedSelection = useCallback(() => {
        setEditingSavedQueryId(null);
        setSaveName('');
        setSaveError(null);
    }, []);

    return (
        <>
            <Head title="Query table" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">Query table</h1>
                    <p className="text-muted-foreground mt-1 max-w-2xl text-sm">
                        Run a parameterized <code className="text-foreground">SELECT</code> (with{' '}
                        <code className="text-foreground">JOIN</code>s if needed) against the connection
                        and database chosen in the header. Use <code className="text-foreground">:name</code>{' '}
                        placeholders and pass values as JSON keys matching those names. Save queries to reload
                        later.
                    </p>
                </div>

                {activeContext === null && (
                    <div
                        className="rounded-md border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-950 dark:text-amber-100"
                        role="status"
                    >
                        Select a connection (and MySQL database) in the header before running a query.
                    </div>
                )}

                <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="bg-muted/30 border-b px-4 py-3">
                        <h2 className="text-sm font-medium">Saved queries</h2>
                        <p className="text-muted-foreground mt-0.5 text-xs">
                            Stored per user. Load one to edit, or save the editor as a new entry.
                        </p>
                    </div>
                    {savedList.length === 0 ? (
                        <p className="text-muted-foreground px-4 py-6 text-sm">No saved queries yet.</p>
                    ) : (
                        <div className="max-h-56 overflow-auto">
                            <table className="w-full border-collapse text-sm">
                                <thead>
                                    <tr className="bg-muted/40 border-b text-left">
                                        <th className="text-foreground px-4 py-2 font-medium">Name</th>
                                        <th className="text-foreground px-4 py-2 font-medium">Updated</th>
                                        <th className="text-foreground w-0 px-4 py-2 font-medium whitespace-nowrap">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {savedList.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-sidebar-border/50 border-b last:border-b-0"
                                        >
                                            <td className="px-4 py-2">
                                                <span className="font-medium">{row.name}</span>
                                                {editingSavedQueryId === row.id && (
                                                    <span className="text-muted-foreground ml-2 text-xs">
                                                        (editing)
                                                    </span>
                                                )}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-2 text-xs">
                                                {row.updated_at
                                                    ? new Date(row.updated_at).toLocaleString()
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-2">
                                                <div className="flex flex-wrap gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => loadSaved(row)}
                                                    >
                                                        Load
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() => void deleteSaved(row.id)}
                                                    >
                                                        Delete
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="grid gap-2">
                        <label htmlFor="query-sql" className="text-sm font-medium">
                            SQL
                        </label>
                        <textarea
                            id="query-sql"
                            value={sql}
                            onChange={(e) => setSql(e.target.value)}
                            spellCheck={false}
                            className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex min-h-[220px] w-full rounded-md border bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                        />
                    </div>
                    <div className="grid gap-2">
                        <label htmlFor="query-bindings" className="text-sm font-medium">
                            Parameters (JSON object)
                        </label>
                        <textarea
                            id="query-bindings"
                            value={bindingsText}
                            onChange={(e) => setBindingsText(e.target.value)}
                            spellCheck={false}
                            className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex min-h-[220px] w-full rounded-md border bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                        />
                    </div>
                </div>

                <div className="flex flex-col gap-3 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border sm:flex-row sm:items-end">
                    <div className="grid min-w-0 flex-1 gap-2">
                        <label htmlFor="save-query-name" className="text-sm font-medium">
                            Save as
                        </label>
                        <Input
                            id="save-query-name"
                            value={saveName}
                            onChange={(e) => setSaveName(e.target.value)}
                            placeholder="e.g. Monthly stock reconciliation"
                            autoComplete="off"
                        />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" disabled={saving} onClick={() => void saveOrUpdate()}>
                            {saving
                                ? 'Saving…'
                                : editingSavedQueryId !== null
                                  ? 'Update saved query'
                                  : 'Save query'}
                        </Button>
                        {editingSavedQueryId !== null && (
                            <Button type="button" variant="outline" onClick={clearSavedSelection}>
                                New query
                            </Button>
                        )}
                    </div>
                </div>

                {(parseError !== null || apiError !== null) && (
                    <div
                        className="rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm break-words whitespace-pre-wrap text-destructive"
                        role="alert"
                    >
                        {parseError ?? apiError}
                    </div>
                )}

                {saveError !== null && (
                    <div
                        className="rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                        role="alert"
                    >
                        {saveError}
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-3">
                    <Button type="button" onClick={() => void runQuery()} disabled={loading}>
                        {loading ? 'Running…' : 'Run query'}
                    </Button>
                    <span className="text-muted-foreground text-xs">
                        Read-only; up to {1000} rows returned. Server adds a safety limit.
                    </span>
                </div>

                {result !== null && result.columns.length > 0 && (
                    <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="max-h-[min(60vh,32rem)] overflow-auto">
                            <table className="w-full border-collapse text-sm">
                                <thead>
                                    <tr className="bg-muted/50 border-b text-left">
                                        {result.columns.map((col) => (
                                            <th
                                                key={col}
                                                className="text-foreground px-3 py-2 font-medium whitespace-nowrap"
                                            >
                                                {col}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {result.rows.map((row, idx) => (
                                        <tr
                                            key={idx}
                                            className="border-sidebar-border/50 border-b last:border-b-0"
                                        >
                                            {result.columns.map((col) => (
                                                <td
                                                    key={col}
                                                    className="text-muted-foreground max-w-[20rem] truncate px-3 py-2 font-mono text-xs"
                                                    title={String(row[col] ?? '')}
                                                >
                                                    {formatCell(row[col])}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="text-muted-foreground flex flex-wrap items-center gap-2 border-t px-3 py-2 text-xs">
                            <span>
                                {result.row_count} row{result.row_count === 1 ? '' : 's'}
                            </span>
                            {result.truncated && (
                                <span className="text-amber-700 dark:text-amber-400">
                                    Results truncated at {1000} rows; narrow your query.
                                </span>
                            )}
                        </div>
                    </div>
                )}

                {result !== null && result.columns.length === 0 && (
                    <p className="text-muted-foreground text-sm">Query returned no rows.</p>
                )}
            </div>
        </>
    );
}

function formatCell(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }
    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

QueryTableIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Query table', href: queryTableIndex.url() },
    ],
};
