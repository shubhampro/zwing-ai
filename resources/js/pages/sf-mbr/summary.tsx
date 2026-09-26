import { Head, Link, router, setLayoutProps, useForm, usePoll } from '@inertiajs/react';
import { ListFilter, LoaderCircle, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/SfMbrReportController';
import Heading from '@/components/heading';
import { useCan } from '@/hooks/use-can';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { formatDateOnly } from '@/lib/datetime';
import { cn } from '@/lib/utils';
import OverallSummaryWidget, {
    type OverallSummary,
} from '@/pages/sf-mbr/overall-summary-widget';
import SlaPriorityProductWidget, {
    type SlaByPriorityProduct,
} from '@/pages/sf-mbr/sla-priority-product-widget';
import { dashboard } from '@/routes';
import { index, show, summary as summaryRoute } from '@/routes/sf-mbr';

type Option = {
    id: number;
    name: string;
};

type ReportStatus = 'pending' | 'generating' | 'ready' | 'failed';

type Report = {
    id: number;
    title: string;
    starts_on: string | null;
    ends_on: string | null;
    status: ReportStatus;
    applications: Option[];
    all_applications: boolean;
};

type Filters = {
    application_ids: number[];
    module_ids: number[];
    include_no_module: boolean;
    all_applications: boolean;
    all_modules: boolean;
};

function scopeLabel(report: Report): string {
    const apps = report.all_applications
        ? 'All applications'
        : report.applications.map((application) => application.name).join(', ');

    return `${apps} · ${formatDateOnly(report.starts_on)} – ${formatDateOnly(report.ends_on)}`;
}

function toggleId(ids: number[], id: number): number[] {
    return ids.includes(id) ? ids.filter((value) => value !== id) : [...ids, id];
}

function FilterTile({
    checked,
    label,
    hint,
    onToggle,
}: {
    checked: boolean;
    label: string;
    hint?: string;
    onToggle: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onToggle}
            className={cn(
                'flex items-start gap-3 rounded-lg border px-3 py-3 text-left transition-colors',
                checked
                    ? 'border-primary bg-primary/5'
                    : 'hover:bg-muted/50',
            )}
        >
            <Checkbox
                checked={checked}
                tabIndex={-1}
                className="pointer-events-none mt-0.5"
            />
            <span>
                <span className="block text-sm font-medium">{label}</span>
                {hint ? (
                    <span className="mt-0.5 block text-xs text-muted-foreground">
                        {hint}
                    </span>
                ) : null}
            </span>
        </button>
    );
}

export default function SfMbrSummary({
    report,
    summary,
    sla,
    filters,
    filter_options,
}: {
    report: Report;
    summary: OverallSummary | null;
    sla: SlaByPriorityProduct | null;
    filters: Filters;
    filter_options: {
        applications: Option[];
        modules: Option[];
    };
}) {
    const isActive =
        report.status === 'generating' || report.status === 'pending';

    usePoll(
        2000,
        { only: ['report', 'summary', 'sla', 'filters', 'filter_options'] },
        { autoStart: isActive },
    );

    const can = useCan();
    const canDelete = can('sf-mbr.delete');
    const { delete: deleteReport, processing } = useForm();
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [open, setOpen] = useState(false);
    const [applicationIds, setApplicationIds] = useState(
        filters.application_ids,
    );
    const [moduleIds, setModuleIds] = useState(filters.module_ids);
    const [includeNoModule, setIncludeNoModule] = useState(
        filters.include_no_module,
    );

    useEffect(() => {
        setApplicationIds(filters.application_ids);
        setModuleIds(filters.module_ids);
        setIncludeNoModule(filters.include_no_module);
    }, [
        filters.application_ids.join(','),
        filters.module_ids.join(','),
        filters.include_no_module,
    ]);

    const allApplicationsSelected =
        filter_options.applications.length > 0 &&
        applicationIds.length === filter_options.applications.length;
    const allModulesSelected =
        includeNoModule &&
        moduleIds.length === filter_options.modules.length;
    const isFiltered = !filters.all_applications || !filters.all_modules;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'MBR Report', href: index() },
            { title: report.title, href: show(report.id) },
            { title: 'Overall Summary', href: summaryRoute(report.id) },
        ],
    });

    function syncDraft() {
        setApplicationIds(filters.application_ids);
        setModuleIds(filters.module_ids);
        setIncludeNoModule(filters.include_no_module);
    }

    function openFilters() {
        syncDraft();
        setOpen(true);
    }

    function closeFilters(nextOpen: boolean) {
        if (!nextOpen) {
            syncDraft();
        }

        setOpen(nextOpen);
    }

    function apply() {
        const allApps =
            applicationIds.length === filter_options.applications.length;
        const allModules =
            includeNoModule &&
            moduleIds.length === filter_options.modules.length;

        router.get(
            summaryRoute.url(report.id),
            allApps && allModules
                ? {}
                : {
                      application_ids: allApps ? undefined : applicationIds,
                      module_ids: allModules ? undefined : moduleIds,
                      include_no_module: allModules
                          ? undefined
                          : includeNoModule,
                  },
            { preserveState: true, preserveScroll: true },
        );
        setOpen(false);
    }

    function confirmDelete() {
        deleteReport(destroy.url(report.id), {
            onSuccess: () => setConfirmOpen(false),
        });
    }

    function resetAll() {
        setApplicationIds(
            filter_options.applications.map((application) => application.id),
        );
        setModuleIds(filter_options.modules.map((module) => module.id));
        setIncludeNoModule(true);
        router.get(
            summaryRoute.url(report.id),
            {},
            { preserveState: true, preserveScroll: true },
        );
        setOpen(false);
    }

    return (
        <>
            <Head title={`Overall Summary · ${report.title}`} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Overall Summary Report"
                        description={scopeLabel(report)}
                        className="mb-0"
                    />
                    <div className="flex shrink-0 items-center gap-2">
                        {report.status === 'ready' ? (
                            <Button
                                size="sm"
                                variant={isFiltered ? 'default' : 'outline'}
                                onClick={openFilters}
                            >
                                <ListFilter className="size-4" />
                                Filters
                                {isFiltered ? (
                                    <Badge
                                        variant="secondary"
                                        className="ml-1 bg-background text-foreground"
                                    >
                                        Active
                                    </Badge>
                                ) : null}
                            </Button>
                        ) : null}
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
                        <Button size="sm" variant="outline" asChild>
                            <Link href={show.url(report.id)}>Back</Link>
                        </Button>
                    </div>
                </div>

                {isFiltered ? (
                    <p className="text-sm text-muted-foreground">
                        Showing {filters.application_ids.length} application
                        {filters.application_ids.length === 1 ? '' : 's'}
                        {' · '}
                        {filters.module_ids.length +
                            (filters.include_no_module ? 1 : 0)}{' '}
                        module
                        {filters.module_ids.length +
                            (filters.include_no_module ? 1 : 0) ===
                        1
                            ? ''
                            : 's'}
                        .
                    </p>
                ) : null}

                {summary ? (
                    <OverallSummaryWidget summary={summary} />
                ) : (
                    <div className="flex items-center gap-3 rounded-lg border px-3 py-4 text-sm text-muted-foreground">
                        {report.status === 'failed' ? (
                            <p>Summary unavailable. Report generation failed.</p>
                        ) : (
                            <>
                                <LoaderCircle className="size-4 animate-spin text-amber-500" />
                                <p>
                                    Preparing overall summary from segregated
                                    accounts.
                                </p>
                            </>
                        )}
                    </div>
                )}

                {sla ? (
                    <SlaPriorityProductWidget sla={sla} />
                ) : summary ? (
                    <div className="flex items-center gap-3 rounded-lg border px-3 py-4 text-sm text-muted-foreground">
                        <p>SLA breakdown unavailable for this report.</p>
                    </div>
                ) : null}
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

            <Dialog open={open} onOpenChange={closeFilters}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>Scope filters</DialogTitle>
                        <DialogDescription>
                            All applications and modules start selected. Uncheck
                            Zwing noise such as SSO or Gift Voucher to correct
                            the numbers.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-8 lg:grid-cols-2">
                        <div className="space-y-3">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <Label className="text-sm">
                                        Applications
                                    </Label>
                                    <p className="text-sm text-muted-foreground">
                                        {applicationIds.length} of{' '}
                                        {filter_options.applications.length}{' '}
                                        selected
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        setApplicationIds(
                                            allApplicationsSelected
                                                ? []
                                                : filter_options.applications.map(
                                                      (application) =>
                                                          application.id,
                                                  ),
                                        )
                                    }
                                >
                                    {allApplicationsSelected
                                        ? 'Clear'
                                        : 'Select all'}
                                </Button>
                            </div>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {filter_options.applications.map(
                                    (application) => (
                                        <FilterTile
                                            key={application.id}
                                            checked={applicationIds.includes(
                                                application.id,
                                            )}
                                            label={application.name}
                                            onToggle={() =>
                                                setApplicationIds((ids) =>
                                                    toggleId(
                                                        ids,
                                                        application.id,
                                                    ),
                                                )
                                            }
                                        />
                                    ),
                                )}
                            </div>
                        </div>
                        <div className="space-y-3">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <Label className="text-sm">Modules</Label>
                                    <p className="text-sm text-muted-foreground">
                                        {moduleIds.length +
                                            (includeNoModule ? 1 : 0)}{' '}
                                        selected
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        if (allModulesSelected) {
                                            setModuleIds([]);
                                            setIncludeNoModule(false);
                                            return;
                                        }

                                        setModuleIds(
                                            filter_options.modules.map(
                                                (module) => module.id,
                                            ),
                                        );
                                        setIncludeNoModule(true);
                                    }}
                                >
                                    {allModulesSelected
                                        ? 'Clear'
                                        : 'Select all'}
                                </Button>
                            </div>
                            <div className="grid gap-2 sm:grid-cols-2">
                                <FilterTile
                                    checked={includeNoModule}
                                    label="No module"
                                    hint="Tickets with a blank module"
                                    onToggle={() =>
                                        setIncludeNoModule((value) => !value)
                                    }
                                />
                                {filter_options.modules.map((module) => (
                                    <FilterTile
                                        key={module.id}
                                        checked={moduleIds.includes(module.id)}
                                        label={module.name}
                                        onToggle={() =>
                                            setModuleIds((ids) =>
                                                toggleId(ids, module.id),
                                            )
                                        }
                                    />
                                ))}
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={
                                filters.all_applications && filters.all_modules
                            }
                            onClick={resetAll}
                        >
                            Reset to all
                        </Button>
                        <Button type="button" onClick={apply}>
                            Apply filters
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
