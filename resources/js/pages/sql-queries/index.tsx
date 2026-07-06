import { Head, router, useForm } from '@inertiajs/react';
import {
    AlignLeft,
    Copy,
    Download,
    FileText,
    FileUp,
    Loader2,
    PenLine,
    Plus,
    Save,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    destroy as destroyAction,
    exportMethod as exportQuery,
    importMethod as importQuery,
    store,
    update,
} from '@/actions/App/Http/Controllers/SqlQueryController';
import Heading from '@/components/heading';
import SqlEditor, { type SqlEditorHandle } from '@/components/sql-editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { copyToClipboard } from '@/lib/copy-to-clipboard';
import { formatSql } from '@/lib/format-sql';
import { dashboard } from '@/routes';
import { index } from '@/routes/sql-queries';

type SavedQuery = {
    id: number;
    title: string;
    description: string | null;
    sql: string;
    updated_at: string | null;
};

type SqlQueriesIndexProps = {
    queries: SavedQuery[];
    schemaTables: string[];
};

const EMPTY_SQL = '-- Write your SQL query here\nSELECT * FROM grn\nWHERE id = 1;';

function formatUpdatedAt(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString();
}

export default function SqlQueriesIndex({
    queries,
    schemaTables,
}: SqlQueriesIndexProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const sqlEditorRef = useRef<SqlEditorHandle>(null);
    const [activeId, setActiveId] = useState<number | null>(
        queries[0]?.id ?? null,
    );
    const [deletingQuery, setDeletingQuery] = useState<SavedQuery | null>(null);
    const [importing, setImporting] = useState(false);

    const activeQuery = queries.find((query) => query.id === activeId) ?? null;
    const isNewQuery = activeId === null;

    const { data, setData, post, put, processing, errors, clearErrors } =
        useForm({
            title: activeQuery?.title ?? 'Untitled query',
            description: activeQuery?.description ?? '',
            sql: activeQuery?.sql ?? EMPTY_SQL,
        });

    const isDirty =
        activeQuery === null
            ? data.title !== 'Untitled query' ||
              data.description !== '' ||
              data.sql !== EMPTY_SQL
            : data.title !== activeQuery.title ||
              (data.description ?? '') !== (activeQuery.description ?? '') ||
              data.sql !== activeQuery.sql;

    const loadQuery = useCallback(
        (query: SavedQuery | null) => {
            if (query) {
                setActiveId(query.id);
                setData({
                    title: query.title,
                    description: query.description ?? '',
                    sql: query.sql,
                });
            } else {
                setActiveId(null);
                setData({
                    title: 'Untitled query',
                    description: '',
                    sql: EMPTY_SQL,
                });
            }

            clearErrors();
        },
        [clearErrors, setData],
    );

    function handleNewQuery() {
        loadQuery(null);
    }

    function handleSave() {
        if (activeId === null) {
            post(store.url(), {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Query saved.');
                },
            });

            return;
        }

        put(update.url(activeId), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Query updated.');
            },
        });
    }

    function handleDelete() {
        if (!deletingQuery) {
            return;
        }

        router.delete(destroyAction.url(deletingQuery.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Query deleted.');
                setDeletingQuery(null);

                if (activeId === deletingQuery.id) {
                    const remaining = queries.filter(
                        (query) => query.id !== deletingQuery.id,
                    );
                    loadQuery(remaining[0] ?? null);
                }
            },
        });
    }

    async function handleCopy() {
        const copyText = sqlEditorRef.current?.getCopyText() ?? data.sql;
        const copied = await copyToClipboard(copyText);

        if (!copied) {
            return;
        }

        const hasSelection = sqlEditorRef.current?.hasSelection() ?? false;
        toast.success(
            hasSelection
                ? 'Selected SQL copied to clipboard.'
                : 'SQL copied to clipboard.',
        );
    }

    async function handleCopySavedQuery(query: SavedQuery) {
        const copied = await copyToClipboard(query.sql);

        if (copied) {
            toast.success(`"${query.title}" copied to clipboard.`);
        }
    }

    function handleFormatSql() {
        const result = formatSql(data.sql);

        if (!result.success) {
            toast.error(result.message);

            return;
        }

        if (result.sql === data.sql.trim()) {
            toast.message('SQL is already formatted.');

            return;
        }

        setData('sql', result.sql);
        toast.success('SQL formatted.');
    }

    function handleExport() {
        if (activeId === null) {
            const blob = new Blob([data.sql], { type: 'application/sql' });
            const url = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = `${data.title.replace(/\s+/g, '-').toLowerCase() || 'query'}.sql`;
            anchor.click();
            URL.revokeObjectURL(url);
            toast.success('Query exported.');

            return;
        }

        window.location.href = exportQuery.url(activeId);
    }

    async function handleImportFile(file: File) {
        setImporting(true);

        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await fetch(importQuery.url(), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN':
                        decodeURIComponent(
                            document.cookie
                                .split('; ')
                                .find((row) => row.startsWith('XSRF-TOKEN='))
                                ?.split('=')[1] ?? '',
                        ) || '',
                },
                credentials: 'same-origin',
                body: formData,
            });

            const payload = (await response.json()) as {
                success: boolean;
                sql?: string;
                message?: string;
            };

            if (!response.ok || !payload.success || !payload.sql) {
                toast.error(payload.message ?? 'Could not import file.');

                return;
            }

            setData('sql', payload.sql);
            if (activeId === null) {
                setData('title', file.name.replace(/\.(sql|txt)$/i, ''));
            }

            toast.success('SQL imported into editor.');
        } catch {
            toast.error('Could not import file.');
        } finally {
            setImporting(false);
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
        }
    }

    return (
        <>
            <Head title="SQL Queries" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="SQL Queries"
                        description="Save, reuse, and share SQL queries with autocomplete for common Zwing tables."
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            variant={isNewQuery ? 'default' : 'outline'}
                            onClick={handleNewQuery}
                        >
                            <Plus className="size-4" />
                            New query
                        </Button>
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".sql,.txt"
                            className="hidden"
                            onChange={(event) => {
                                const file = event.target.files?.[0];

                                if (file) {
                                    void handleImportFile(file);
                                }
                            }}
                        />
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={importing}
                            onClick={() => fileInputRef.current?.click()}
                        >
                            {importing ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <FileUp className="size-4" />
                            )}
                            Import
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={handleExport}
                        >
                            <Download className="size-4" />
                            Export
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={handleFormatSql}
                        >
                            <AlignLeft className="size-4" />
                            Format SQL
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => void handleCopy()}
                        >
                            <Copy className="size-4" />
                            Copy
                        </Button>
                        <Button
                            size="sm"
                            onClick={handleSave}
                            disabled={processing || !isDirty}
                        >
                            {processing ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <Save className="size-4" />
                            )}
                            {isNewQuery ? 'Save query' : 'Update query'}
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <aside className="flex flex-col gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                        <div className="flex items-center justify-between">
                            <h3 className="text-sm font-medium">
                                Saved queries
                            </h3>
                            <span className="text-xs text-muted-foreground">
                                {queries.length}
                            </span>
                        </div>

                        <div className="flex max-h-[520px] flex-col gap-1 overflow-y-auto">
                            <button
                                type="button"
                                onClick={handleNewQuery}
                                className={cn(
                                    'rounded-md border px-3 py-2.5 text-left transition-colors',
                                    isNewQuery
                                        ? 'border-emerald-500/50 bg-emerald-500/10 ring-1 ring-emerald-500/20'
                                        : 'border-dashed border-muted-foreground/25 hover:border-muted-foreground/40 hover:bg-muted/50',
                                )}
                            >
                                <div className="flex items-center gap-2">
                                    <Sparkles
                                        className={cn(
                                            'size-4 shrink-0',
                                            isNewQuery
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-muted-foreground',
                                        )}
                                    />
                                    <span className="truncate text-sm font-medium">
                                        New query
                                    </span>
                                    {isNewQuery && (
                                        <Badge className="ml-auto bg-emerald-600 hover:bg-emerald-600">
                                            Draft
                                        </Badge>
                                    )}
                                </div>
                                <p className="mt-0.5 pl-6 text-xs text-muted-foreground">
                                    Start from scratch
                                </p>
                            </button>

                            {queries.length > 0 && (
                                <>
                                    <div className="my-2 border-t border-sidebar-border/70" />
                                    <p className="px-1 pb-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                        Saved
                                    </p>
                                </>
                            )}

                            {queries.length === 0 && (
                                <p className="px-2 py-3 text-center text-xs text-muted-foreground">
                                    No saved queries yet. Your first save will
                                    appear here.
                                </p>
                            )}

                            {queries.map((query) => (
                                <div
                                    key={query.id}
                                    className={cn(
                                        'group flex items-start gap-0.5 rounded-md border transition-colors',
                                        activeId === query.id
                                            ? 'border-primary/30 bg-primary/10 ring-1 ring-primary/20'
                                            : 'border-transparent hover:bg-muted/70',
                                    )}
                                >
                                    <button
                                        type="button"
                                        onClick={() => loadQuery(query)}
                                        className="min-w-0 flex-1 px-3 py-2 text-left"
                                    >
                                        <div className="flex items-center gap-2">
                                            <FileText
                                                className={cn(
                                                    'size-3.5 shrink-0',
                                                    activeId === query.id
                                                        ? 'text-primary'
                                                        : 'text-muted-foreground',
                                                )}
                                            />
                                            <p className="truncate text-sm font-medium">
                                                {query.title}
                                            </p>
                                        </div>
                                        {query.description && (
                                            <p className="mt-0.5 truncate pl-5 text-xs text-muted-foreground">
                                                {query.description}
                                            </p>
                                        )}
                                        <p className="mt-1 pl-5 text-[11px] text-muted-foreground">
                                            {formatUpdatedAt(query.updated_at)}
                                        </p>
                                    </button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="mt-1 mr-1 size-7 shrink-0 opacity-60 transition-opacity group-hover:opacity-100"
                                        title={`Copy "${query.title}" SQL`}
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            void handleCopySavedQuery(query);
                                        }}
                                    >
                                        <Copy className="size-3.5" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    </aside>

                    <div
                        className={cn(
                            'flex flex-col gap-4 rounded-xl border p-4 md:p-5',
                            isNewQuery
                                ? 'border-emerald-500/30 bg-emerald-500/[0.03]'
                                : 'border-primary/20 bg-primary/[0.02]',
                        )}
                    >
                        <div className="flex flex-col gap-3 border-b border-border/60 pb-4 sm:flex-row sm:items-start sm:justify-between">
                            <div className="space-y-1.5">
                                <div className="flex flex-wrap items-center gap-2">
                                    {isNewQuery ? (
                                        <Badge className="gap-1 bg-emerald-600 hover:bg-emerald-600">
                                            <Sparkles className="size-3" />
                                            New query
                                        </Badge>
                                    ) : (
                                        <Badge
                                            variant="secondary"
                                            className="gap-1"
                                        >
                                            <PenLine className="size-3" />
                                            Editing saved query
                                        </Badge>
                                    )}
                                    {isDirty && (
                                        <Badge variant="outline">
                                            Unsaved changes
                                        </Badge>
                                    )}
                                </div>
                                <h2 className="text-lg font-semibold tracking-tight">
                                    {isNewQuery
                                        ? 'Create a new SQL query'
                                        : data.title}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {isNewQuery
                                        ? 'Write your SQL below, then save it to your library.'
                                        : activeQuery?.updated_at
                                          ? `Last updated ${formatUpdatedAt(activeQuery.updated_at)}`
                                          : 'Update the fields below and click Update query.'}
                                </p>
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="query-title">Title</Label>
                                <Input
                                    id="query-title"
                                    value={data.title}
                                    onChange={(event) =>
                                        setData('title', event.target.value)
                                    }
                                    placeholder="GRN pending sync check"
                                />
                                {errors.title && (
                                    <p className="text-sm text-destructive">
                                        {errors.title}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2 sm:col-span-1">
                                <Label htmlFor="query-description">
                                    Description (optional)
                                </Label>
                                <textarea
                                    id="query-description"
                                    value={data.description}
                                    onChange={(event) =>
                                        setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Notes for teammates…"
                                    rows={2}
                                    className="border-input placeholder:text-muted-foreground flex min-h-[60px] w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] md:text-sm"
                                />
                                {errors.description && (
                                    <p className="text-sm text-destructive">
                                        {errors.description}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label>SQL</Label>
                                <p className="text-xs text-muted-foreground">
                                    Select text to copy one query · Ctrl+Space
                                    for suggestions
                                </p>
                            </div>
                            <SqlEditor
                                ref={sqlEditorRef}
                                value={data.sql}
                                onChange={(value) => setData('sql', value)}
                                schemaTables={schemaTables}
                                height="460px"
                            />
                            {errors.sql && (
                                <p className="text-sm text-destructive">
                                    {errors.sql}
                                </p>
                            )}
                        </div>

                        {activeQuery && (
                            <div className="flex justify-end">
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    onClick={() =>
                                        setDeletingQuery(activeQuery)
                                    }
                                >
                                    <Trash2 className="size-4" />
                                    Delete query
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <Dialog
                open={deletingQuery !== null}
                onOpenChange={(open) => !open && setDeletingQuery(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete query?</DialogTitle>
                        <DialogDescription>
                            This will permanently remove &ldquo;
                            {deletingQuery?.title}&rdquo; from your library.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeletingQuery(null)}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

SqlQueriesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'SQL Queries', href: index.url() },
    ],
};
