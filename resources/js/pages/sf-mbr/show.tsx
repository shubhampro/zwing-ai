import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ArrowLeft } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index, show } from '@/routes/sf-mbr';
import {
    formatDays,
    formatHours,
    formatNumber,
    formatPct,
    shareOf,
} from './format';

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

type MixRow = {
    label: string;
    tickets: number;
    share: number;
};

type Ageing = {
    d0_1: number;
    d1_2: number;
    d2_5: number;
    d5_plus: number;
};

type SlaRow = {
    priority: string;
    sla_days: number;
    pool: number;
    resolved: number;
    open: number;
    breached_open: number;
    sla_breach_pct: number;
    sla_hit_pct: number | null;
    zwing_sla_breach_pct: number;
    median_ttr_days: number | null;
    p90_ttr_days: number | null;
};

type CustomerRow = {
    account_name: string;
    tickets: number;
    share: number;
};

type HoldRow = {
    group_name: string;
    held_minutes: number;
    held_days: number;
};

type AgentRow = {
    name: string;
    tickets: number;
    share: number;
};

type Highlights = {
    total_tickets: number;
    new_tickets: number;
    resolved_pct: number;
    carry_forward: number;
    net_backlog: number;
    mom_new: number | null;
    active_customers: number;
    avg_tickets_per_customer: number | null;
    urgent_sla_breach_pct: number;
    urgent_sla_hit_pct: number | null;
    zwing_urgent_sla_breach_pct: number;
    zwing_urgent_sla_hit_pct: number | null;
    frt_median_hours: number | null;
    ttr_median_days: number | null;
    ttr_p90_days: number | null;
    fcr_pct: number | null;
    l3_pct: number;
    integration: number;
};

type Waterfall = {
    received: number;
    to_ba: number;
    to_other: number;
    in_ladder: number;
    resolved_l1: number;
    to_l2: number;
    resolved_l2: number;
    to_integration: number;
    to_l3: number;
};

type Props = {
    title: string;
    owner: string;
    source: string;
    month: string;
    month_label: string;
    as_of: string;
    available_months: string[];
    highlights: Highlights;
    risks: string[];
    actions: string[];
    volume: VolumeRow[];
    demand: {
        net_backlog: number;
        type_mix: MixRow[];
        channel_mix: MixRow[];
    };
    speed: {
        frt_median_hours: number | null;
        frt_coverage_pct: number;
        ttr_median_days: number | null;
        ttr_p90_days: number | null;
        breached_open: number;
        resolved_ageing: Ageing;
        open_ageing: Ageing;
        sla_by_priority: SlaRow[];
    };
    quality: {
        csat_available: boolean;
        fcr_pct: number | null;
        repeat_pct: number | null;
        repeat_customers: number;
    };
    customer_load: {
        new_tickets: number;
        active_customers: number;
        avg_tickets_per_customer: number | null;
        customers: CustomerRow[];
    };
    concentration: MixRow[];
    top_customers: CustomerRow[];
    at_risk: CustomerRow[];
    waterfall: Waterfall;
    holds: HoldRow[];
    people: AgentRow[];
    product_mix: MixRow[];
};

const tableWrap =
    'overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border';
const th = 'px-3 py-2 font-medium';
const td = 'px-3 py-2';

export default function SalesforceMbrShow({
    title,
    owner,
    source,
    month,
    month_label,
    as_of,
    available_months,
    highlights,
    risks,
    actions,
    volume,
    demand,
    speed,
    quality,
    customer_load,
    concentration,
    top_customers,
    at_risk,
    waterfall,
    holds,
    people,
    product_mix,
}: Props) {
    const months = available_months.length > 0 ? available_months : [month];

    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Zwing MBR', href: index() },
            { title: month_label, href: show.url(month) },
        ],
    });

    return (
        <>
            <Head title={`${title} (${month_label})`} />

            <div className="flex flex-col gap-8 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div className="flex flex-col gap-3">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="w-fit -ml-2"
                            asChild
                        >
                            <Link href={index()}>
                                <ArrowLeft className="size-4" />
                                All months
                            </Link>
                        </Button>
                        <Heading
                            title={title}
                            description={`${owner} · ${month_label} · as of ${as_of}. ${source}. SLA: Urgent >1d · High >2d · Medium >5d.`}
                            className="mb-0"
                        />
                    </div>
                    <Select
                        value={month}
                        onValueChange={(value) =>
                            router.get(show.url(value), {}, { preserveScroll: true })
                        }
                    >
                        <SelectTrigger className="w-40" aria-label="Month">
                            <SelectValue placeholder="Month" />
                        </SelectTrigger>
                        <SelectContent>
                            {months.map((value) => (
                                <SelectItem key={value} value={value}>
                                    {value}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <Section
                    n="1"
                    title="Highlights"
                    description="Exec scorecard. Volume + speed + quality proxy."
                >
                    <section className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                        <Kpi
                            label="New tickets"
                            value={formatNumber(highlights.new_tickets)}
                        />
                        <Kpi
                            label="MoM new"
                            value={formatPct(highlights.mom_new, true)}
                        />
                        <Kpi
                            label="Resolved %"
                            value={formatPct(highlights.resolved_pct)}
                        />
                        <Kpi
                            label="Net backlog"
                            value={formatSigned(highlights.net_backlog)}
                            warn={highlights.net_backlog > 0}
                        />
                        <Kpi
                            label="Urgent SLA hit"
                            value={formatPct(highlights.urgent_sla_hit_pct)}
                            warn={(highlights.urgent_sla_breach_pct ?? 0) >= 0.4}
                        />
                        <Kpi
                            label="Zwing Urgent hit"
                            value={formatPct(highlights.zwing_urgent_sla_hit_pct)}
                            warn={
                                (highlights.zwing_urgent_sla_breach_pct ?? 0) >=
                                0.4
                            }
                        />
                        <Kpi
                            label="FRT median"
                            value={formatHours(highlights.frt_median_hours)}
                        />
                        <Kpi
                            label="TTR median"
                            value={`${formatDays(highlights.ttr_median_days)}d`}
                        />
                        <Kpi
                            label="TTR p90"
                            value={`${formatDays(highlights.ttr_p90_days)}d`}
                        />
                        <Kpi
                            label="FCR proxy"
                            value={formatPct(highlights.fcr_pct)}
                            warn={(highlights.fcr_pct ?? 1) < 0.5}
                        />
                        <Kpi
                            label="Escalated to L3"
                            value={formatPct(highlights.l3_pct)}
                        />
                        <Kpi
                            label="Active customers"
                            value={formatNumber(highlights.active_customers)}
                        />
                    </section>
                    <div className="grid gap-3 md:grid-cols-2">
                        <NoteList
                            title="Risks"
                            empty="No auto-risk this month."
                            items={risks}
                            warn
                        />
                        <NoteList
                            title="Actions"
                            empty="No auto-action this month."
                            items={actions}
                        />
                    </div>
                </Section>

                <Section
                    n="2"
                    title="Demand"
                    description="In vs out. Type and channel mix on new tickets."
                >
                    <div className={tableWrap}>
                        <table className="w-full min-w-[36rem] text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className={th}>Metric</th>
                                    {volume.map((row) => (
                                        <th
                                            key={row.key}
                                            className={cn(
                                                th,
                                                'text-right',
                                                row.key === month &&
                                                    'text-foreground',
                                            )}
                                        >
                                            {row.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                <VolumeLine
                                    label="Brought forward"
                                    month={month}
                                    volume={volume}
                                    value={(row) => row.brought_forward}
                                />
                                <VolumeLine
                                    label="New tickets"
                                    month={month}
                                    volume={volume}
                                    value={(row) => row.new_tickets}
                                />
                                <VolumeLine
                                    label="Pool"
                                    month={month}
                                    volume={volume}
                                    value={(row) => row.pool}
                                />
                                <VolumeLine
                                    label="Resolved"
                                    month={month}
                                    volume={volume}
                                    value={(row) => row.resolved}
                                />
                                <VolumeLine
                                    label="Carry-forward"
                                    month={month}
                                    volume={volume}
                                    value={(row) => row.carry_forward}
                                />
                                <VolumeLine
                                    label="Resolved %"
                                    month={month}
                                    volume={volume}
                                    value={(row) => row.resolved_pct}
                                    percent
                                />
                            </tbody>
                        </table>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Net backlog this month:{' '}
                        <span
                            className={cn(
                                'font-medium tabular-nums',
                                demand.net_backlog > 0 && 'text-destructive',
                            )}
                        >
                            {formatSigned(demand.net_backlog)}
                        </span>
                        . Positive = queue grew.
                    </p>
                    <div className="grid gap-6 xl:grid-cols-2">
                        <MixTable title="Type mix" rows={demand.type_mix} />
                        <MixTable
                            title="Channel mix"
                            rows={demand.channel_mix}
                        />
                    </div>
                </Section>

                <Section
                    n="3"
                    title="Speed / SLA"
                    description="FRT from first outbound activity. TTR = calendar days created → resolved. Zwing SLA = Zwing-Tech minutes."
                >
                    <section className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <Kpi
                            label="FRT coverage"
                            value={formatPct(speed.frt_coverage_pct)}
                        />
                        <Kpi
                            label="Breached still open"
                            value={formatNumber(speed.breached_open)}
                            warn={speed.breached_open > 0}
                        />
                        <Kpi
                            label="Urgent miss"
                            value={formatPct(highlights.urgent_sla_breach_pct)}
                            warn={highlights.urgent_sla_breach_pct >= 0.4}
                        />
                        <Kpi
                            label="Zwing Urgent miss"
                            value={formatPct(
                                highlights.zwing_urgent_sla_breach_pct,
                            )}
                            warn={
                                highlights.zwing_urgent_sla_breach_pct >= 0.4
                            }
                        />
                    </section>
                    <div className={tableWrap}>
                        <table className="w-full min-w-[56rem] text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className={th}>Priority</th>
                                    <th className={`${th} text-right`}>SLA</th>
                                    <th className={`${th} text-right`}>Pool</th>
                                    <th className={`${th} text-right`}>
                                        Resolved
                                    </th>
                                    <th className={`${th} text-right`}>Open</th>
                                    <th className={`${th} text-right`}>
                                        Breached open
                                    </th>
                                    <th className={`${th} text-right`}>
                                        SLA hit
                                    </th>
                                    <th className={`${th} text-right`}>
                                        Zwing miss
                                    </th>
                                    <th className={`${th} text-right`}>
                                        Median TTR
                                    </th>
                                    <th className={`${th} text-right`}>
                                        p90 TTR
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {speed.sla_by_priority.map((row) => (
                                    <tr
                                        key={row.priority}
                                        className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                    >
                                        <td className={`${td} font-medium`}>
                                            {row.priority}
                                        </td>
                                        <td
                                            className={`${td} text-right tabular-nums`}
                                        >
                                            {`>${row.sla_days}d`}
                                        </td>
                                        <td
                                            className={`${td} text-right tabular-nums`}
                                        >
                                            {formatNumber(row.pool)}
                                        </td>
                                        <td
                                            className={`${td} text-right tabular-nums`}
                                        >
                                            {formatNumber(row.resolved)}
                                        </td>
                                        <td
                                            className={`${td} text-right tabular-nums`}
                                        >
                                            {formatNumber(row.open)}
                                        </td>
                                        <td
                                            className={`${td} text-right tabular-nums`}
                                        >
                                            {formatNumber(row.breached_open)}
                                        </td>
                                        <td
                                            className={`${td} text-right tabular-nums`}
                                        >
                                            {formatPct(row.sla_hit_pct)}
                                        </td>
                                        <td
                                            className={`${td} text-right tabular-nums`}
                                        >
                                            {formatPct(row.zwing_sla_breach_pct)}
                                        </td>
                                        <td
                                            className={`${td} text-right tabular-nums`}
                                        >
                                            {formatDays(row.median_ttr_days)}
                                        </td>
                                        <td
                                            className={`${td} text-right tabular-nums`}
                                        >
                                            {formatDays(row.p90_ttr_days)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="grid gap-6 xl:grid-cols-2">
                        <AgeingTable
                            title="Resolved ageing"
                            ageing={speed.resolved_ageing}
                        />
                        <AgeingTable
                            title="Open ageing"
                            ageing={speed.open_ageing}
                        />
                    </div>
                </Section>

                <Section
                    n="4"
                    title="Quality"
                    description="CSAT not in dump. FCR proxy = new tickets resolved at L1 only. Repeat = accounts with 2+ new tickets."
                >
                    <section className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <Kpi label="CSAT" value="Not in dump" />
                        <Kpi
                            label="FCR proxy"
                            value={formatPct(quality.fcr_pct)}
                            warn={(quality.fcr_pct ?? 1) < 0.5}
                        />
                        <Kpi
                            label="Repeat accounts"
                            value={formatNumber(quality.repeat_customers)}
                        />
                        <Kpi
                            label="Repeat %"
                            value={formatPct(quality.repeat_pct)}
                        />
                    </section>
                </Section>

                <Section
                    n="5"
                    title="Customers"
                    description={`New tickets in ${month_label}, excluding accounts with “test” in the name.`}
                >
                    <section className="grid grid-cols-3 gap-3">
                        <Kpi
                            label="New (excl. test)"
                            value={formatNumber(customer_load.new_tickets)}
                        />
                        <Kpi
                            label="Active customers"
                            value={formatNumber(customer_load.active_customers)}
                        />
                        <Kpi
                            label="Avg / customer"
                            value={
                                customer_load.avg_tickets_per_customer === null
                                    ? '—'
                                    : customer_load.avg_tickets_per_customer.toFixed(
                                          2,
                                      )
                            }
                        />
                    </section>
                    <div className="grid gap-6 xl:grid-cols-2">
                        <MixTable
                            title="Concentration"
                            rows={concentration}
                        />
                        <div className={tableWrap}>
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className={th}>#</th>
                                        <th className={th}>Account</th>
                                        <th className={`${th} text-right`}>
                                            Tickets
                                        </th>
                                        <th className={`${th} text-right`}>
                                            Share
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {top_customers.length ? (
                                        top_customers.map((row, index) => (
                                            <tr
                                                key={row.account_name}
                                                className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                            >
                                                <td
                                                    className={`${td} text-muted-foreground`}
                                                >
                                                    {index + 1}
                                                </td>
                                                <td className={td}>
                                                    {row.account_name}
                                                    {at_risk.some(
                                                        (risk) =>
                                                            risk.account_name ===
                                                            row.account_name,
                                                    ) ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="ml-2"
                                                        >
                                                            Watch
                                                        </Badge>
                                                    ) : null}
                                                </td>
                                                <td
                                                    className={`${td} text-right tabular-nums`}
                                                >
                                                    {formatNumber(row.tickets)}
                                                </td>
                                                <td
                                                    className={`${td} text-right tabular-nums`}
                                                >
                                                    {formatPct(row.share)}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="px-3 py-8 text-center text-muted-foreground"
                                            >
                                                No Zwing tickets this month.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </Section>

                <Section
                    n="6"
                    title="Delivery path"
                    description="New tickets only. L1 = Helpdesk-L1 · L2 = Helpdesk-L2 · L3 = Zwing-Tech."
                >
                    <div className={tableWrap}>
                        <table className="w-full min-w-[28rem] text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className={th}>Stage</th>
                                    <th className={`${th} text-right`}>
                                        Tickets
                                    </th>
                                    <th className={`${th} text-right`}>
                                        % of received
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <WaterfallLine
                                    label="A. Tickets received"
                                    value={waterfall.received}
                                    whole={waterfall.received}
                                    strong
                                />
                                <WaterfallLine
                                    label="Less: sent to Product / BA"
                                    value={-waterfall.to_ba}
                                    whole={waterfall.received}
                                />
                                <WaterfallLine
                                    label="Less: sent to other teams"
                                    value={-waterfall.to_other}
                                    whole={waterfall.received}
                                />
                                <WaterfallLine
                                    label="B. Handled in L1 → L2 → L3"
                                    value={waterfall.in_ladder}
                                    whole={waterfall.received}
                                    strong
                                />
                                <WaterfallLine
                                    label="Less: resolved by L1"
                                    value={-waterfall.resolved_l1}
                                    whole={waterfall.received}
                                />
                                <WaterfallLine
                                    label="C. Escalated to L2"
                                    value={waterfall.to_l2}
                                    whole={waterfall.received}
                                    strong
                                />
                                <WaterfallLine
                                    label="Less: resolved by L2"
                                    value={-waterfall.resolved_l2}
                                    whole={waterfall.received}
                                />
                                <WaterfallLine
                                    label="Less: sent to Integration"
                                    value={-waterfall.to_integration}
                                    whole={waterfall.received}
                                />
                                <WaterfallLine
                                    label="D. Escalated to L3"
                                    value={waterfall.to_l3}
                                    whole={waterfall.received}
                                    strong
                                />
                            </tbody>
                        </table>
                    </div>
                    <div className={tableWrap}>
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className={th}>Group hold</th>
                                    <th className={`${th} text-right`}>
                                        Held days
                                    </th>
                                    <th className={`${th} text-right`}>
                                        Held minutes
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {holds.length ? (
                                    holds.map((row) => (
                                        <tr
                                            key={row.group_name}
                                            className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                        >
                                            <td className={td}>
                                                {row.group_name}
                                            </td>
                                            <td
                                                className={`${td} text-right tabular-nums`}
                                            >
                                                {row.held_days.toFixed(2)}
                                            </td>
                                            <td
                                                className={`${td} text-right tabular-nums`}
                                            >
                                                {formatNumber(row.held_minutes)}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={3}
                                            className="px-3 py-8 text-center text-muted-foreground"
                                        >
                                            No hold stints on cases resolved
                                            this month. Run{' '}
                                            <code>sf:compute-case-holds</code>.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </Section>

                <div className="grid gap-8 xl:grid-cols-2">
                    <Section
                        n="7"
                        title="People"
                        description="New tickets by agent, else owner. No occupancy in dump."
                    >
                        <MixNamedTable
                            nameHeader="Agent"
                            rows={people}
                            empty="No owner or agent names on new tickets."
                        />
                    </Section>
                    <Section
                        n="8"
                        title="Product / root cause"
                        description="New tickets by module."
                    >
                        <MixTable title="" rows={product_mix} />
                    </Section>
                </div>
            </div>
        </>
    );
}

function formatSigned(value: number): string {
    if (value > 0) {
        return `+${formatNumber(value)}`;
    }

    return formatNumber(value);
}

function Section({
    n,
    title,
    description,
    children,
}: {
    n: string;
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <section className="flex flex-col gap-3">
            <div>
                <h3 className="text-base font-semibold">
                    <span className="mr-2 text-muted-foreground">{n}.</span>
                    {title}
                </h3>
                {description ? (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
            {children}
        </section>
    );
}

function Kpi({
    label,
    value,
    warn = false,
}: {
    label: string;
    value: string;
    warn?: boolean;
}) {
    return (
        <div className="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p
                className={cn(
                    'mt-1 text-xl font-semibold tabular-nums',
                    warn && 'text-destructive',
                )}
            >
                {value}
            </p>
        </div>
    );
}

function NoteList({
    title,
    items,
    empty,
    warn = false,
}: {
    title: string;
    items: string[];
    empty: string;
    warn?: boolean;
}) {
    return (
        <div className="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
            <p className="text-xs font-medium text-muted-foreground">{title}</p>
            {items.length === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">{empty}</p>
            ) : (
                <ol className="mt-2 list-decimal space-y-1 pl-4 text-sm">
                    {items.map((item) => (
                        <li
                            key={item}
                            className={cn(warn && 'text-destructive')}
                        >
                            {item}
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}

function MixTable({ title, rows }: { title: string; rows: MixRow[] }) {
    return (
        <div className="flex flex-col gap-2">
            {title ? (
                <p className="text-sm font-medium text-muted-foreground">
                    {title}
                </p>
            ) : null}
            <div className={tableWrap}>
                <table className="w-full text-left text-sm">
                    <thead className="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th className={th}>Bucket</th>
                            <th className={`${th} text-right`}>Tickets</th>
                            <th className={`${th} text-right`}>Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length ? (
                            rows.map((row) => (
                                <tr
                                    key={row.label}
                                    className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                >
                                    <td className={td}>{row.label}</td>
                                    <td
                                        className={`${td} text-right tabular-nums`}
                                    >
                                        {formatNumber(row.tickets)}
                                    </td>
                                    <td
                                        className={`${td} text-right tabular-nums`}
                                    >
                                        {formatPct(row.share)}
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td
                                    colSpan={3}
                                    className="px-3 py-8 text-center text-muted-foreground"
                                >
                                    No rows.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function MixNamedTable({
    nameHeader,
    rows,
    empty,
}: {
    nameHeader: string;
    rows: AgentRow[];
    empty: string;
}) {
    return (
        <div className={tableWrap}>
            <table className="w-full text-left text-sm">
                <thead className="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th className={th}>{nameHeader}</th>
                        <th className={`${th} text-right`}>Tickets</th>
                        <th className={`${th} text-right`}>Share</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.length ? (
                        rows.map((row) => (
                            <tr
                                key={row.name}
                                className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                            >
                                <td className={td}>{row.name}</td>
                                <td
                                    className={`${td} text-right tabular-nums`}
                                >
                                    {formatNumber(row.tickets)}
                                </td>
                                <td
                                    className={`${td} text-right tabular-nums`}
                                >
                                    {formatPct(row.share)}
                                </td>
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <td
                                colSpan={3}
                                className="px-3 py-8 text-center text-muted-foreground"
                            >
                                {empty}
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function AgeingTable({ title, ageing }: { title: string; ageing: Ageing }) {
    const rows: Array<[string, number]> = [
        ['0–1d', ageing.d0_1],
        ['1–2d', ageing.d1_2],
        ['2–5d', ageing.d2_5],
        ['>5d', ageing.d5_plus],
    ];

    return (
        <div className="flex flex-col gap-2">
            <p className="text-sm font-medium text-muted-foreground">{title}</p>
            <div className={tableWrap}>
                <table className="w-full text-left text-sm">
                    <thead className="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th className={th}>Bucket</th>
                            <th className={`${th} text-right`}>Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map(([label, value]) => (
                            <tr
                                key={label}
                                className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                            >
                                <td className={td}>{label}</td>
                                <td
                                    className={`${td} text-right tabular-nums`}
                                >
                                    {formatPct(value)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function VolumeLine({
    label,
    month,
    volume,
    value,
    percent = false,
}: {
    label: string;
    month: string;
    volume: VolumeRow[];
    value: (row: VolumeRow) => number;
    percent?: boolean;
}) {
    return (
        <tr className="border-t border-sidebar-border/70 dark:border-sidebar-border">
            <td className={td}>{label}</td>
            {volume.map((row) => (
                <td
                    key={row.key}
                    className={cn(
                        td,
                        'text-right tabular-nums',
                        row.key === month && 'font-medium',
                    )}
                >
                    {percent
                        ? formatPct(value(row))
                        : formatNumber(value(row))}
                </td>
            ))}
        </tr>
    );
}

function WaterfallLine({
    label,
    value,
    whole,
    strong = false,
}: {
    label: string;
    value: number;
    whole: number;
    strong?: boolean;
}) {
    return (
        <tr
            className={cn(
                'border-t border-sidebar-border/70 dark:border-sidebar-border',
                strong && 'bg-muted/40',
            )}
        >
            <td className={cn(td, strong && 'font-medium')}>{label}</td>
            <td className={`${td} text-right tabular-nums`}>
                {formatNumber(value)}
            </td>
            <td className={`${td} text-right tabular-nums`}>
                {shareOf(Math.abs(value), whole)}
            </td>
        </tr>
    );
}

SalesforceMbrShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Zwing MBR', href: index() },
    ],
};
