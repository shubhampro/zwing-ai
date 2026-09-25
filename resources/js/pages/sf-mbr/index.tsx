import { Head, Link } from '@inertiajs/react';
import { BarChart2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index, show } from '@/routes/sf-mbr';
import { formatNumber, formatPct } from './format';

type VolumeRow = {
    key: string;
    label: string;
    brought_forward: number;
    new_tickets: number;
    pool: number;
    resolved: number;
    carry_forward: number;
    resolved_pct: number;
};

export default function SalesforceMbrIndex({
    title,
    months,
}: {
    title: string;
    product: string;
    months: VolumeRow[];
}) {
    return (
        <>
            <Head title={title} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <Heading
                    title={title}
                    description="CST month pack. Pick a month. Local Salesforce dump · Product Zwing · spam excluded."
                />

                <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full min-w-[40rem] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Month</th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Brought forward
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    New
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Pool
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Resolved
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Resolved %
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Carry-forward
                                </th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {months.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No local Zwing cases. Run{' '}
                                        <code>php artisan sf:pull-cases</code>.
                                    </td>
                                </tr>
                            ) : (
                                months.map((row) => (
                                    <tr
                                        key={row.key}
                                        className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                    >
                                        <td className="px-3 py-2 font-medium">
                                            {row.label}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {formatNumber(row.brought_forward)}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {formatNumber(row.new_tickets)}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {formatNumber(row.pool)}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {formatNumber(row.resolved)}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {formatPct(row.resolved_pct)}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {formatNumber(row.carry_forward)}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link href={show.url(row.key)}>
                                                    <BarChart2 className="size-4" />
                                                    View
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

SalesforceMbrIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Zwing MBR', href: index() },
    ],
};
