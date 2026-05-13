import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { activityLogs, index } from '@/routes/database-connections';

type LogUser = {
    id: number;
    name: string;
    email: string;
};

type LogRow = {
    id: number;
    action: string;
    connection_slug: string;
    connection_group: string;
    before: Record<string, unknown> | null;
    after: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
    user: LogUser | null;
};

type Paginator<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function DatabaseConnectionActivityLogs({
    logs,
}: {
    logs: Paginator<LogRow>;
}) {
    return (
        <>
            <Head title="Connection activity logs" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Activity logs"
                        description="Create and update events for database connections (passwords redacted)."
                    />
                    <Button variant="outline" size="sm" asChild>
                        <Link href={index.url()}>Back to connections</Link>
                    </Button>
                </div>

                <div className="space-y-4">
                    {logs.data.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No log entries yet.
                        </p>
                    )}
                    {logs.data.map((log) => (
                        <article
                            key={log.id}
                            className="space-y-3 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                        >
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="rounded-md bg-muted px-2 py-0.5 font-mono text-xs uppercase">
                                        {log.action}
                                    </span>
                                    <span className="font-mono text-sm">
                                        {log.connection_slug}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        group: {log.connection_group}
                                    </span>
                                </div>
                                <time
                                    className="text-xs text-muted-foreground"
                                    dateTime={log.created_at}
                                >
                                    {log.created_at}
                                </time>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {log.user?.name ?? 'Unknown'} ({log.user?.email}
                                ) · {log.ip_address ?? '—'}
                            </p>
                            <div className="grid gap-3 md:grid-cols-2">
                                <div>
                                    <p className="mb-1 text-xs font-medium">
                                        Before
                                    </p>
                                    <pre className="max-h-48 overflow-auto rounded-md bg-muted/50 p-2 text-[11px] leading-relaxed whitespace-pre-wrap">
                                        {log.before
                                            ? JSON.stringify(
                                                  log.before,
                                                  null,
                                                  2,
                                              )
                                            : '—'}
                                    </pre>
                                </div>
                                <div>
                                    <p className="mb-1 text-xs font-medium">
                                        After
                                    </p>
                                    <pre className="max-h-48 overflow-auto rounded-md bg-muted/50 p-2 text-[11px] leading-relaxed whitespace-pre-wrap">
                                        {log.after
                                            ? JSON.stringify(log.after, null, 2)
                                            : '—'}
                                    </pre>
                                </div>
                            </div>
                        </article>
                    ))}
                </div>

                {logs.links.length > 3 && (
                    <nav
                        className="flex flex-wrap gap-2"
                        aria-label="Pagination"
                    >
                        {logs.links.map((link, i) => {
                            const label = link.label
                                .replace(/<span[^>]*>|<\/span>/g, '')
                                .replace(/&laquo;/g, '«')
                                .replace(/&raquo;/g, '»')
                                .trim();

                            return (
                                <Button
                                    key={i}
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    disabled={!link.url}
                                    asChild={!!link.url}
                                >
                                    {link.url ? (
                                        <Link
                                            href={link.url}
                                            preserveState
                                            preserveScroll
                                        >
                                            {label}
                                        </Link>
                                    ) : (
                                        <span>{label}</span>
                                    )}
                                </Button>
                            );
                        })}
                    </nav>
                )}
            </div>
        </>
    );
}

DatabaseConnectionActivityLogs.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Connections', href: index.url() },
        { title: 'Logs', href: activityLogs.url() },
    ],
};
