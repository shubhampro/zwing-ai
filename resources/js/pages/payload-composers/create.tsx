import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { store } from '@/actions/App/Http/Controllers/PayloadComposerController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { create, index } from '@/routes/payload-composers';
import {
    emptyScalar,
    emptySlot,
    PayloadComposerForm,
    type SavedQueryOption,
} from './form';

type Props = {
    savedQueries: SavedQueryOption[];
    slotShapes: string[];
};

export default function PayloadComposersCreate({
    savedQueries,
    slotShapes,
}: Props) {
    const { data, setData, post, processing, errors, transform } = useForm({
        name: '',
        description: '',
        scalars: [emptyScalar()],
        slots: [emptySlot(0)],
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        transform((form) => ({
            ...form,
            slots: form.slots.map((slot, index) => ({
                ...slot,
                saved_sql_query_id: Number(slot.saved_sql_query_id),
                sort_order: index,
            })),
        }));
        post(store.url());
    }

    return (
        <>
            <Head title="New payload composer" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="New payload composer"
                        description="Define header scalars and one or more SQL query slots."
                    />
                    <Button size="sm" variant="outline" asChild>
                        <Link href={index.url()}>Back</Link>
                    </Button>
                </div>

                {savedQueries.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Save at least one SQL query first, then come back.
                    </p>
                ) : (
                    <PayloadComposerForm
                        name={data.name}
                        description={data.description}
                        scalars={data.scalars}
                        slots={data.slots}
                        savedQueries={savedQueries}
                        slotShapes={slotShapes}
                        errors={errors}
                        processing={processing}
                        submitLabel="Create composer"
                        onNameChange={(value) => setData('name', value)}
                        onDescriptionChange={(value) =>
                            setData('description', value)
                        }
                        onScalarsChange={(scalars) =>
                            setData('scalars', scalars)
                        }
                        onSlotsChange={(slots) => setData('slots', slots)}
                        onSubmit={submit}
                    />
                )}
            </div>
        </>
    );
}

PayloadComposersCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Payload composers', href: index.url() },
        { title: 'Create', href: create.url() },
    ],
};
