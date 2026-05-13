import { useRef, useState } from 'react';
import { testConnection } from '@/actions/App/Http/Controllers/DatabaseConnectionController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export type DatabaseConnectionFormDefaults = {
    slug: string;
    connection_group: string;
    driver: string;
    access_mode: string;
    label: string;
    is_active: boolean;
    writes_enabled: boolean;
    enforce_read_only_sql_guard: boolean;
    url: string;
    host: string;
    port: string | number | null;
    database: string;
    username: string;
    password?: string;
    unix_socket: string;
    charset: string;
    collation: string;
    search_path: string;
    sslmode: string;
    ssl_ca_path: string;
    mongodb_dsn: string;
    mongodb_authentication_database: string;
    mongodb_read_preference: string;
    extra_options: string;
};

const selectClass = cn(
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
);

function BooleanField({
    name,
    label,
    defaultChecked,
    error,
}: {
    name: string;
    label: string;
    defaultChecked: boolean;
    error?: string;
}) {
    return (
        <div className="flex items-center gap-2">
            <input type="hidden" name={name} value="0" />
            <input
                type="checkbox"
                name={name}
                value="1"
                defaultChecked={defaultChecked}
                id={name}
                className="size-4 rounded border border-input text-primary shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
            <Label htmlFor={name} className="font-normal">
                {label}
            </Label>
            <InputError message={error} />
        </div>
    );
}

type TestResult = { success: boolean; message: string };

function getXsrfToken(): string {
    const entry = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));
    return entry ? decodeURIComponent(entry.split('=')[1]) : '';
}

export default function DatabaseConnectionFormFields({
    defaults,
    errors,
    passwordMode,
    connectionId,
}: {
    defaults: DatabaseConnectionFormDefaults;
    errors: Partial<Record<string, string>>;
    passwordMode: 'required' | 'optional';
    connectionId?: number;
}) {
    const [driver, setDriver] = useState(defaults.driver || 'mysql');
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState<TestResult | null>(null);
    const testButtonRef = useRef<HTMLButtonElement>(null);

    const hasAdvancedErrors = !!(
        errors.url ||
        errors.unix_socket ||
        errors.charset ||
        errors.collation ||
        errors.search_path ||
        errors.ssl_ca_path ||
        errors.mongodb_read_preference ||
        errors.extra_options ||
        errors.is_active ||
        errors.writes_enabled ||
        errors.enforce_read_only_sql_guard
    );

    async function handleTest() {
        const form = testButtonRef.current?.closest(
            'form',
        ) as HTMLFormElement | null;
        if (!form) return;

        setTesting(true);
        setTestResult(null);

        const formData = new FormData(form);
        if (connectionId !== undefined) {
            formData.append('connection_id', String(connectionId));
        }

        try {
            const response = await fetch(testConnection.url(), {
                method: 'POST',
                headers: {
                    'X-XSRF-TOKEN': getXsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: formData,
            });
            const result = (await response.json()) as TestResult;
            setTestResult(result);
        } catch {
            setTestResult({
                success: false,
                message: 'Request failed. Check your network.',
            });
        } finally {
            setTesting(false);
        }
    }

    return (
        <div className="space-y-6">
            {/* Database type + access mode */}
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="driver">Database</Label>
                    <select
                        id="driver"
                        name="driver"
                        value={driver}
                        onChange={(e) => setDriver(e.target.value)}
                        className={selectClass}
                    >
                        <option value="mysql">MySQL</option>
                        <option value="pgsql">PostgreSQL</option>
                        <option value="mongodb">MongoDB</option>
                    </select>
                    <InputError message={errors.driver} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="access_mode">Connection type</Label>
                    <select
                        id="access_mode"
                        name="access_mode"
                        defaultValue={defaults.access_mode}
                        className={selectClass}
                    >
                        <option value="read">Read</option>
                        <option value="write">Write</option>
                    </select>
                    <InputError message={errors.access_mode} />
                </div>
            </div>

            {/* Identity */}
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="slug">Slug</Label>
                    <Input
                        id="slug"
                        name="slug"
                        required
                        defaultValue={defaults.slug}
                        placeholder="warehouse_read"
                        autoComplete="off"
                    />
                    <InputError message={errors.slug} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="connection_group">Group</Label>
                    <Input
                        id="connection_group"
                        name="connection_group"
                        required
                        defaultValue={defaults.connection_group}
                        placeholder="warehouse"
                        autoComplete="off"
                    />
                    <InputError message={errors.connection_group} />
                </div>
                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="label">
                        Label{' '}
                        <span className="font-normal text-muted-foreground">
                            (optional)
                        </span>
                    </Label>
                    <Input
                        id="label"
                        name="label"
                        defaultValue={defaults.label}
                        placeholder="Production read replica"
                    />
                    <InputError message={errors.label} />
                </div>
            </div>

            {/* Credentials — driver aware */}
            {driver === 'mongodb' ? (
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="mongodb_dsn">
                            Connection DSN{' '}
                            <span className="font-normal text-muted-foreground">
                                (optional)
                            </span>
                        </Label>
                        <Input
                            id="mongodb_dsn"
                            name="mongodb_dsn"
                            defaultValue={defaults.mongodb_dsn}
                            placeholder="mongodb://user:pass@host:27017/dbname"
                            autoComplete="off"
                        />
                        <InputError message={errors.mongodb_dsn} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="database">Database name</Label>
                        <Input
                            id="database"
                            name="database"
                            defaultValue={defaults.database}
                        />
                        <InputError message={errors.database} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="mongodb_authentication_database">
                            Auth database
                        </Label>
                        <Input
                            id="mongodb_authentication_database"
                            name="mongodb_authentication_database"
                            defaultValue={
                                defaults.mongodb_authentication_database
                            }
                            placeholder="admin"
                        />
                        <InputError
                            message={errors.mongodb_authentication_database}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="username">Username</Label>
                        <Input
                            id="username"
                            name="username"
                            defaultValue={defaults.username}
                            autoComplete="off"
                        />
                        <InputError message={errors.username} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password">
                            Password
                            {passwordMode === 'optional' && (
                                <span className="font-normal text-muted-foreground">
                                    {' '}
                                    — leave blank to keep
                                </span>
                            )}
                        </Label>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            required={passwordMode === 'required'}
                            defaultValue={defaults.password ?? ''}
                            autoComplete="new-password"
                        />
                        <InputError message={errors.password} />
                    </div>
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="host">Host</Label>
                        <Input
                            id="host"
                            name="host"
                            defaultValue={defaults.host}
                            placeholder="127.0.0.1"
                        />
                        <InputError message={errors.host} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="port">Port</Label>
                        <Input
                            id="port"
                            name="port"
                            type="number"
                            min={1}
                            max={65535}
                            defaultValue={
                                defaults.port === null || defaults.port === ''
                                    ? undefined
                                    : defaults.port
                            }
                            placeholder={driver === 'pgsql' ? '5432' : '3306'}
                        />
                        <InputError message={errors.port} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="database">Database name</Label>
                        <Input
                            id="database"
                            name="database"
                            defaultValue={defaults.database}
                        />
                        <InputError message={errors.database} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="username">Username</Label>
                        <Input
                            id="username"
                            name="username"
                            defaultValue={defaults.username}
                            autoComplete="off"
                        />
                        <InputError message={errors.username} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="password">
                            Password
                            {passwordMode === 'optional' && (
                                <span className="font-normal text-muted-foreground">
                                    {' '}
                                    — leave blank to keep
                                </span>
                            )}
                        </Label>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            required={passwordMode === 'required'}
                            defaultValue={defaults.password ?? ''}
                            autoComplete="new-password"
                        />
                        <InputError message={errors.password} />
                    </div>
                    {driver === 'pgsql' && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="sslmode">SSL mode</Label>
                                <Input
                                    id="sslmode"
                                    name="sslmode"
                                    defaultValue={defaults.sslmode}
                                    placeholder="prefer"
                                />
                                <InputError message={errors.sslmode} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="search_path">Search path</Label>
                                <Input
                                    id="search_path"
                                    name="search_path"
                                    defaultValue={defaults.search_path}
                                    placeholder="public"
                                />
                                <InputError message={errors.search_path} />
                            </div>
                        </>
                    )}
                </div>
            )}

            {/* Test connection */}
            <div className="flex items-center gap-3">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    ref={testButtonRef}
                    disabled={testing}
                    onClick={handleTest}
                >
                    {testing ? 'Testing…' : 'Test connection'}
                </Button>
                {testResult && (
                    <span
                        className={cn(
                            'text-sm font-medium',
                            testResult.success
                                ? 'text-green-600 dark:text-green-400'
                                : 'text-red-600 dark:text-red-400',
                        )}
                    >
                        {testResult.success ? '✓' : '✕'} {testResult.message}
                    </span>
                )}
            </div>

            {/* Advanced options */}
            <details open={hasAdvancedErrors} className="group">
                <summary className="flex cursor-pointer list-none items-center gap-1 text-sm text-muted-foreground select-none hover:text-foreground">
                    <svg
                        className="size-3.5 rotate-0 transition-transform group-open:rotate-90"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        strokeWidth={2}
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M9 5l7 7-7 7"
                        />
                    </svg>
                    Advanced options
                    {hasAdvancedErrors && (
                        <span className="ml-1 size-1.5 rounded-full bg-red-500" />
                    )}
                </summary>

                <div className="mt-4 space-y-4">
                    <div className="flex flex-wrap gap-x-6 gap-y-3">
                        <BooleanField
                            name="is_active"
                            label="Active"
                            defaultChecked={defaults.is_active}
                            error={errors.is_active}
                        />
                        <BooleanField
                            name="writes_enabled"
                            label="Writes enabled"
                            defaultChecked={defaults.writes_enabled}
                            error={errors.writes_enabled}
                        />
                        <BooleanField
                            name="enforce_read_only_sql_guard"
                            label="SQL read-only guard"
                            defaultChecked={
                                defaults.enforce_read_only_sql_guard
                            }
                            error={errors.enforce_read_only_sql_guard}
                        />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="url">
                                Connection URL{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </Label>
                            <Input
                                id="url"
                                name="url"
                                defaultValue={defaults.url}
                                placeholder="mysql://user:pass@host:3306/db"
                                autoComplete="off"
                            />
                            <InputError message={errors.url} />
                        </div>

                        {driver === 'mysql' && (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="charset">Charset</Label>
                                    <Input
                                        id="charset"
                                        name="charset"
                                        defaultValue={defaults.charset}
                                        placeholder="utf8mb4"
                                    />
                                    <InputError message={errors.charset} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="collation">Collation</Label>
                                    <Input
                                        id="collation"
                                        name="collation"
                                        defaultValue={defaults.collation}
                                        placeholder="utf8mb4_unicode_ci"
                                    />
                                    <InputError message={errors.collation} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="unix_socket">
                                        Unix socket
                                    </Label>
                                    <Input
                                        id="unix_socket"
                                        name="unix_socket"
                                        defaultValue={defaults.unix_socket}
                                    />
                                    <InputError message={errors.unix_socket} />
                                </div>
                            </>
                        )}

                        {driver === 'pgsql' && (
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="ssl_ca_path">SSL CA path</Label>
                                <Input
                                    id="ssl_ca_path"
                                    name="ssl_ca_path"
                                    defaultValue={defaults.ssl_ca_path}
                                />
                                <InputError message={errors.ssl_ca_path} />
                            </div>
                        )}

                        {driver === 'mongodb' && (
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="mongodb_read_preference">
                                    Read preference
                                </Label>
                                <Input
                                    id="mongodb_read_preference"
                                    name="mongodb_read_preference"
                                    defaultValue={
                                        defaults.mongodb_read_preference
                                    }
                                    placeholder="secondaryPreferred / primary"
                                />
                                <InputError
                                    message={errors.mongodb_read_preference}
                                />
                            </div>
                        )}

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="extra_options">
                                Extra options{' '}
                                <span className="font-normal text-muted-foreground">
                                    (JSON)
                                </span>
                            </Label>
                            <textarea
                                id="extra_options"
                                name="extra_options"
                                rows={3}
                                defaultValue={defaults.extra_options}
                                placeholder='{"pdo":{}}'
                                className={cn(
                                    'flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground',
                                    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                )}
                            />
                            <InputError message={errors.extra_options} />
                        </div>
                    </div>
                </div>
            </details>
        </div>
    );
}
