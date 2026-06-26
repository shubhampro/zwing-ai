import { Head } from '@inertiajs/react';
import {
    ArrowDownToLine,
    BrainCircuit,
    MessageSquare,
    RefreshCcw,
    Sparkles,
} from 'lucide-react';
import {
    DashboardModuleCard,
    type DashboardModule,
} from '@/components/dashboard-module-card';
import {
    ReconciliationSummaryPanel,
    StatHighlight,
    type ReconciliationSummary,
} from '@/components/reconciliation-summary-panel';
import Heading from '@/components/heading';
import { dashboard } from '@/routes';
import {
    create as invoiceCreate,
    index as invoiceIndex,
    report as invoiceReport,
} from '@/routes/invoice-reconciliation';
import {
    create as stockCreate,
    index as stockIndex,
    report as stockReport,
} from '@/routes/stock-transaction-reconciliation';

type ModuleHub = {
    assistant: DashboardModule;
    inbound_sync: DashboardModule;
    outbound_unsync: DashboardModule;
    model_training: DashboardModule;
};

type DashboardProps = {
    stockSummary: ReconciliationSummary | null;
    invoiceSummary: ReconciliationSummary | null;
    moduleHub: ModuleHub;
};

export default function Dashboard({
    stockSummary,
    invoiceSummary,
    moduleHub,
}: DashboardProps) {
    const stockNotInErp = stockSummary?.zwing_only_percent ?? 0;
    const invoiceNotInErp = invoiceSummary?.zwing_only_percent ?? 0;
    const avgMatched =
        stockSummary && invoiceSummary
            ? Math.round(
                  (stockSummary.matched_percent +
                      invoiceSummary.matched_percent) /
                      2,
              )
            : (stockSummary?.matched_percent ??
              invoiceSummary?.matched_percent ??
              null);

    const readyModules = [
        moduleHub.assistant,
        moduleHub.inbound_sync,
        moduleHub.outbound_unsync,
        moduleHub.model_training,
    ].filter((module) => module.ready).length;

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <Heading
                        title="Dashboard"
                        description="Sync diagnostics, AI assistance, and reconciliation health in one place."
                    />
                    <div className="flex items-center gap-2 rounded-xl border border-sidebar-border/70 bg-card px-4 py-3 text-sm dark:border-sidebar-border">
                        <Sparkles className="size-4 text-violet-500" />
                        <span className="text-muted-foreground">
                            <span className="font-semibold text-foreground">
                                {readyModules}/4
                            </span>{' '}
                            sync & AI tools ready
                        </span>
                    </div>
                </div>

                <section className="space-y-4">
                    <h2 className="text-sm font-semibold tracking-wide text-foreground uppercase">
                        Sync & AI tools
                    </h2>

                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <DashboardModuleCard
                            module={moduleHub.assistant}
                            icon={MessageSquare}
                            accent="violet"
                        />
                        <DashboardModuleCard
                            module={moduleHub.model_training}
                            icon={BrainCircuit}
                            accent="fuchsia"
                        />
                        <DashboardModuleCard
                            module={moduleHub.inbound_sync}
                            icon={ArrowDownToLine}
                            accent="emerald"
                        />
                        <DashboardModuleCard
                            module={moduleHub.outbound_unsync}
                            icon={RefreshCcw}
                            accent="sky"
                        />
                    </div>
                </section>

                <section className="space-y-4">
                    <div>
                        <h2 className="text-sm font-semibold tracking-wide text-foreground uppercase">
                            Reconciliation overview
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Latest completed stock and invoice comparison
                            sessions.
                        </p>
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <StatHighlight
                            label="Stock matched"
                            percent={stockSummary?.matched_percent ?? 0}
                            sublabel={
                                stockSummary
                                    ? `${stockSummary.matched.toLocaleString()} of ${stockSummary.total.toLocaleString()} rows`
                                    : 'Complete a stock session to see stats'
                            }
                            tone="green"
                        />
                        <StatHighlight
                            label="Invoice matched"
                            percent={invoiceSummary?.matched_percent ?? 0}
                            sublabel={
                                invoiceSummary
                                    ? `${invoiceSummary.matched.toLocaleString()} of ${invoiceSummary.total.toLocaleString()} invoices`
                                    : 'Complete an invoice session to see stats'
                            }
                            tone="blue"
                        />
                        <StatHighlight
                            label="Not in ERP (Zwing only)"
                            percent={Math.max(stockNotInErp, invoiceNotInErp)}
                            sublabel={
                                stockSummary || invoiceSummary
                                    ? `Stock ${stockNotInErp}% · Invoice ${invoiceNotInErp}%`
                                    : 'Missing-from-ERP share from latest runs'
                            }
                            tone="amber"
                        />
                    </div>

                    {avgMatched !== null && (
                        <p className="text-center text-sm text-muted-foreground">
                            Average match rate across latest completed
                            sessions:{' '}
                            <span className="font-semibold text-foreground">
                                {avgMatched}%
                            </span>
                        </p>
                    )}

                    <div className="grid flex-1 gap-4 lg:grid-cols-2">
                        <ReconciliationSummaryPanel
                            title="Stock reconciliation"
                            description="Latest completed stock comparison between Zwing and ERP."
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
                            description="Latest completed invoice comparison by invoice ID."
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
                    </div>
                </section>
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
