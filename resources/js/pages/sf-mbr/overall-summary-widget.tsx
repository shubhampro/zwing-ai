import { Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { Info, Pointer } from 'lucide-react';

function formatNumber(value: number): string {
    return new Intl.NumberFormat('en-IN').format(value);
}

function formatPercent(value: number | null): string {
    if (value === null) {
        return '—';
    }

    return `${new Intl.NumberFormat('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value)}%`;
}

export type OverallSummary = {
    title: string;
    period_label: string;
    new_tickets: number;
    workable_pool: number;
    closed_or_resolved: number;
    resolved_percent: number | null;
    carry_forward: number;
    help: {
        new_tickets: string;
        workable_pool: string;
        closed_or_resolved: string;
        resolved_percent: string;
        carry_forward: string;
    };
};

function MetricHelp({ label, help }: { label: string; help: string }) {
    return (
        <Popover className="relative">
            <PopoverButton
                className="group inline-flex size-6 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-emerald-100 hover:text-emerald-700 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none data-open:bg-emerald-100 data-open:text-emerald-700 dark:hover:bg-emerald-950 dark:hover:text-emerald-300 dark:data-open:bg-emerald-950 dark:data-open:text-emerald-300"
                aria-label={`About ${label}`}
            >
                <Info className="size-3.5 group-hover:hidden group-data-open:hidden" />
                <Pointer className="hidden size-3.5 group-hover:block group-data-open:block" />
            </PopoverButton>
            <PopoverPanel
                transition
                anchor="right start"
                className="z-50 w-80 origin-left text-left transition duration-150 ease-out data-closed:scale-95 data-closed:opacity-0"
            >
                <div className="overflow-hidden rounded-xl border border-emerald-200 bg-popover shadow-xl dark:border-emerald-800">
                    <div className="flex items-center gap-2 bg-emerald-700 px-3 py-2 text-white dark:bg-emerald-800">
                        <span className="inline-flex size-6 items-center justify-center rounded-full bg-white/15">
                            <Info className="size-3.5" />
                        </span>
                        <p className="text-sm font-semibold">{label}</p>
                    </div>
                    <p className="px-3 py-3 text-sm leading-relaxed text-foreground">
                        {help}
                    </p>
                </div>
            </PopoverPanel>
        </Popover>
    );
}

export default function OverallSummaryWidget({
    summary,
}: {
    summary: OverallSummary;
}) {
    const rows = [
        {
            label: 'New tickets',
            value: formatNumber(summary.new_tickets),
            help: summary.help.new_tickets,
        },
        {
            label: 'Workable pool (Includes C/F)',
            value: formatNumber(summary.workable_pool),
            help: summary.help.workable_pool,
        },
        {
            label: 'Closed/Resolved',
            value: formatNumber(summary.closed_or_resolved),
            help: summary.help.closed_or_resolved,
        },
        {
            label: 'Resolved %',
            value: formatPercent(summary.resolved_percent),
            help: summary.help.resolved_percent,
        },
        {
            label: 'Carry-forward',
            value: formatNumber(summary.carry_forward),
            help: summary.help.carry_forward,
        },
    ];

    return (
        <div className="overflow-hidden rounded-lg border">
            <table className="w-full text-left text-sm">
                <thead>
                    <tr className="bg-emerald-700 text-white dark:bg-emerald-800">
                        <th
                            colSpan={2}
                            className="px-3 py-2 text-sm font-semibold"
                        >
                            {summary.title}
                        </th>
                    </tr>
                    <tr className="bg-emerald-600 text-white dark:bg-emerald-700">
                        <th className="px-3 py-2 font-medium">Group metric</th>
                        <th className="px-3 py-2 text-right font-medium">
                            {summary.period_label}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.label} className="border-t bg-card">
                            <td className="px-3 py-2">
                                <div className="flex items-center gap-1.5">
                                    <span>{row.label}</span>
                                    <MetricHelp
                                        label={row.label}
                                        help={row.help}
                                    />
                                </div>
                            </td>
                            <td className="px-3 py-2 text-right tabular-nums">
                                {row.value}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
