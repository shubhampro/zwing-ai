import { Head, router, useForm } from '@inertiajs/react';
import {
    AlignLeft,
    ArrowLeft,
    Copy,
    Database,
    Download,
    FileText,
    FileUp,
    Loader2,
    MoreHorizontal,
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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

type View = 'list' | 'editor';

const EMPTY_SQL = '-- Write your SQL query here\nSELECT * FROM grn\nWHERE id = 1;';

function formatUpdatedAt(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

export default function SqlQueriesIndex({
    queries,
    schemaTables,
}: SqlQueriesIndexProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const sqlEditorRef = useRef<SqlEditorHandle>(null);
    const [view, setView] = useState<View>('list');
    const [activeId, setActiveId] = useState<number | null>(null);
    const [deletingQuery, setDeletingQuery] = useState<SavedQuery | null>(null);
    const [importing, setImporting] = useState(false);

    const activeQuery = queries.find((query) => query.id === activeId) ?? null;
    const isNewQuery = activeId === null;

    const { data, setData, post, put, processing, errors, clearErrors } =
        useForm({
            title: 'Untitled query',
            description: '',
            sql: EMPTY_SQL,
        });

    const isDirty =
        activeQuery === null
            ? data.title !== 'Untitled query' ||
              data.description !== '' ||
              data.sql !== EMPTY_SQL
            : data.title !== activeQuery.title ||
              (data.description ?? '') !== (activeQuery.description ?? '') ||
              data.sql !== activeQuery.sql;

    const openQuery = useCallback(
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
            setView('editor');
        },
        [clearErrors, setData],
    );

    function handleNewQuery() {
        openQuery(null);
    }

    function handleBackToList() {
        setView('list');
        clearErrors();
    }

    function handleSave() {
        if (activeId === null) {
            post(store.url(), {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Query saved.');
                    setView('list');
                },
            });

            return;
        }

        put(update.url(activeId), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Query updated.');
                setView('list');
            },
        });
    }

    function handleDelete() {
        if (!deletingQuery) {
            return;
        }

        const deletingId = deletingQuery.id;

        router.delete(destroyAction.url(deletingId), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Query deleted.');
                setDeletingQuery(null);

                if (activeId === deletingId) {
                    setActiveId(null);
                    setView('list');
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
                {view === 'list' ? (
                    <>
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <Heading
                                title="SQL Queries"
                                description="Save, reuse, and share SQL queries with autocomplete for common Zwing tables."
                            />
                            <Button size="sm" onClick={handleNewQuery}>
                                <Plus className="size-4" />
                                New query
                            </Button>
                        </div>

                        <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                            <table className="w-full min-w-[640px] text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-2.5 font-medium">
                                            Title
                                        </th>
                                        <th className="px-4 py-2.5 font-medium">
                                            Description
                                        </th>
                                        <th className="px-4 py-2.5 font-medium">
                                            Last updated
                                        </th>
                                        <th className="px-4 py-2.5 text-right font-medium">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {queries.length === 0 && (
                                        <tr>
                                            <td colSpan={4}>
                                                <div className="flex flex-col items-center gap-3 px-4 py-14 text-center">
                                                    <div className="flex size-11 items-center justify-center rounded-full bg-muted">
                                                        <Database className="size-5 text-muted-foreground" />
                                                    </div>
                                                    <div className="space-y-1">
                                                        <p className="text-sm font-medium">
                                                            No saved queries yet
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            Create your first
                                                            query to build your
                                                            reusable library.
                                                        </p>
                                                    </div>
                                                    <Button
                                                        size="sm"
                                                        onClick={handleNewQuery}
                                                    >
                                                        <Plus className="size-4" />
                                                        New query
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                    {queries.map((query) => (
                                        <tr
                                            key={query.id}
                                            onClick={() => openQuery(query)}
                                            className="cursor-pointer border-t border-sidebar-border/70 transition-colors hover:bg-muted/50 dark:border-sidebar-border"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2.5">
                                                    <FileText className="size-4 shrink-0 text-muted-foreground" />
                                                    <span className="font-medium">
                                                        {query.title}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="max-w-[360px] px-4 py-3 text-muted-foreground">
                                                <span className="line-clamp-1">
                                                    {query.description || '—'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                                {formatUpdatedAt(
                                                    query.updated_at,
                                                ) || '—'}
                                            </td>
                                            <td
                                                className="px-4 py-3 text-right"
                                                onClick={(event) =>
                                                    event.stopPropagation()
                                                }
                                            >
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="cursor-pointer"
                                                        >
                                                            <MoreHorizontal className="size-4" />
                                                            <span className="sr-only">
                                                                Actions
                                                            </span>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                openQuery(query)
                                                            }
                                                        >
                                                            <PenLine className="size-4" />
                                                            Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                void handleCopySavedQuery(
                                                                    query,
                                                                )
                                                            }
                                                        >
                                                            <Copy className="size-4" />
                                                            Copy SQL
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            className="text-destructive focus:text-destructive"
                                                            onSelect={() =>
                                                                setDeletingQuery(
                                                                    query,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-4" />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                ) : (
                    <>
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-start gap-3">
                                <Button
                                    size="icon"
                                    variant="outline"
                                    className="mt-0.5 shrink-0"
                                    onClick={handleBackToList}
                                    aria-label="Back to queries"
                                >
                                    <ArrowLeft className="size-4" />
                                </Button>
                                <Heading
                                    title={
                                        isNewQuery
                                            ? 'New SQL query'
                                            : 'Edit SQL query'
                                    }
                                    description="Write your SQL below with autocomplete for common Zwing tables."
                                />
                            </div>
                            <div className="flex flex-wrap gap-2">
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
                    </>
                )}
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
