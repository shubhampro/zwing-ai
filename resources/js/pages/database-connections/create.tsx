import { Form, Head, Link } from '@inertiajs/react';
import DatabaseConnectionController from '@/actions/App/Http/Controllers/DatabaseConnectionController';
import DatabaseConnectionFormFields from '@/components/database-connection-form-fields';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index } from '@/routes/database-connections';

const defaults = {
    slug: '',
    connection_group: '',
    driver: 'mysql',
    access_mode: 'read',
    label: '',
    is_active: true,
    writes_enabled: false,
    enforce_read_only_sql_guard: true,
    url: '',
    host: '127.0.0.1',
    port: '',
    database: '',
    username: '',
    password: '',
    unix_socket: '',
    charset: 'utf8mb4',
    collation: 'utf8mb4_unicode_ci',
    search_path: 'public',
    sslmode: 'prefer',
    ssl_ca_path: '',
    mongodb_dsn: '',
    mongodb_authentication_database: 'admin',
    mongodb_read_preference: '',
    ssh_tunnel: '',
    extra_options: '',
};

export default function DatabaseConnectionsCreate() {
    return (
        <>
            <Head title="Add database connection" />

            <div className="flex flex-col gap-6 p-4 md:max-w-4xl md:p-6">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Add connection
                    </h1>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={index.url()}>Back to list</Link>
                    </Button>
                </div>

                <Form
                    {...DatabaseConnectionController.store.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <DatabaseConnectionFormFields
                                defaults={defaults}
                                errors={errors}
                                passwordMode="required"
                            />
                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    Save connection
                                </Button>
                                <Button variant="outline" type="button" asChild>
                                    <Link href={index.url()}>Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

DatabaseConnectionsCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Connections', href: index.url() },
        { title: 'Add', href: '#' },
    ],
};
