import { Head, Link, setLayoutProps, useForm, usePoll } from '@inertiajs/react';
import { CheckCircle2, CircleDashed, LoaderCircle, Trash2, XCircle } from 'lucide-react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/SfMbrReportController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCan } from '@/hooks/use-can';
import { formatDateOnly } from '@/lib/datetime';
import { dashboard } from '@/routes';
import { details, index, show, summary } from '@/routes/sf-mbr';

type Application = {
    id: number;
    name: string;
};

type ReportStatus = 'pending' | 'generating' | 'ready' | 'failed';
type SectionStatus = 'pending' | 'running' | 'done' | 'failed';

type Report = {
    id: number;
    title: string;
    starts_on: string | null;
    ends_on: string | null;
    status: ReportStatus;
    failed_reason: string | null;
    applications: Application[];
    all_applications: boolean;
};

type Section = {
    key: string;
    label: string;
    status: SectionStatus;
};

function scopeLabel(report: Report): string {
    const apps = report.all_applications
        ? 'All applications'
        : report.applications.map((application) => application.name).join(', ');

    return `${apps} · ${formatDateOnly(report.starts_on)} – ${formatDateOnly(report.ends_on)}`;
}

function sectionIcon(status: SectionStatus) {
    if (status === 'running') {
        return (
            <LoaderCircle className="size-4 animate-spin text-amber-500" />
        );
    }

    if (status === 'done') {
        return <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" />;
    }

    if (status === 'failed') {
        return <XCircle className="size-4 text-destructive" />;
    }

    return <CircleDashed className="size-4 text-muted-foreground" />;
}

function sectionCopy(section: Section): string {
    if (section.status === 'running') {
        return 'Preparing';
    }

    if (section.status === 'failed') {
        return 'Failed';
    }

    if (section.status === 'pending') {
        return 'Waiting for earlier sections';
    }

    return 'Ready';
}

function ReportLink({
    href,
    children,
    enabled,
}: {
    href: string;
    children: string;
    enabled: boolean;
}) {
    if (!enabled) {
        return (
            <Button size="sm" disabled>
                {children}
            </Button>
        );
    }

    return (
        <Button size="sm" asChild>
            <Link href={href}>{children}</Link>
        </Button>
    );
}

export default function SfMbrShow({
    report,
    generation,
}: {
    report: Report;
    generation: {
        status: ReportStatus;
        failed_reason: string | null;
        sections: Section[];
    };
}) {
    const isActive =
        report.status === 'generating' || report.status === 'pending';
    const isReady = report.status === 'ready';
    const canDelete = useCan()('sf-mbr.delete');
    const [confirmOpen, setConfirmOpen] = useState(false);
    const { delete: deleteReport, processing } = useForm();

    usePoll(2000, { only: ['report', 'generation'] }, { autoStart: isActive });

    function confirmDelete() {
        deleteReport(destroy.url(report.id), {
            onSuccess: () => setConfirmOpen(false),
        });
    }

    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'MBR Report', href: index() },
            { title: report.title, href: show(report.id) },
        ],
    });

    return (
        <>
            <Head title={`Generate Report · ${report.title}`} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Generate Report"
                        description={scopeLabel(report)}
                        className="mb-0"
                    />
                    <div className="flex shrink-0 items-center gap-2">
                        <Button size="sm" variant="outline" asChild>
                            <Link href={index.url()}>Back</Link>
                        </Button>
                        {canDelete ? (
                            <Button
                                size="sm"
                                variant="outline"
                                className="text-destructive hover:text-destructive"
                                onClick={() => setConfirmOpen(true)}
                            >
                                <Trash2 className="size-4" />
                                Delete
                            </Button>
                        ) : null}
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <CardTitle>{report.title}</CardTitle>
                                <CardDescription>
                                    Each section runs on the queue. Later
                                    sections wait for earlier ones.
                                </CardDescription>
                            </div>
                            <Badge
                                variant={
                                    report.status === 'failed'
                                        ? 'destructive'
                                        : report.status === 'ready'
                                          ? 'default'
                                          : 'secondary'
                                }
                            >
                                {report.status === 'generating'
                                    ? 'Preparing'
                                    : report.status}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {generation.sections.map((section) => (
                            <div
                                key={section.key}
                                className="flex items-start gap-3 rounded-lg border px-3 py-3"
                            >
                                <div className="mt-0.5">{sectionIcon(section.status)}</div>
                                <div className="min-w-0">
                                    <p className="text-sm font-medium">
                                        {section.label}
                                    </p>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        {sectionCopy(section)}
                                    </p>
                                </div>
                            </div>
                        ))}

                        {report.status === 'failed' && report.failed_reason ? (
                            <p className="text-sm text-destructive">
                                {report.failed_reason}
                            </p>
                        ) : null}

                        <div className="flex flex-wrap gap-2">
                            <ReportLink
                                href={summary.url(report.id)}
                                enabled={isReady}
                            >
                                Overall Summary
                            </ReportLink>
                            <ReportLink
                                href={details.url(report.id)}
                                enabled={isReady}
                            >
                                Details Report
                            </ReportLink>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete MBR report?</DialogTitle>
                        <DialogDescription>
                            This will permanently delete{' '}
                            <span className="font-medium text-foreground">
                                "{report.title}"
                            </span>{' '}
                            and its segregated account rows. This action cannot
                            be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmOpen(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={confirmDelete}
                            disabled={processing}
                        >
                            {processing ? 'Deleting…' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
