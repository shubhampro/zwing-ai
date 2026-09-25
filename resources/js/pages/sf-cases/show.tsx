import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { formatDateTime, formatDay } from '@/lib/datetime';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index, show } from '@/routes/sf-cases';

type Hold = {
    id: number;
    group_name: string;
    started_at: string | null;
    ended_at: string | null;
    held_minutes: number;
    is_open: boolean;
};

type History = {
    id: number;
    field: string;
    old_value: string | null;
    new_value: string | null;
    changed_by: string | null;
    changed_at: string | null;
};

type Activity = {
    id: number;
    source: string;
    type: string | null;
    subject: string | null;
    body: string | null;
    preview: string | null;
    author_name: string | null;
    is_incoming: boolean;
    occurred_at: string | null;
};

type Brief = {
    status: string | null;
    path: string[];
    ask: string | null;
    customer: string | null;
    note: string | null;
    mail: string | null;
};

type TimelineEvent = {
    key: string;
    kind: 'opened' | 'path' | 'customer' | 'note' | 'closed' | 'open';
    at: string | null;
    title: string;
    body: string | null;
    meta: string | null;
};

type Ticket = {
    id: number;
    sf_id: string;
    case_number: string;
    subject: string;
    description: string | null;
    status: string;
    priority: string | null;
    type: string | null;
    origin: string | null;
    is_closed: boolean;
    is_spam: boolean;
    product: string | null;
    product_name: string | null;
    application: string | null;
    module: string | null;
    sub_module: string | null;
    group_name: string | null;
    first_assigned_group: string | null;
    owner_name: string | null;
    agent_name: string | null;
    requester_name: string | null;
    account: { id: number; sf_id: string; name: string } | null;
    tags: string | null;
    size: string | null;
    jira_id: string | null;
    jira_status: string | null;
    created_at_sf: string | null;
    resolved_at_sf: string | null;
    closed_at_sf: string | null;
    last_modified_at_sf: string | null;
    synced_at: string | null;
    resolution_minutes: number | null;
    zwing_resolution_minutes: number | null;
    activity_summary: string | null;
    activity_summarized_at: string | null;
    brief: Brief;
    timeline: TimelineEvent[];
    holds: Hold[];
    histories: History[];
    activities: Activity[];
};

type TabId = 'summary' | 'details' | 'holds' | 'history' | 'activity';

function dash(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value);
}

function clip(value: string | null | undefined, limit = 90): string {
    const text = dash(value);

    if (text === '—' || text.length <= limit) {
        return text;
    }

    return `${text.slice(0, limit - 1)}…`;
}

function HtmlBlock({ html }: { html: string | null | undefined }) {
    if (!html) {
        return <p className="text-sm text-muted-foreground">—</p>;
    }

    return (
        <div
            className="sf-html text-sm leading-relaxed [&_a]:text-primary [&_a]:underline [&_h1]:text-base [&_h1]:font-semibold [&_h2]:text-base [&_h2]:font-semibold [&_li]:ml-4 [&_ol]:list-decimal [&_p]:mb-2 [&_p:last-child]:mb-0 [&_table]:my-2 [&_td]:border [&_td]:px-2 [&_td]:py-1 [&_th]:border [&_th]:px-2 [&_ul]:list-disc"
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}

function activityTone(activity: Activity): string {
    if (activity.is_incoming) {
        return 'border-amber-500/40 bg-amber-500/10';
    }

    if (activity.source === 'email') {
        return 'border-emerald-500/40 bg-emerald-500/10';
    }

    return 'border-sky-500/40 bg-sky-500/10';
}

function formatMinutes(minutes: number | null): string {
    if (minutes === null) {
        return '—';
    }

    if (minutes < 60) {
        return `${minutes}m`;
    }

    if (minutes < 60 * 24) {
        return `${(minutes / 60).toFixed(1)}h`;
    }

    return `${(minutes / (60 * 24)).toFixed(1)}d`;
}

function Kpi({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 truncate text-sm font-semibold">{value}</p>
        </div>
    );
}

function Field({
    label,
    value,
}: {
    label: string;
    value: string | number | null | undefined;
}) {
    return (
        <div className="rounded-md bg-muted/50 px-3 py-2">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="truncate text-sm font-medium">{dash(value)}</dd>
        </div>
    );
}

const kindDot: Record<TimelineEvent['kind'], string> = {
    opened: 'bg-sky-500 ring-sky-500/25',
    path: 'bg-zinc-400 ring-zinc-400/25',
    customer: 'bg-amber-500 ring-amber-500/25',
    note: 'bg-emerald-500 ring-emerald-500/25',
    closed: 'bg-primary ring-primary/25',
    open: 'bg-orange-500 ring-orange-500/25',
};

const kindCard: Record<TimelineEvent['kind'], string> = {
    opened: 'border-sky-500/30 bg-sky-500/5',
    path: 'border-sidebar-border/70 bg-muted/30',
    customer: 'border-amber-500/30 bg-amber-500/5',
    note: 'border-emerald-500/30 bg-emerald-500/5',
    closed: 'border-primary/30 bg-primary/5',
    open: 'border-orange-500/30 bg-orange-500/5',
};

function SummaryTimeline({ events }: { events: TimelineEvent[] }) {
    if (events.length === 0) {
        return (
            <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                No summary yet. Run{' '}
                <code>php artisan sf:compute-case-summaries</code> after
                activity + holds.
            </p>
        );
    }

    return (
        <ol className="relative flex flex-col gap-0 pl-2">
            <span
                aria-hidden
                className="absolute top-2 bottom-2 left-[1.15rem] w-px bg-gradient-to-b from-sky-500 via-amber-400 to-primary"
            />
            {events.map((event) => (
                <li
                    key={event.key}
                    className="relative grid grid-cols-[6.5rem_1.5rem_minmax(0,1fr)] gap-3 py-3"
                >
                    <time className="pt-0.5 text-right text-xs text-muted-foreground tabular-nums">
                        {formatDay(event.at)}
                    </time>
                    <div className="relative flex justify-center">
                        <span
                            className={cn(
                                'mt-1 size-3 rounded-full ring-4',
                                kindDot[event.kind],
                            )}
                        />
                    </div>
                    <div
                        className={cn(
                            'rounded-lg border px-3 py-2.5',
                            kindCard[event.kind],
                        )}
                    >
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="text-sm font-semibold">
                                {event.title}
                            </p>
                            {event.meta && (
                                <Badge variant="outline">{event.meta}</Badge>
                            )}
                        </div>
                        {event.body && (
                            <p className="mt-1.5 text-sm leading-relaxed whitespace-pre-wrap">
                                {event.body}
                            </p>
                        )}
                    </div>
                </li>
            ))}
        </ol>
    );
}

function DetailsTab({ ticket }: { ticket: Ticket }) {
    return (
        <div className="flex flex-col gap-6">
            <section className="flex flex-col gap-3">
                <h3 className="text-sm font-semibold">Fields</h3>
                <dl className="grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-4">
                    <Field label="SF Id" value={ticket.sf_id} />
                    <Field label="Type" value={ticket.type} />
                    <Field label="Origin" value={ticket.origin} />
                    <Field label="Size" value={ticket.size} />
                    <Field label="Product" value={ticket.product} />
                    <Field label="Product name" value={ticket.product_name} />
                    <Field label="Application" value={ticket.application} />
                    <Field label="Module" value={ticket.module} />
                    <Field label="Sub-module" value={ticket.sub_module} />
                    <Field
                        label="First group"
                        value={ticket.first_assigned_group}
                    />
                    <Field label="Agent" value={ticket.agent_name} />
                    <Field label="Requester" value={ticket.requester_name} />
                    <Field label="Jira" value={ticket.jira_id} />
                    <Field label="Jira status" value={ticket.jira_status} />
                    <Field
                        label="Resolved"
                        value={formatDateTime(ticket.resolved_at_sf)}
                    />
                    <Field
                        label="Closed"
                        value={formatDateTime(ticket.closed_at_sf)}
                    />
                    <Field
                        label="Last modified"
                        value={formatDateTime(ticket.last_modified_at_sf)}
                    />
                    <Field label="Tags" value={ticket.tags} />
                    <Field
                        label="Synced"
                        value={formatDateTime(ticket.synced_at)}
                    />
                </dl>
            </section>

            <section className="flex flex-col gap-3">
                <h3 className="text-sm font-semibold">Description</h3>
                <div className="rounded-lg border border-sky-500/20 bg-sky-500/5 px-4 py-3">
                    <HtmlBlock html={ticket.description} />
                </div>
            </section>
        </div>
    );
}

function holdTone(name: string): { bar: string; card: string; dot: string } {
    const group = name.toLowerCase();

    if (
        group.includes('zwing') &&
        (group.includes('ba') || group.includes('product'))
    ) {
        return {
            bar: 'bg-violet-500',
            card: 'border-violet-500/30 bg-violet-500/5',
            dot: 'bg-violet-500',
        };
    }

    if (group.includes('zwing')) {
        return {
            bar: 'bg-sky-500',
            card: 'border-sky-500/30 bg-sky-500/5',
            dot: 'bg-sky-500',
        };
    }

    if (group.includes('l1')) {
        return {
            bar: 'bg-amber-500',
            card: 'border-amber-500/30 bg-amber-500/5',
            dot: 'bg-amber-500',
        };
    }

    if (group.includes('l2')) {
        return {
            bar: 'bg-orange-500',
            card: 'border-orange-500/30 bg-orange-500/5',
            dot: 'bg-orange-500',
        };
    }

    if (group.includes('integration')) {
        return {
            bar: 'bg-emerald-500',
            card: 'border-emerald-500/30 bg-emerald-500/5',
            dot: 'bg-emerald-500',
        };
    }

    return {
        bar: 'bg-zinc-400',
        card: 'border-sidebar-border/70 bg-muted/30 dark:border-sidebar-border',
        dot: 'bg-zinc-400',
    };
}

function holdShare(part: number, whole: number): string {
    if (whole <= 0) {
        return '—';
    }

    return `${((part / whole) * 100).toFixed(0)}%`;
}

function HoldsTab({ holds }: { holds: Hold[] }) {
    if (holds.length === 0) {
        return (
            <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                No hold stints. Run{' '}
                <code>php artisan sf:compute-case-holds</code>.
            </p>
        );
    }

    const total = holds.reduce((sum, hold) => sum + hold.held_minutes, 0);
    const zwing = holds
        .filter((hold) => hold.group_name.toLowerCase().includes('zwing'))
        .reduce((sum, hold) => sum + hold.held_minutes, 0);
    const open = holds.filter((hold) => hold.is_open);
    const longest = Math.max(...holds.map((hold) => hold.held_minutes), 1);
    const groups = Array.from(
        holds
            .reduce((map, hold) => {
                map.set(
                    hold.group_name,
                    (map.get(hold.group_name) ?? 0) + hold.held_minutes,
                );

                return map;
            }, new Map<string, number>())
            .entries(),
    )
        .map(([name, minutes]) => ({ name, minutes }))
        .sort((left, right) => right.minutes - left.minutes);

    return (
        <div className="flex flex-col gap-6">
            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                <Kpi label="Total hold" value={formatMinutes(total)} />
                <Kpi
                    label="Zwing hold"
                    value={`${formatMinutes(zwing)} · ${holdShare(zwing, total)}`}
                />
                <Kpi label="Groups" value={String(groups.length)} />
                <Kpi label="Current" value={open[0]?.group_name ?? 'Closed'} />
            </div>

            <section className="flex flex-col gap-3">
                <div className="flex items-baseline justify-between gap-3">
                    <h3 className="text-sm font-semibold">Time share</h3>
                    <p className="text-xs text-muted-foreground">
                        {holds.length} stint{holds.length === 1 ? '' : 's'} ·{' '}
                        {formatMinutes(total)}
                    </p>
                </div>
                <div
                    className="flex h-3 overflow-hidden rounded-full bg-muted"
                    title="Hold minutes by group"
                >
                    {groups.map((group) => (
                        <span
                            key={group.name}
                            title={`${group.name} · ${formatMinutes(group.minutes)} · ${holdShare(group.minutes, total)}`}
                            className={cn(
                                'h-full min-w-1 first:rounded-l-full last:rounded-r-full',
                                holdTone(group.name).bar,
                            )}
                            style={{
                                width: `${Math.max((group.minutes / total) * 100, 2)}%`,
                            }}
                        />
                    ))}
                </div>
                <ul className="flex flex-wrap gap-x-4 gap-y-2">
                    {groups.map((group) => (
                        <li
                            key={group.name}
                            className="flex items-center gap-2 text-sm"
                        >
                            <span
                                className={cn(
                                    'size-2.5 rounded-full',
                                    holdTone(group.name).dot,
                                )}
                            />
                            <span>{group.name}</span>
                            <span className="text-muted-foreground tabular-nums">
                                {formatMinutes(group.minutes)} ·{' '}
                                {holdShare(group.minutes, total)}
                            </span>
                        </li>
                    ))}
                </ul>
            </section>

            <ol className="flex flex-col gap-2">
                {holds.map((hold, index) => {
                    const tone = holdTone(hold.group_name);
                    const width = Math.max(
                        (hold.held_minutes / longest) * 100,
                        6,
                    );

                    return (
                        <li
                            key={hold.id}
                            className={cn(
                                'rounded-lg border px-4 py-3',
                                tone.card,
                            )}
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="flex min-w-0 items-center gap-2">
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {String(index + 1).padStart(2, '0')}
                                    </span>
                                    <span
                                        className={cn(
                                            'size-2.5 shrink-0 rounded-full',
                                            tone.dot,
                                        )}
                                    />
                                    <p className="truncate text-sm font-semibold">
                                        {hold.group_name}
                                    </p>
                                    {hold.is_open && (
                                        <Badge variant="default">Current</Badge>
                                    )}
                                </div>
                                <p className="text-sm font-semibold tabular-nums">
                                    {formatMinutes(hold.held_minutes)}
                                </p>
                            </div>
                            <div className="mt-2.5 h-1.5 overflow-hidden rounded-full bg-black/10 dark:bg-white/10">
                                <div
                                    className={cn(
                                        'h-full rounded-full',
                                        tone.bar,
                                    )}
                                    style={{ width: `${width}%` }}
                                />
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                {formatDateTime(hold.started_at)}
                                <span className="mx-1.5">→</span>
                                {hold.is_open
                                    ? 'still with group'
                                    : formatDateTime(hold.ended_at)}
                            </p>
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}

function HistoryTab({ histories }: { histories: History[] }) {
    return (
        <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
            <table className="w-full min-w-[36rem] text-left text-sm">
                <thead className="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th className="px-3 py-2 font-medium">Field</th>
                        <th className="px-3 py-2 font-medium">Old</th>
                        <th className="px-3 py-2 font-medium">New</th>
                        <th className="px-3 py-2 font-medium">By</th>
                        <th className="px-3 py-2 font-medium">At</th>
                    </tr>
                </thead>
                <tbody>
                    {histories.length === 0 ? (
                        <tr>
                            <td
                                colSpan={5}
                                className="px-3 py-8 text-center text-muted-foreground"
                            >
                                No history rows.
                            </td>
                        </tr>
                    ) : (
                        histories.map((history) => (
                            <tr
                                key={history.id}
                                className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                            >
                                <td className="px-3 py-2">{history.field}</td>
                                <td className="px-3 py-2">
                                    {dash(history.old_value)}
                                </td>
                                <td className="px-3 py-2">
                                    {dash(history.new_value)}
                                </td>
                                <td className="px-3 py-2">
                                    {dash(history.changed_by)}
                                </td>
                                <td className="px-3 py-2">
                                    {formatDateTime(history.changed_at)}
                                </td>
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
}

function ActivityTab({ activities }: { activities: Activity[] }) {
    if (activities.length === 0) {
        return (
            <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                No activity.
            </p>
        );
    }

    return (
        <ol className="flex flex-col gap-2">
            {activities.map((activity) => (
                <li key={activity.id}>
                    <Collapsible
                        className={cn(
                            'rounded-lg border',
                            activityTone(activity),
                        )}
                    >
                        <CollapsibleTrigger className="flex w-full items-start gap-2 px-3 py-2.5 text-left">
                            <ChevronDown className="mt-0.5 size-4 shrink-0 transition-transform [[data-state=open]_&]:rotate-180" />
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                    <span>
                                        {formatDateTime(activity.occurred_at)}
                                    </span>
                                    <Badge variant="outline">
                                        {activity.source}
                                    </Badge>
                                    <Badge
                                        variant={
                                            activity.is_incoming
                                                ? 'secondary'
                                                : 'default'
                                        }
                                    >
                                        {activity.is_incoming ? 'In' : 'Out'}
                                    </Badge>
                                    <span>{dash(activity.author_name)}</span>
                                </div>
                                <p className="mt-1 truncate text-sm">
                                    {clip(activity.preview ?? activity.subject)}
                                </p>
                            </div>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="border-t border-black/5 px-3 py-3 dark:border-white/10">
                            {activity.subject && (
                                <p className="mb-2 text-sm font-medium">
                                    {activity.subject}
                                </p>
                            )}
                            <HtmlBlock html={activity.body} />
                        </CollapsibleContent>
                    </Collapsible>
                </li>
            ))}
        </ol>
    );
}

function CaseTabs({ ticket }: { ticket: Ticket }) {
    const [tab, setTab] = useState<TabId>('summary');

    const tabs: { id: TabId; label: string; count?: number }[] = [
        { id: 'summary', label: 'Summary' },
        { id: 'details', label: 'Details' },
        { id: 'holds', label: 'Holds', count: ticket.holds.length },
        { id: 'history', label: 'History', count: ticket.histories.length },
        { id: 'activity', label: 'Activity', count: ticket.activities.length },
    ];

    return (
        <section className="flex flex-col gap-4">
            <div
                role="tablist"
                aria-label="Case sections"
                className="flex flex-wrap gap-1 border-b border-sidebar-border/70 dark:border-sidebar-border"
            >
                {tabs.map((item) => {
                    const selected = tab === item.id;

                    return (
                        <button
                            key={item.id}
                            type="button"
                            role="tab"
                            id={`case-tab-${item.id}`}
                            aria-selected={selected}
                            aria-controls={`case-panel-${item.id}`}
                            tabIndex={selected ? 0 : -1}
                            onClick={() => setTab(item.id)}
                            className={cn(
                                '-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm transition-colors',
                                selected
                                    ? 'border-primary font-semibold text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {item.label}
                            {item.count !== undefined && (
                                <span
                                    className={cn(
                                        'rounded-full px-1.5 py-0.5 text-[11px] tabular-nums',
                                        selected
                                            ? 'bg-primary/10 text-foreground'
                                            : 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {item.count}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>

            <div
                role="tabpanel"
                id={`case-panel-${tab}`}
                aria-labelledby={`case-tab-${tab}`}
            >
                {tab === 'summary' && (
                    <SummaryTimeline events={ticket.timeline} />
                )}
                {tab === 'details' && <DetailsTab ticket={ticket} />}
                {tab === 'holds' && <HoldsTab holds={ticket.holds} />}
                {tab === 'history' && (
                    <HistoryTab histories={ticket.histories} />
                )}
                {tab === 'activity' && (
                    <ActivityTab activities={ticket.activities} />
                )}
            </div>
        </section>
    );
}

export default function SalesforceCaseShow({ ticket }: { ticket: Ticket }) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'SF Cases', href: index() },
            { title: ticket.case_number, href: show.url(ticket.case_number) },
        ],
    });

    return (
        <>
            <Head title={`${ticket.case_number} · ${ticket.subject}`} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <Button variant="outline" size="sm" className="w-fit" asChild>
                    <Link href={index.url()}>
                        <ArrowLeft className="size-4" />
                        Back to list
                    </Link>
                </Button>

                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title={`${ticket.case_number} · ${ticket.subject}`}
                        description={
                            ticket.account
                                ? ticket.account.name
                                : `SF ${ticket.sf_id}`
                        }
                        className="mb-0"
                    />
                    <div className="flex flex-wrap gap-2">
                        <Badge
                            variant={ticket.is_closed ? 'secondary' : 'default'}
                        >
                            {ticket.status}
                        </Badge>
                        {ticket.priority && (
                            <Badge variant="outline">{ticket.priority}</Badge>
                        )}
                        {ticket.type && (
                            <Badge variant="outline">{ticket.type}</Badge>
                        )}
                        {ticket.is_spam && (
                            <Badge variant="destructive">Spam</Badge>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                    <Kpi label="Account" value={dash(ticket.account?.name)} />
                    <Kpi label="Owner" value={dash(ticket.owner_name)} />
                    <Kpi label="Group" value={dash(ticket.group_name)} />
                    <Kpi
                        label="Opened"
                        value={formatDateTime(ticket.created_at_sf)}
                    />
                    <Kpi
                        label="TTR"
                        value={formatMinutes(ticket.resolution_minutes)}
                    />
                    <Kpi
                        label="Zwing TTR"
                        value={formatMinutes(ticket.zwing_resolution_minutes)}
                    />
                </div>

                <CaseTabs ticket={ticket} />
            </div>
        </>
    );
}
