import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import {
    activityLogs,
    create,
    edit,
    index,
} from '@/routes/database-connections';

type ConnectionRow = {
    id: number;
    slug: string;
    connection_group: string;
    driver: string;
    access_mode: string;
    label: string | null;
    is_active: boolean;
    writes_enabled: boolean;
    enforce_read_only_sql_guard: boolean;
    updated_at: string | null;
};

export default function DatabaseConnectionsIndex({
    connections,
}: {
    connections: ConnectionRow[];
}) {
    return (
        <>
            <Head title="Database connections" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Database connections"
                        description="Manage remote connections stored in the database. Reads default to SQL guard unless disabled on the row."
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={activityLogs.url()}>Activity logs</Link>
                        </Button>
                        <Button size="sm" asChild>
                            <Link href={create.url()}>Add connection</Link>
                        </Button>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full min-w-[640px] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Slug</th>
                                <th className="px-3 py-2 font-medium">Group</th>
                                <th className="px-3 py-2 font-medium">Driver</th>
                                <th className="px-3 py-2 font-medium">Mode</th>
                                <th className="px-3 py-2 font-medium">Active</th>
                                <th className="px-3 py-2 font-medium">Writes</th>
                                <th className="px-3 py-2 font-medium">Guard</th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {connections.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="text-muted-foreground px-3 py-8 text-center"
                                    >
                                        No connections yet.{' '}
                                        <Link
                                            href={create.url()}
                                            className="text-foreground underline"
                                        >
                                            Add one
                                        </Link>
                                        .
                                    </td>
                                </tr>
                            )}
                            {connections.map((c) => (
                                <tr
                                    key={c.id}
                                    className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                >
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {c.slug}
                                    </td>
                                    <td className="px-3 py-2">{c.connection_group}</td>
                                    <td className="px-3 py-2">{c.driver}</td>
                                    <td className="px-3 py-2">{c.access_mode}</td>
                                    <td className="px-3 py-2">
                                        {c.is_active ? 'Yes' : 'No'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {c.writes_enabled ? 'Yes' : 'No'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {c.enforce_read_only_sql_guard ? 'Yes' : 'No'}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <Button variant="ghost" size="sm" asChild>
                                            <Link href={edit.url(c.id)}>Edit</Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

DatabaseConnectionsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Connections', href: index.url() },
    ],
};
