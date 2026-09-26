import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import { store } from '@/actions/App/Http/Controllers/SfMbrReportController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { Label } from '@/components/ui/label';
import { formatDateOnly } from '@/lib/datetime';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { create, index } from '@/routes/sf-mbr';

type ApplicationOption = {
    id: number;
    name: string;
};

function previewTitle(
    applications: ApplicationOption[],
    selectedIds: number[],
    startsOn: string,
    endsOn: string,
): string {
    const range = `${formatDateOnly(startsOn)} – ${formatDateOnly(endsOn)}`;
    const names = applications
        .filter((application) => selectedIds.includes(application.id))
        .map((application) => application.name);

    if (names.length === 0) {
        return `All applications · ${range}`;
    }

    return `${names.join(', ')} · ${range}`;
}

export default function SfMbrCreate({
    applications,
    defaults,
}: {
    applications: ApplicationOption[];
    defaults: {
        starts_on: string;
        ends_on: string;
    };
}) {
    const { data, setData, post, processing, errors } = useForm({
        starts_on: defaults.starts_on,
        ends_on: defaults.ends_on,
        application_ids: [] as number[],
    });

    const allSelected = data.application_ids.length === 0;

    function toggleApplication(id: number) {
        setData(
            'application_ids',
            data.application_ids.includes(id)
                ? data.application_ids.filter((value) => value !== id)
                : [...data.application_ids, id],
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        post(store.url());
    }

    return (
        <>
            <Head title="New MBR report" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="New MBR report"
                        description="Choose an IST date range and the applications this review covers."
                        className="mb-0"
                    />
                    <Button size="sm" variant="outline" asChild>
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                            Back
                        </Link>
                    </Button>
                </div>

                <form className="max-w-3xl" onSubmit={submit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Report scope</CardTitle>
                            <CardDescription>
                                Title is generated automatically from this
                                selection.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-8">
                            <div className="space-y-3">
                                <div>
                                    <Label className="text-sm">
                                        Date range
                                    </Label>
                                    <p className="text-sm text-muted-foreground">
                                        Inclusive days in IST.
                                    </p>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="starts_on">From</Label>
                                        <DatePicker
                                            id="starts_on"
                                            value={data.starts_on}
                                            max={data.ends_on}
                                            onChange={(value) =>
                                                setData('starts_on', value)
                                            }
                                        />
                                        <InputError
                                            message={errors.starts_on}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="ends_on">To</Label>
                                        <DatePicker
                                            id="ends_on"
                                            value={data.ends_on}
                                            min={data.starts_on}
                                            onChange={(value) =>
                                                setData('ends_on', value)
                                            }
                                        />
                                        <InputError message={errors.ends_on} />
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-3">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <Label className="text-sm">
                                            Applications
                                        </Label>
                                        <p className="text-sm text-muted-foreground">
                                            Leave all unselected to include
                                            every application.
                                        </p>
                                    </div>
                                    {!allSelected && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                setData('application_ids', [])
                                            }
                                        >
                                            Clear
                                        </Button>
                                    )}
                                </div>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setData('application_ids', [])
                                        }
                                        className={cn(
                                            'rounded-lg border px-3 py-3 text-left transition-colors',
                                            allSelected
                                                ? 'border-primary bg-primary/5'
                                                : 'hover:bg-muted/50',
                                        )}
                                    >
                                        <span className="block text-sm font-medium">
                                            All applications
                                        </span>
                                        <span className="mt-0.5 block text-xs text-muted-foreground">
                                            Default for a full review
                                        </span>
                                    </button>
                                    {applications.map((application) => {
                                        const selected =
                                            data.application_ids.includes(
                                                application.id,
                                            );

                                        return (
                                            <button
                                                key={application.id}
                                                type="button"
                                                onClick={() =>
                                                    toggleApplication(
                                                        application.id,
                                                    )
                                                }
                                                className={cn(
                                                    'rounded-lg border px-3 py-3 text-left text-sm font-medium transition-colors',
                                                    selected
                                                        ? 'border-primary bg-primary/5'
                                                        : 'hover:bg-muted/50',
                                                )}
                                            >
                                                {application.name}
                                            </button>
                                        );
                                    })}
                                </div>
                                <InputError message={errors.application_ids} />
                            </div>

                            <div className="rounded-lg bg-muted/50 px-4 py-3">
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Title
                                </p>
                                <p className="mt-1 text-sm font-medium">
                                    {previewTitle(
                                        applications,
                                        data.application_ids,
                                        data.starts_on,
                                        data.ends_on,
                                    )}
                                </p>
                            </div>
                        </CardContent>
                        <CardFooter className="justify-end">
                            <Button type="submit" disabled={processing}>
                                Create report
                            </Button>
                        </CardFooter>
                    </Card>
                </form>
            </div>
        </>
    );
}

SfMbrCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'MBR Report', href: index() },
        { title: 'New report', href: create() },
    ],
};
