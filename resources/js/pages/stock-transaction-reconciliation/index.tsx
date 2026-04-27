import { Head } from '@inertiajs/react';
import { dashboard } from '@/routes';
import { index as stockTransactionReconciliationIndex } from '@/routes/stock-transaction-reconciliation';

export default function StockTransactionReconciliationIndex() {
    return (
        <>
            <Head title="Stock–transaction reconciliation" />
            <div className="flex h-full flex-1 flex-col p-4" />
        </>
    );
}

StockTransactionReconciliationIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Stock–transaction reconciliation',
            href: stockTransactionReconciliationIndex.url(),
        },
    ],
};
