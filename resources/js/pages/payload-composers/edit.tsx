import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { update } from '@/actions/App/Http/Controllers/PayloadComposerController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { edit, index, show } from '@/routes/payload-composers';
import {
    PayloadComposerForm,
    type SavedQueryOption,
    type ScalarField,
    type SlotField,
} from './form';

type Composer = {
    id: number;
    name: string;
    description: string | null;
    scalars: Array<{
        key: string;
        required: boolean;
        default: string | null;
    }>;
    slots: Array<{
        key: string;
        saved_sql_query_id: number;
        shape: string;
        sort_order: number;
    }>;
};

type Props = {
    composer: Composer;
    savedQueries: SavedQueryOption[];
    slotShapes: string[];
};

export default function PayloadComposersEdit({
    composer,
    savedQueries,
    slotShapes,
}: Props) {
    const { data, setData, put, processing, errors, transform } = useForm({
        name: composer.name,
        description: composer.description ?? '',
        scalars: composer.scalars.map(
            (scalar): ScalarField => ({
                key: scalar.key,
                required: scalar.required,
                default: scalar.default ?? '',
            }),
        ),
        slots: composer.slots.map(
            (slot): SlotField => ({
                key: slot.key,
                saved_sql_query_id: String(slot.saved_sql_query_id),
                shape: slot.shape,
                sort_order: slot.sort_order,
            }),
        ),
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
        put(update.url(composer.id));
    }

    return (
        <>
            <Head title={`Edit ${composer.name}`} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={`Edit ${composer.name}`}
                        description="Update scalars and query slots."
                    />
                    <Button size="sm" variant="outline" asChild>
                        <Link href={show.url(composer.id)}>Cancel</Link>
                    </Button>
                </div>

                <PayloadComposerForm
                    name={data.name}
                    description={data.description}
                    scalars={data.scalars}
                    slots={data.slots}
                    savedQueries={savedQueries}
                    slotShapes={slotShapes}
                    errors={errors}
                    processing={processing}
                    submitLabel="Save changes"
                    onNameChange={(value) => setData('name', value)}
                    onDescriptionChange={(value) =>
                        setData('description', value)
                    }
                    onScalarsChange={(scalars) => setData('scalars', scalars)}
                    onSlotsChange={(slots) => setData('slots', slots)}
                    onSubmit={submit}
                />
            </div>
        </>
    );
}

PayloadComposersEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Payload composers', href: index.url() },
        { title: 'Edit', href: edit.url(0) },
    ],
};
