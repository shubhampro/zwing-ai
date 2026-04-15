import InputError from '@/components/input-error';
import Heading from '@/components/heading';
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
    ssh_tunnel: string;
    extra_options: string;
};

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
        <div className="grid gap-2">
            <div className="flex items-center gap-2">
                <input type="hidden" name={name} value="0" />
                <input
                    type="checkbox"
                    name={name}
                    value="1"
                    defaultChecked={defaultChecked}
                    id={name}
                    className="border-input text-primary focus-visible:ring-ring/50 size-4 rounded border shadow-xs outline-none focus-visible:ring-[3px]"
                />
                <Label htmlFor={name} className="font-normal">
                    {label}
                </Label>
            </div>
            <InputError message={error} />
        </div>
    );
}

export default function DatabaseConnectionFormFields({
    defaults,
    errors,
    passwordMode,
}: {
    defaults: DatabaseConnectionFormDefaults;
    errors: Partial<Record<string, string>>;
    passwordMode: 'required' | 'optional';
}) {
    return (
        <div className="space-y-10">
            <div className="space-y-4">
                <Heading
                    variant="small"
                    title="Identity"
                    description="Slug is the Laravel connection name (letters, numbers, hyphen, underscore)."
                />
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
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
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="connection_group">Connection group</Label>
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
                    <div className="grid gap-2">
                        <Label htmlFor="driver">Driver</Label>
                        <select
                            id="driver"
                            name="driver"
                            required
                            defaultValue={defaults.driver}
                            className={cn(
                                'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
                                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                            )}
                        >
                            <option value="mysql">MySQL</option>
                            <option value="pgsql">PostgreSQL</option>
                            <option value="mongodb">MongoDB</option>
                        </select>
                        <InputError message={errors.driver} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="access_mode">Access mode</Label>
                        <select
                            id="access_mode"
                            name="access_mode"
                            required
                            defaultValue={defaults.access_mode}
                            className={cn(
                                'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
                                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                            )}
                        >
                            <option value="read">Read</option>
                            <option value="write">Write</option>
                        </select>
                        <InputError message={errors.access_mode} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="label">Label (optional)</Label>
                        <Input
                            id="label"
                            name="label"
                            defaultValue={defaults.label}
                            placeholder="Warehouse reporting (read replica)"
                        />
                        <InputError message={errors.label} />
                    </div>
                </div>
                <div className="grid gap-4 sm:grid-cols-3">
                    <BooleanField
                        name="is_active"
                        label="Active (register on boot)"
                        defaultChecked={defaults.is_active}
                        error={errors.is_active}
                    />
                    <BooleanField
                        name="writes_enabled"
                        label="Writes enabled (write rows only)"
                        defaultChecked={defaults.writes_enabled}
                        error={errors.writes_enabled}
                    />
                    <BooleanField
                        name="enforce_read_only_sql_guard"
                        label="SQL read-only guard (read rows)"
                        defaultChecked={defaults.enforce_read_only_sql_guard}
                        error={errors.enforce_read_only_sql_guard}
                    />
                </div>
            </div>

            <div className="space-y-4">
                <Heading
                    variant="small"
                    title="Credentials"
                    description="Use read-only database users for read connections when possible."
                />
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="url">URL (optional)</Label>
                        <Input
                            id="url"
                            name="url"
                            defaultValue={defaults.url}
                            placeholder="mysql://user:pass@host:3306/db"
                            autoComplete="off"
                        />
                        <InputError message={errors.url} />
                    </div>
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
                            placeholder="3306"
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
                                <span className="text-muted-foreground font-normal">
                                    {' '}
                                    — leave blank to keep current
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
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="unix_socket">Unix socket (MySQL)</Label>
                        <Input
                            id="unix_socket"
                            name="unix_socket"
                            defaultValue={defaults.unix_socket}
                        />
                        <InputError message={errors.unix_socket} />
                    </div>
                </div>
            </div>

            <div className="space-y-4">
                <Heading
                    variant="small"
                    title="Driver options"
                    description="PostgreSQL search path / SSL, MySQL charset, MongoDB DSN."
                />
                <div className="grid gap-4 sm:grid-cols-2">
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
                    <div className="grid gap-2">
                        <Label htmlFor="search_path">Search path (PostgreSQL)</Label>
                        <Input
                            id="search_path"
                            name="search_path"
                            defaultValue={defaults.search_path}
                            placeholder="public"
                        />
                        <InputError message={errors.search_path} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="sslmode">SSL mode (PostgreSQL)</Label>
                        <Input
                            id="sslmode"
                            name="sslmode"
                            defaultValue={defaults.sslmode}
                            placeholder="prefer"
                        />
                        <InputError message={errors.sslmode} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="ssl_ca_path">SSL CA path</Label>
                        <Input
                            id="ssl_ca_path"
                            name="ssl_ca_path"
                            defaultValue={defaults.ssl_ca_path}
                        />
                        <InputError message={errors.ssl_ca_path} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="mongodb_dsn">MongoDB DSN (optional)</Label>
                        <Input
                            id="mongodb_dsn"
                            name="mongodb_dsn"
                            defaultValue={defaults.mongodb_dsn}
                            autoComplete="off"
                        />
                        <InputError message={errors.mongodb_dsn} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="mongodb_authentication_database">
                            MongoDB auth database
                        </Label>
                        <Input
                            id="mongodb_authentication_database"
                            name="mongodb_authentication_database"
                            defaultValue={defaults.mongodb_authentication_database}
                            placeholder="admin"
                        />
                        <InputError message={errors.mongodb_authentication_database} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="mongodb_read_preference">
                            MongoDB read preference
                        </Label>
                        <Input
                            id="mongodb_read_preference"
                            name="mongodb_read_preference"
                            defaultValue={defaults.mongodb_read_preference}
                            placeholder="secondaryPreferred / primary"
                        />
                        <InputError message={errors.mongodb_read_preference} />
                    </div>
                </div>
            </div>

            <div className="space-y-4">
                <Heading
                    variant="small"
                    title="Advanced JSON"
                    description="Optional. SSH tunnel is metadata only; open the tunnel yourself and point host/port at localhost."
                />
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="ssh_tunnel">SSH tunnel (JSON)</Label>
                        <textarea
                            id="ssh_tunnel"
                            name="ssh_tunnel"
                            rows={4}
                            defaultValue={defaults.ssh_tunnel}
                            placeholder='{"bastion":"...","local_port":27018}'
                            className={cn(
                                'border-input placeholder:text-muted-foreground flex w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none',
                                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                            )}
                        />
                        <InputError message={errors.ssh_tunnel} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="extra_options">Extra options (JSON)</Label>
                        <textarea
                            id="extra_options"
                            name="extra_options"
                            rows={4}
                            defaultValue={defaults.extra_options}
                            placeholder='{"pdo":{},"mongo":{}}'
                            className={cn(
                                'border-input placeholder:text-muted-foreground flex w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none',
                                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                            )}
                        />
                        <InputError message={errors.extra_options} />
                    </div>
                </div>
            </div>
        </div>
    );
}
