import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeftRight,
    ArrowUpRight,
    Building2,
    ClipboardCheck,
    CloudUpload,
    FileText,
    PlayCircle,
    Plug,
    Wallet,
    type LucideIcon,
} from 'lucide-react';
import {
    ReconciliationSummaryPanel,
    StatHighlight,
    type ReconciliationSummary,
} from '@/components/reconciliation-summary-panel';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
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
import { dashboard } from '@/routes';
import {
    create as expenseCreate,
    index as expenseIndex,
    report as expenseReport,
} from '@/routes/expense-cash-reconciliation';
import { index as inboundEventsRunnerIndex } from '@/routes/inbound-events-runner';
import {
    create as invoiceCreate,
    index as invoiceIndex,
    report as invoiceReport,
} from '@/routes/invoice-reconciliation';
import { index as outboundSyncIndex } from '@/routes/outbound-sync';
import { index as organizationsIndex } from '@/routes/organizations';
import {
    create as stockCreate,
    index as stockIndex,
    report as stockReport,
} from '@/routes/stock-transaction-reconciliation';
import { index as thirdPartyApiBatchesIndex } from '@/routes/third-party-api-batches';
import { index as thirdPartyApisIndex } from '@/routes/third-party-apis';
import { index as transactionCheckerIndex } from '@/routes/transaction-checker';

type PlatformStats = {
    organizations_count: number;
    third_party_apis_count: number;
    completed_batches_count: number;
    transaction_checker_runs_count: number;
};

type LatestBatchSummary = {
    id: number;
    name: string;
    row_count: number;
    success_count: number;
    failed_count: number;
    skipped_count: number;
    success_percent: number;
    completed_at: string | null;
};

type DashboardProps = {
    stockSummary: ReconciliationSummary | null;
    invoiceSummary: ReconciliationSummary | null;
    expenseSummary: ReconciliationSummary | null;
    platform: PlatformStats;
    latestBatch: LatestBatchSummary | null;
};

type ToolLink = {
    title: string;
    description: string;
    href: string;
    icon: LucideIcon;
    accent: string;
};

type ToolGroup = {
    title: string;
    description: string;
    tools: ToolLink[];
};

const toolGroups: ToolGroup[] = [
    {
        title: 'Setup & configuration',
        description: 'Organizations, API templates, and batch definitions.',
        tools: [
            {
                title: 'Organizations',
                description: 'Manage vendors and org connections.',
                href: organizationsIndex.url(),
                icon: Building2,
                accent: 'bg-slate-500/10 text-slate-700 dark:text-slate-300',
            },
            {
                title: 'Third party APIs',
                description: 'Configure reusable API templates.',
                href: thirdPartyApisIndex.url(),
                icon: Plug,
                accent: 'bg-violet-500/10 text-violet-700 dark:text-violet-300',
            },
            {
                title: 'API batches',
                description: 'Upload CSVs and run bulk API calls.',
                href: thirdPartyApiBatchesIndex.url(),
                icon: FileText,
                accent: 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300',
            },
        ],
    },
    {
        title: 'Reconciliation',
        description: 'Compare Zwing data against ERP exports.',
        tools: [
            {
                title: 'Stock reconciliation',
                description: 'Batch, barcode, and qty comparison.',
                href: stockIndex.url(),
                icon: ArrowLeftRight,
                accent: 'bg-green-500/10 text-green-700 dark:text-green-300',
            },
            {
                title: 'Invoice reconciliation',
                description: 'Match invoices by ID and amount.',
                href: invoiceIndex.url(),
                icon: FileText,
                accent: 'bg-blue-500/10 text-blue-700 dark:text-blue-300',
            },
            {
                title: 'Expense & cash reconciliation',
                description: 'Compare expense and cash transactions.',
                href: expenseIndex.url(),
                icon: Wallet,
                accent: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
            },
        ],
    },
    {
        title: 'Connect & validation',
        description: 'Inspect transactions and sync health with Connect.',
        tools: [
            {
                title: 'Transaction Checker',
                description: 'Validate GRN, GRT, and SST in live DBs.',
                href: transactionCheckerIndex.url(),
                icon: ClipboardCheck,
                accent: 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-300',
            },
            {
                title: 'Inbound Events Runner',
                description: 'Retry inbound event logs from CSV.',
                href: inboundEventsRunnerIndex.url(),
                icon: PlayCircle,
                accent: 'bg-orange-500/10 text-orange-700 dark:text-orange-300',
            },
            {
                title: 'Outbound Sync',
                description: 'Check unsynced outbound Connect data.',
                href: outboundSyncIndex.url(),
                icon: CloudUpload,
                accent: 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
            },
        ],
    },
];

function MetricCard({
    label,
    value,
    sublabel,
    tone,
}: {
    label: string;
    value: string | number;
    sublabel: string;
    tone: 'slate' | 'violet' | 'cyan';
}) {
    const toneClasses = {
        slate: 'from-slate-500/15 to-slate-500/5 text-slate-700 dark:text-slate-300',
        violet: 'from-violet-500/15 to-violet-500/5 text-violet-700 dark:text-violet-300',
        cyan: 'from-cyan-500/15 to-cyan-500/5 text-cyan-700 dark:text-cyan-300',
    };

    return (
        <Card className={cn('bg-gradient-to-br py-5', toneClasses[tone])}>
            <CardContent className="px-6">
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </p>
                <p className="mt-1 text-4xl font-bold tracking-tight tabular-nums">
                    {typeof value === 'number'
                        ? value.toLocaleString()
                        : value}
                </p>
                <p className="mt-1 text-sm text-muted-foreground">{sublabel}</p>
            </CardContent>
        </Card>
    );
}

function ToolQuickLink({ tool }: { tool: ToolLink }) {
    const Icon = tool.icon;

    return (
        <Link
            href={tool.href}
            prefetch
            className="group flex items-start gap-3 rounded-lg border border-sidebar-border/70 p-4 transition-colors hover:border-sidebar-border hover:bg-muted/40 dark:border-sidebar-border"
        >
            <div
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-lg',
                    tool.accent,
                )}
            >
                <Icon className="size-5" />
            </div>
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    <p className="font-medium">{tool.title}</p>
                    <ArrowUpRight className="size-3.5 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                </div>
                <p className="mt-0.5 text-sm text-muted-foreground">
                    {tool.description}
                </p>
            </div>
        </Link>
    );
}

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

export default function Dashboard({
    stockSummary,
    invoiceSummary,
    expenseSummary,
    platform,
    latestBatch,
}: DashboardProps) {
    const reconciliationSummaries = [
        stockSummary,
        invoiceSummary,
        expenseSummary,
    ].filter((summary): summary is ReconciliationSummary => summary !== null);

    const avgMatched =
        reconciliationSummaries.length > 0
            ? Math.round(
                  reconciliationSummaries.reduce(
                      (total, summary) => total + summary.matched_percent,
                      0,
                  ) / reconciliationSummaries.length,
              )
            : null;

    const completedReconciliations = reconciliationSummaries.length;

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Dashboard"
                    description="Your hub for reconciliation, API batches, and Connect sync tools."
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                    <StatHighlight
                        label="Stock matched"
                        percent={stockSummary?.matched_percent ?? 0}
                        sublabel={
                            stockSummary
                                ? `${stockSummary.matched.toLocaleString()} of ${stockSummary.total.toLocaleString()} rows`
                                : 'No completed stock session yet'
                        }
                        tone="green"
                    />
                    <StatHighlight
                        label="Invoice matched"
                        percent={invoiceSummary?.matched_percent ?? 0}
                        sublabel={
                            invoiceSummary
                                ? `${invoiceSummary.matched.toLocaleString()} of ${invoiceSummary.total.toLocaleString()} invoices`
                                : 'No completed invoice session yet'
                        }
                        tone="blue"
                    />
                    <StatHighlight
                        label="Expense matched"
                        percent={expenseSummary?.matched_percent ?? 0}
                        sublabel={
                            expenseSummary
                                ? `${expenseSummary.matched.toLocaleString()} of ${expenseSummary.total.toLocaleString()} rows`
                                : 'No completed expense session yet'
                        }
                        tone="amber"
                    />
                    <MetricCard
                        label="Organizations"
                        value={platform.organizations_count}
                        sublabel={`${platform.third_party_apis_count} API templates configured`}
                        tone="slate"
                    />
                    <MetricCard
                        label="API batches done"
                        value={platform.completed_batches_count}
                        sublabel={
                            latestBatch
                                ? `Latest: ${latestBatch.success_percent}% success`
                                : 'Run a batch to see results here'
                        }
                        tone="violet"
                    />
                    <MetricCard
                        label="Transaction checks"
                        value={platform.transaction_checker_runs_count}
                        sublabel="Saved runs from Transaction Checker"
                        tone="cyan"
                    />
                </div>

                {avgMatched !== null && (
                    <div className="flex flex-wrap items-center justify-center gap-2 text-sm text-muted-foreground">
                        <span>
                            Average match rate across{' '}
                            {completedReconciliations} completed reconciliation
                            {completedReconciliations === 1 ? '' : 's'}:
                        </span>
                        <Badge variant="secondary" className="tabular-nums">
                            {avgMatched}%
                        </Badge>
                    </div>
                )}

                <section className="flex flex-col gap-4">
                    <div>
                        <h2 className="text-lg font-semibold tracking-tight">
                            Quick access
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Jump to any tool from the sidebar, or open it here.
                        </p>
                    </div>

                    <div className="grid gap-4 xl:grid-cols-3">
                        {toolGroups.map((group) => (
                            <Card
                                key={group.title}
                                className="border-sidebar-border/70 dark:border-sidebar-border"
                            >
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-base">
                                        {group.title}
                                    </CardTitle>
                                    <CardDescription>
                                        {group.description}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-2">
                                    {group.tools.map((tool) => (
                                        <ToolQuickLink
                                            key={tool.title}
                                            tool={tool}
                                        />
                                    ))}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </section>

                <section className="flex flex-col gap-4">
                    <div>
                        <h2 className="text-lg font-semibold tracking-tight">
                            Latest reconciliation results
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Most recent completed Zwing vs ERP comparison for
                            each module.
                        </p>
                    </div>

                    <div className="grid flex-1 gap-4 xl:grid-cols-3">
                        <ReconciliationSummaryPanel
                            title="Stock reconciliation"
                            description="Latest stock comparison between Zwing and ERP."
                            icon="stock"
                            summary={stockSummary}
                            emptyHref={stockCreate.url()}
                            reportHref={(id) => stockReport.url(id)}
                            listHref={stockIndex.url()}
                            mismatchLabels={{
                                primary: 'Qty mismatch',
                                secondary: 'Other mismatch',
                            }}
                        />
                        <ReconciliationSummaryPanel
                            title="Invoice reconciliation"
                            description="Latest invoice comparison by invoice ID."
                            icon="invoice"
                            summary={invoiceSummary}
                            emptyHref={invoiceCreate.url()}
                            reportHref={(id) => invoiceReport.url(id)}
                            listHref={invoiceIndex.url()}
                            mismatchLabels={{
                                primary: 'Amount mismatch',
                                secondary: 'Status mismatch',
                            }}
                        />
                        <ReconciliationSummaryPanel
                            title="Expense & cash reconciliation"
                            description="Latest expense and cash doc comparison."
                            icon="expense"
                            summary={expenseSummary}
                            emptyHref={expenseCreate.url()}
                            reportHref={(id) => expenseReport.url(id)}
                            listHref={expenseIndex.url()}
                            mismatchLabels={{
                                primary: 'Amount mismatch',
                                secondary: 'Date / status mismatch',
                            }}
                        />
                    </div>
                </section>

                {latestBatch && (
                    <Card className="border-sidebar-border/70 dark:border-sidebar-border">
                        <CardHeader className="flex flex-row items-start justify-between gap-4 space-y-0">
                            <div>
                                <CardTitle className="text-base">
                                    Latest API batch
                                </CardTitle>
                                <CardDescription className="mt-1">
                                    {latestBatch.name} ·{' '}
                                    {formatDate(latestBatch.completed_at)}
                                </CardDescription>
                            </div>
                            <Badge
                                className={cn(
                                    'tabular-nums',
                                    latestBatch.success_percent >= 90
                                        ? 'bg-green-600 hover:bg-green-600'
                                        : latestBatch.success_percent >= 70
                                          ? 'bg-amber-600 hover:bg-amber-600'
                                          : 'bg-destructive hover:bg-destructive',
                                )}
                            >
                                {latestBatch.success_percent}% success
                            </Badge>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-3 sm:grid-cols-4">
                                <div className="rounded-md border border-sidebar-border/70 px-3 py-2 dark:border-sidebar-border">
                                    <p className="text-xs text-muted-foreground">
                                        Total rows
                                    </p>
                                    <p className="text-lg font-semibold tabular-nums">
                                        {latestBatch.row_count.toLocaleString()}
                                    </p>
                                </div>
                                <div className="rounded-md border border-green-500/20 bg-green-500/[0.04] px-3 py-2">
                                    <p className="text-xs text-muted-foreground">
                                        Success
                                    </p>
                                    <p className="text-lg font-semibold tabular-nums text-green-700 dark:text-green-400">
                                        {latestBatch.success_count.toLocaleString()}
                                    </p>
                                </div>
                                <div className="rounded-md border border-destructive/20 bg-destructive/[0.04] px-3 py-2">
                                    <p className="text-xs text-muted-foreground">
                                        Failed
                                    </p>
                                    <p className="text-lg font-semibold tabular-nums text-destructive">
                                        {latestBatch.failed_count.toLocaleString()}
                                    </p>
                                </div>
                                <div className="rounded-md border border-sidebar-border/70 px-3 py-2 dark:border-sidebar-border">
                                    <p className="text-xs text-muted-foreground">
                                        Skipped
                                    </p>
                                    <p className="text-lg font-semibold tabular-nums">
                                        {latestBatch.skipped_count.toLocaleString()}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                        <CardFooter>
                            <Button size="sm" variant="outline" asChild>
                                <Link href={thirdPartyApiBatchesIndex.url()}>
                                    View all batches
                                </Link>
                            </Button>
                        </CardFooter>
                    </Card>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
