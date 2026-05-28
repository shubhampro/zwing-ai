import { Head, useForm } from '@inertiajs/react';
import { store } from '@/actions/App/Http/Controllers/OrganizationController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { create, index } from '@/routes/organizations';

export default function OrganizationsCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        ba_code: '',
        vendor_id: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(store.url());
    }

    return (
        <>
            <Head title="New organization" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold">New organization</h1>
                    <p className="text-sm text-muted-foreground">
                        Add a new organization with BA code and vendor mapping.
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="flex max-w-lg flex-col gap-4"
                >
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="Organization name"
                            autoFocus
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="ba_code">BA Code</Label>
                        <Input
                            id="ba_code"
                            value={data.ba_code}
                            onChange={(e) => setData('ba_code', e.target.value)}
                            placeholder="e.g. BA-1234"
                        />
                        <InputError message={errors.ba_code} />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="vendor_id">Vendor ID</Label>
                        <Input
                            id="vendor_id"
                            type="number"
                            min={1}
                            value={data.vendor_id}
                            onChange={(e) =>
                                setData('vendor_id', e.target.value)
                            }
                            placeholder="e.g. 1001"
                        />
                        <InputError message={errors.vendor_id} />
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Creating…' : 'Create organization'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

OrganizationsCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Organizations', href: index.url() },
        { title: 'New', href: create.url() },
    ],
};
