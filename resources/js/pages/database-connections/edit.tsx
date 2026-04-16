import { Form, Head, Link } from '@inertiajs/react';
import DatabaseConnectionController from '@/actions/App/Http/Controllers/DatabaseConnectionController';
import DatabaseConnectionFormFields, {
    type DatabaseConnectionFormDefaults,
} from '@/components/database-connection-form-fields';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index } from '@/routes/database-connections';

export default function DatabaseConnectionsEdit({
    connection,
}: {
    connection: DatabaseConnectionFormDefaults & { id: number };
}) {
    const { id, ...defaults } = connection;

    return (
        <>
            <Head title={`Edit ${connection.slug}`} />

            <div className="flex flex-col gap-6 p-4 md:max-w-4xl md:p-6">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Edit {connection.slug}
                    </h1>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={index.url()}>Back to list</Link>
                    </Button>
                </div>

                <Form
                    {...DatabaseConnectionController.update.form(id)}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <DatabaseConnectionFormFields
                                defaults={{ ...defaults, password: '' }}
                                errors={errors}
                                passwordMode="optional"
                                connectionId={id}
                            />
                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    Save changes
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

DatabaseConnectionsEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Connections', href: index.url() },
        { title: 'Edit', href: '#' },
    ],
};
