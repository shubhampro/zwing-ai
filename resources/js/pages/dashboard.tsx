import { Head } from '@inertiajs/react';
import { ReconciliationSummaryPanel, StatHighlight, type ReconciliationSummary } from '@/components/reconciliation-summary-panel';
import Heading from '@/components/heading';
import { dashboard } from '@/routes';
import { create as invoiceCreate, index as invoiceIndex, report as invoiceReport } from '@/routes/invoice-reconciliation';
import { create as stockCreate, index as stockIndex, report as stockReport } from '@/routes/stock-transaction-reconciliation';

type DashboardProps = {
    stockSummary: ReconciliationSummary | null;
    invoiceSummary: ReconciliationSummary | null;
};

export default function Dashboard({ stockSummary, invoiceSummary }: DashboardProps) {
    const stockNotInErp = stockSummary?.zwing_only_percent ?? 0;
    const invoiceNotInErp = invoiceSummary?.zwing_only_percent ?? 0;
    const avgMatched =
        stockSummary && invoiceSummary
            ? Math.round((stockSummary.matched_percent + invoiceSummary.matched_percent) / 2)
            : (stockSummary?.matched_percent ?? invoiceSummary?.matched_percent ?? null);

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Dashboard"
                    description="Overview of your latest stock and invoice reconciliation results."
                />

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
                    <p className="text-muted-foreground text-center text-sm">
                        Average match rate across latest completed sessions:{' '}
                        <span className="text-foreground font-semibold">{avgMatched}%</span>
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
                        mismatchLabels={{ primary: 'Qty mismatch', secondary: 'Other mismatch' }}
                    />
                    <ReconciliationSummaryPanel
                        title="Invoice reconciliation"
                        description="Latest completed invoice comparison by invoice ID."
                        icon="invoice"
                        summary={invoiceSummary}
                        emptyHref={invoiceCreate.url()}
                        reportHref={(id) => invoiceReport.url(id)}
                        listHref={invoiceIndex.url()}
                        mismatchLabels={{ primary: 'Amount mismatch', secondary: 'Status mismatch' }}
                    />
                </div>
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
