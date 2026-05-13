import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
    databases,
    update,
} from '@/actions/App/Http/Controllers/DatabaseSessionContextController';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { DatabaseConnectionOption } from '@/types';

function getXsrfToken(): string {
    const entry = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));

    return entry ? decodeURIComponent(entry.split('=')[1]) : '';
}

function connectionLabel(connection: DatabaseConnectionOption): string {
    if (connection.label && connection.label.trim() !== '') {
        return `${connection.label} (${connection.slug})`;
    }

    return connection.slug;
}

type DatabaseContextSelectorProps = {
    className?: string;
    /**
     * When set, limit to Zwing (MySQL) or ERP (PostgreSQL) connections.
     */
    system?: 'zwing' | 'erp';
};

function filterConnectionsForSystem(
    all: DatabaseConnectionOption[],
    system: 'zwing' | 'erp' | undefined,
): DatabaseConnectionOption[] {
    if (system === 'zwing') {
        return all.filter((c) => c.driver === 'mysql');
    }

    if (system === 'erp') {
        return all.filter((c) => c.driver === 'pgsql');
    }

    return all;
}

export function DatabaseContextSelector({
    className,
    system,
}: DatabaseContextSelectorProps = {}) {
    const page = usePage();
    const allConnections = page.props.databaseConnectionsForSelector;
    const connections = useMemo(
        () => filterConnectionsForSystem(allConnections, system),
        [allConnections, system],
    );
    const active = page.props.activeDatabaseContext;

    const [mysqlDatabases, setMysqlDatabases] = useState<string[]>([]);
    const [loadingDatabases, setLoadingDatabases] = useState(false);
    const [saving, setSaving] = useState(false);

    const activeSlug = active?.connection_slug ?? '';
    const activeInFilteredScope = connections.some(
        (c) => c.slug === activeSlug,
    );
    const selectedSlug = activeInFilteredScope ? activeSlug : '';
    const selectedMeta = connections.find((c) => c.slug === selectedSlug);
    const isMysql = selectedMeta?.driver === 'mysql';

    const persistSelection = useCallback(
        async (connectionSlug: string, database: string | null) => {
            setSaving(true);
            try {
                const response = await fetch(update.url(), {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        connection_slug: connectionSlug,
                        database,
                    }),
                });

                if (!response.ok) {
                    const payload = (await response
                        .json()
                        .catch(() => ({}))) as {
                        message?: string;
                    };
                    throw new Error(
                        payload.message ?? 'Could not save selection.',
                    );
                }

                router.reload({
                    only: [
                        'activeDatabaseContext',
                        'databaseConnectionsForSelector',
                    ],
                });
            } finally {
                setSaving(false);
            }
        },
        [],
    );

    useEffect(() => {
        if (!isMysql || selectedSlug === '') {
            setMysqlDatabases([]);
            setLoadingDatabases(false);

            return;
        }

        let cancelled = false;

        async function loadDatabases(): Promise<void> {
            setLoadingDatabases(true);
            try {
                const response = await fetch(
                    databases.url({
                        query: { connection_slug: selectedSlug },
                    }),
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    },
                );
                const payload = (await response.json()) as {
                    data?: string[];
                    message?: string;
                };

                if (!response.ok) {
                    setMysqlDatabases([]);
                    return;
                }

                if (!cancelled) {
                    setMysqlDatabases(payload.data ?? []);
                }
            } catch {
                if (!cancelled) {
                    setMysqlDatabases([]);
                }
            } finally {
                if (!cancelled) {
                    setLoadingDatabases(false);
                }
            }
        }

        void loadDatabases();

        return () => {
            cancelled = true;
        };
    }, [isMysql, selectedSlug]);

    async function handleConnectionChange(slug: string): Promise<void> {
        await persistSelection(slug, null);
    }

    async function handleDatabaseChange(database: string): Promise<void> {
        if (selectedSlug === '') {
            return;
        }

        await persistSelection(selectedSlug, database);
    }

    if (allConnections.length === 0) {
        return null;
    }

    if (connections.length === 0) {
        return (
            <p className="text-sm text-muted-foreground" role="status">
                {system === 'zwing' &&
                    'No Zwing (MySQL) connections are configured.'}
                {system === 'erp' &&
                    'No ERP (PostgreSQL) connections are configured.'}
            </p>
        );
    }

    return (
        <div
            className={cn(
                'ml-auto flex w-full max-w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-start sm:gap-4',
                isMysql &&
                    selectedSlug !== '' &&
                    'sm:max-w-[min(100%,22rem)] md:max-w-[26rem]',
                (!isMysql || selectedSlug === '') &&
                    'sm:max-w-[11rem] md:max-w-[13rem]',
                className,
            )}
        >
            <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                <Label
                    htmlFor="db-context-connection"
                    className="sr-only px-0.5 text-xs font-medium tracking-tight text-muted-foreground sm:not-sr-only"
                >
                    Connection
                </Label>
                <Select
                    value={selectedSlug === '' ? undefined : selectedSlug}
                    onValueChange={(value) => {
                        void handleConnectionChange(value);
                    }}
                    disabled={saving}
                >
                    <SelectTrigger
                        id="db-context-connection"
                        size="sm"
                        className="h-8 w-full min-w-0 shadow-sm [&_[data-slot=select-value]]:truncate"
                    >
                        <SelectValue placeholder="Select connection" />
                    </SelectTrigger>
                    <SelectContent align="start">
                        {connections.map((connection) => (
                            <SelectItem
                                key={connection.slug}
                                value={connection.slug}
                            >
                                {connectionLabel(connection)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {isMysql && selectedSlug !== '' && (
                <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                    <Label
                        htmlFor="db-context-database"
                        className="sr-only px-0.5 text-xs font-medium tracking-tight text-muted-foreground sm:not-sr-only"
                    >
                        Database
                    </Label>
                    <Select
                        value={
                            !activeInFilteredScope ||
                            active?.database === null ||
                            active?.database === ''
                                ? undefined
                                : active?.database
                        }
                        onValueChange={(value) => {
                            void handleDatabaseChange(value);
                        }}
                        disabled={
                            saving ||
                            loadingDatabases ||
                            mysqlDatabases.length === 0
                        }
                    >
                        <SelectTrigger
                            id="db-context-database"
                            size="sm"
                            className="h-8 w-full min-w-0 shadow-sm [&_[data-slot=select-value]]:truncate"
                        >
                            <SelectValue
                                placeholder={
                                    loadingDatabases
                                        ? 'Loading databases…'
                                        : 'Select database'
                                }
                            />
                        </SelectTrigger>
                        <SelectContent align="start">
                            {mysqlDatabases.map((name) => (
                                <SelectItem key={name} value={name}>
                                    {name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            )}
        </div>
    );
}
