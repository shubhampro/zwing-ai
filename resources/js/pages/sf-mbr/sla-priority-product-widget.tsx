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

type SlaRow = {
    application_id: number;
    application: string;
    pool: number;
    sla_breach: number;
    breach_percent: number | null;
};

type SlaGroup = {
    priority: string;
    sla_label: string;
    sla_hours: number;
    rows: SlaRow[];
    totals: {
        pool: number;
        sla_breach: number;
        breach_percent: number | null;
    };
};

export type SlaByPriorityProduct = {
    title: string;
    period_label: string;
    help: {
        pool: string;
        sla_breach: string;
        breach_percent: string;
    };
    groups: SlaGroup[];
};

function MetricHelp({ label, help }: { label: string; help: string }) {
    return (
        <Popover className="relative">
            <PopoverButton
                className="group inline-flex size-6 shrink-0 cursor-pointer items-center justify-center rounded-full text-white/80 transition-colors hover:bg-white/15 hover:text-white focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:outline-none data-open:bg-white/15 data-open:text-white"
                aria-label={`About ${label}`}
            >
                <Info className="size-3.5 group-hover:hidden group-data-open:hidden" />
                <Pointer className="hidden size-3.5 group-hover:block group-data-open:block" />
            </PopoverButton>
            <PopoverPanel
                transition
                anchor="bottom start"
                className="z-50 w-80 origin-top text-left transition duration-150 ease-out data-closed:scale-95 data-closed:opacity-0"
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

function breachClass(value: number | null): string {
    if (value === null || value < 50) {
        return 'tabular-nums';
    }

    return 'tabular-nums font-medium text-red-600 dark:text-red-400';
}

export default function SlaPriorityProductWidget({
    sla,
}: {
    sla: SlaByPriorityProduct;
}) {
    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-left text-sm">
                <thead>
                    <tr className="bg-emerald-700 text-white dark:bg-emerald-800">
                        <th
                            colSpan={5}
                            className="px-3 py-2 text-sm font-semibold"
                        >
                            {sla.title} — {sla.period_label}
                        </th>
                    </tr>
                    <tr className="bg-emerald-600 text-white dark:bg-emerald-700">
                        <th className="px-3 py-2 font-medium">Priority</th>
                        <th className="px-3 py-2 font-medium">Application</th>
                        <th className="px-3 py-2 text-right font-medium">
                            <div className="flex items-center justify-end gap-1.5">
                                <span>Pool</span>
                                <MetricHelp
                                    label="Pool"
                                    help={sla.help.pool}
                                />
                            </div>
                        </th>
                        <th className="px-3 py-2 text-right font-medium">
                            <div className="flex items-center justify-end gap-1.5">
                                <span>SLA Breach</span>
                                <MetricHelp
                                    label="SLA Breach"
                                    help={sla.help.sla_breach}
                                />
                            </div>
                        </th>
                        <th className="px-3 py-2 text-right font-medium">
                            <div className="flex items-center justify-end gap-1.5">
                                <span>Breach %</span>
                                <MetricHelp
                                    label="Breach %"
                                    help={sla.help.breach_percent}
                                />
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {sla.groups.map((group) => (
                        <SlaGroupRows key={group.priority} group={group} />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function SlaGroupRows({ group }: { group: SlaGroup }) {
    const rowSpan = group.rows.length + 1;

    return (
        <>
            {group.rows.map((row, index) => (
                <tr key={row.application_id} className="border-t bg-card">
                    {index === 0 ? (
                        <td
                            rowSpan={rowSpan}
                            className="align-top px-3 py-2 font-medium"
                        >
                            {group.sla_label}
                        </td>
                    ) : null}
                    <td className="px-3 py-2">{row.application}</td>
                    <td className="px-3 py-2 text-right tabular-nums">
                        {formatNumber(row.pool)}
                    </td>
                    <td className="px-3 py-2 text-right tabular-nums">
                        {formatNumber(row.sla_breach)}
                    </td>
                    <td
                        className={`px-3 py-2 text-right ${breachClass(row.breach_percent)}`}
                    >
                        {formatPercent(row.breach_percent)}
                    </td>
                </tr>
            ))}
            <tr className="border-t bg-emerald-50 font-medium dark:bg-emerald-950/40">
                {group.rows.length === 0 ? (
                    <td className="px-3 py-2">{group.sla_label}</td>
                ) : null}
                <td className="px-3 py-2">{group.sla_label} total</td>
                <td className="px-3 py-2 text-right tabular-nums">
                    {formatNumber(group.totals.pool)}
                </td>
                <td className="px-3 py-2 text-right tabular-nums">
                    {formatNumber(group.totals.sla_breach)}
                </td>
                <td
                    className={`px-3 py-2 text-right ${breachClass(group.totals.breach_percent)}`}
                >
                    {formatPercent(group.totals.breach_percent)}
                </td>
            </tr>
        </>
    );
}
