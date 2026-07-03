import { Link } from '@inertiajs/react';
import { ArrowLeftRight, BarChart2, FileText, Wallet } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type ReconciliationSummary = {
    session: {
        id: number;
        name: string;
        v_id: number;
        reconciled_at: string | null;
    };
    total: number;
    matched: number;
    matched_percent: number;
    zwing_only: number;
    zwing_only_percent: number;
    erp_only: number;
    erp_only_percent: number;
    mismatch: number;
    mismatch_percent: number;
    primary_mismatch: number;
    primary_mismatch_percent: number;
    secondary_mismatch: number;
    secondary_mismatch_percent: number;
};

type Segment = {
    key: string;
    label: string;
    count: number;
    percent: number;
    barClass: string;
    dotClass: string;
};

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }
    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function MatchRing({ percent, label }: { percent: number; label: string }) {
    const clamped = Math.min(100, Math.max(0, percent));
    const ringColor =
        clamped >= 90
            ? 'text-green-500'
            : clamped >= 70
              ? 'text-amber-500'
              : 'text-destructive';

    return (
        <div
            className={cn(
                'relative flex size-28 shrink-0 items-center justify-center',
                ringColor,
            )}
        >
            <div
                className="absolute inset-0 rounded-full"
                style={{
                    background: `conic-gradient(currentColor ${clamped * 3.6}deg, var(--color-muted) 0deg)`,
                }}
                aria-hidden
            />
            <div className="absolute inset-[6px] flex flex-col items-center justify-center rounded-full bg-card">
                <span
                    className={cn('text-2xl font-bold tabular-nums', ringColor)}
                >
                    {clamped}%
                </span>
                <span className="text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </span>
            </div>
        </div>
    );
}

function SegmentBar({ segments }: { segments: Segment[] }) {
    const withData = segments.filter((s) => s.percent > 0);

    if (withData.length === 0) {
        return <div className="h-3 w-full rounded-full bg-muted" />;
    }

    return (
        <div className="flex h-3 w-full overflow-hidden rounded-full bg-muted">
            {withData.map((segment) => (
                <div
                    key={segment.key}
                    className={cn('h-full transition-all', segment.barClass)}
                    style={{ width: `${segment.percent}%` }}
                    title={`${segment.label}: ${segment.percent}%`}
                />
            ))}
        </div>
    );
}

function SegmentLegend({ segments }: { segments: Segment[] }) {
    return (
        <ul className="grid gap-2 sm:grid-cols-2">
            {segments.map((segment) => (
                <li
                    key={segment.key}
                    className="flex items-center justify-between gap-2 text-sm"
                >
                    <span className="flex items-center gap-2 text-muted-foreground">
                        <span
                            className={cn(
                                'size-2.5 shrink-0 rounded-full',
                                segment.dotClass,
                            )}
                        />
                        {segment.label}
                    </span>
                    <span className="font-medium tabular-nums">
                        {segment.count.toLocaleString()}
                        <span className="ms-1 font-normal text-muted-foreground">
                            ({segment.percent}%)
                        </span>
                    </span>
                </li>
            ))}
        </ul>
    );
}

type ReconciliationSummaryPanelProps = {
    title: string;
    description: string;
    icon: 'stock' | 'invoice' | 'expense';
    summary: ReconciliationSummary | null;
    emptyHref: string;
    reportHref: (sessionId: number) => string;
    listHref: string;
    mismatchLabels: { primary: string; secondary: string };
};

export function ReconciliationSummaryPanel({
    title,
    description,
    icon,
    summary,
    emptyHref,
    reportHref,
    listHref,
    mismatchLabels,
}: ReconciliationSummaryPanelProps) {
    const Icon =
        icon === 'stock'
            ? ArrowLeftRight
            : icon === 'invoice'
              ? FileText
              : Wallet;

    if (summary === null) {
        return (
            <Card className="h-full border-dashed">
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <div className="flex size-9 items-center justify-center rounded-lg bg-muted">
                            <Icon className="size-4 text-muted-foreground" />
                        </div>
                        <div>
                            <CardTitle>{title}</CardTitle>
                            <CardDescription>{description}</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        No completed reconciliation yet. Upload Zwing and ERP
                        CSVs to see match percentages here.
                    </p>
                </CardContent>
                <CardFooter>
                    <Button size="sm" asChild>
                        <Link href={emptyHref}>Start reconciliation</Link>
                    </Button>
                </CardFooter>
            </Card>
        );
    }

    const segments: Segment[] = [
        {
            key: 'matched',
            label: 'Matched / in both',
            count: summary.matched,
            percent: summary.matched_percent,
            barClass: 'bg-green-500',
            dotClass: 'bg-green-500',
        },
        {
            key: 'zwing_only',
            label: 'Zwing only (not in ERP)',
            count: summary.zwing_only,
            percent: summary.zwing_only_percent,
            barClass: 'bg-amber-500',
            dotClass: 'bg-amber-500',
        },
        {
            key: 'mismatch',
            label: mismatchLabels.primary,
            count: summary.primary_mismatch,
            percent: summary.primary_mismatch_percent,
            barClass: 'bg-red-500',
            dotClass: 'bg-red-500',
        },
        ...(summary.secondary_mismatch > 0
            ? [
                  {
                      key: 'secondary_mismatch',
                      label: mismatchLabels.secondary,
                      count: summary.secondary_mismatch,
                      percent: summary.secondary_mismatch_percent,
                      barClass: 'bg-orange-500',
                      dotClass: 'bg-orange-500',
                  } satisfies Segment,
              ]
            : []),
        {
            key: 'erp_only',
            label: 'ERP only',
            count: summary.erp_only,
            percent: summary.erp_only_percent,
            barClass: 'bg-blue-500',
            dotClass: 'bg-blue-500',
        },
    ];

    return (
        <Card className="h-full">
            <CardHeader className="flex flex-row items-start justify-between gap-4 space-y-0">
                <div className="flex items-start gap-3">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Icon className="size-5 text-primary" />
                    </div>
                    <div>
                        <CardTitle>{title}</CardTitle>
                        <CardDescription className="mt-1">
                            {description}
                        </CardDescription>
                        <p className="mt-2 text-xs text-muted-foreground">
                            Latest:{' '}
                            <span className="font-medium text-foreground">
                                {summary.session.name}
                            </span>
                            {' · '}
                            Vendor {summary.session.v_id}
                            {' · '}
                            {formatDate(summary.session.reconciled_at)}
                        </p>
                    </div>
                </div>
                <MatchRing percent={summary.matched_percent} label="matched" />
            </CardHeader>
            <CardContent className="flex flex-col gap-5">
                <div>
                    <div className="mb-2 flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Distribution
                        </span>
                        <span className="font-medium tabular-nums">
                            {summary.total.toLocaleString()} rows compared
                        </span>
                    </div>
                    <SegmentBar segments={segments} />
                </div>
                <SegmentLegend segments={segments} />
            </CardContent>
            <CardFooter className="gap-2">
                <Button size="sm" asChild>
                    <Link href={reportHref(summary.session.id)}>
                        <BarChart2 className="size-4" />
                        View report
                    </Link>
                </Button>
                <Button size="sm" variant="outline" asChild>
                    <Link href={listHref}>All sessions</Link>
                </Button>
            </CardFooter>
        </Card>
    );
}

type StatHighlightProps = {
    label: string;
    percent: number;
    sublabel: string;
    tone: 'green' | 'amber' | 'blue';
};

export function StatHighlight({
    label,
    percent,
    sublabel,
    tone,
}: StatHighlightProps) {
    const toneClasses = {
        green: 'from-green-500/15 to-green-500/5 text-green-700 dark:text-green-400',
        amber: 'from-amber-500/15 to-amber-500/5 text-amber-700 dark:text-amber-400',
        blue: 'from-blue-500/15 to-blue-500/5 text-blue-700 dark:text-blue-400',
    };

    return (
        <Card className={cn('bg-gradient-to-br py-5', toneClasses[tone])}>
            <CardContent className="px-6">
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </p>
                <p className="mt-1 text-4xl font-bold tracking-tight tabular-nums">
                    {percent}%
                </p>
                <p className="mt-1 text-sm text-muted-foreground">{sublabel}</p>
            </CardContent>
        </Card>
    );
}
