import { Head, Link, setLayoutProps, usePoll } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDateOnly } from '@/lib/datetime';
import { dashboard } from '@/routes';
import { details as detailsRoute, index, show } from '@/routes/sf-mbr';

type Application = {
    id: number;
    name: string;
};

type ReportStatus = 'pending' | 'generating' | 'ready' | 'failed';

type Report = {
    id: number;
    title: string;
    starts_on: string | null;
    ends_on: string | null;
    status: ReportStatus;
    applications: Application[];
    all_applications: boolean;
};

type AccountRow = {
    id: number;
    account: string | null;
    application: string | null;
    backlog_ticket_count: number;
    created_ticket_count: number;
    resolved_or_closed_count: number;
    open_ticket_count: number;
};

function formatNumber(value: number): string {
    return new Intl.NumberFormat('en-IN').format(value);
}

function scopeLabel(report: Report): string {
    const apps = report.all_applications
        ? 'All applications'
        : report.applications.map((application) => application.name).join(', ');

    return `${apps} · ${formatDateOnly(report.starts_on)} – ${formatDateOnly(report.ends_on)}`;
}

export default function SfMbrDetails({
    report,
    accounts,
}: {
    report: Report;
    accounts: AccountRow[];
}) {
    const isActive =
        report.status === 'generating' || report.status === 'pending';

    usePoll(2000, { only: ['report', 'accounts'] }, { autoStart: isActive });

    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'MBR Report', href: index() },
            { title: report.title, href: show(report.id) },
            { title: 'Details Report', href: detailsRoute(report.id) },
        ],
    });

    return (
        <>
            <Head title={`Details Report · ${report.title}`} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Details Report"
                        description={scopeLabel(report)}
                        className="mb-0"
                    />
                    <Button size="sm" variant="outline" asChild>
                        <Link href={show.url(report.id)}>Back</Link>
                    </Button>
                </div>

                {report.status === 'ready' ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Accounts</CardTitle>
                            <CardDescription>
                                One row per customer and application. Backlog
                                is open at range start. Created and
                                resolved/closed are period events. Open is
                                leftover at range end.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full min-w-[40rem] text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">
                                                Account
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Application
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                Backlog
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                Created
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                Resolved / closed
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                Open
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {accounts.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={6}
                                                    className="px-3 py-8 text-center text-muted-foreground"
                                                >
                                                    No accounts in this range.
                                                </td>
                                            </tr>
                                        ) : (
                                            accounts.map((row) => (
                                                <tr
                                                    key={row.id}
                                                    className="border-t"
                                                >
                                                    <td className="px-3 py-2 font-medium">
                                                        {row.account ?? '—'}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        {row.application ??
                                                            '—'}
                                                    </td>
                                                    <td className="px-3 py-2 text-right tabular-nums">
                                                        {formatNumber(
                                                            row.backlog_ticket_count,
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 text-right tabular-nums">
                                                        {formatNumber(
                                                            row.created_ticket_count,
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 text-right tabular-nums">
                                                        {formatNumber(
                                                            row.resolved_or_closed_count,
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 text-right tabular-nums">
                                                        {formatNumber(
                                                            row.open_ticket_count,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="flex items-center gap-3 rounded-lg border px-3 py-4 text-sm text-muted-foreground">
                        {report.status === 'failed' ? (
                            <p>Details unavailable. Report generation failed.</p>
                        ) : (
                            <>
                                <LoaderCircle className="size-4 animate-spin text-amber-500" />
                                <p>Preparing details from segregated accounts.</p>
                            </>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
