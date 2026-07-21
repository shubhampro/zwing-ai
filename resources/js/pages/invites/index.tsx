import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useClipboard } from '@/hooks/use-clipboard';
import { dashboard } from '@/routes';
import { index, store } from '@/routes/invites';

type InviteRow = {
    id: number;
    email: string | null;
    role: string;
    registration_url: string;
    invited_by: { id: number; name: string } | null;
    used_by: { id: number; name: string } | null;
    used_at: string | null;
    expires_at: string | null;
    is_valid: boolean;
    created_at: string | null;
};

export default function InvitesIndex({
    invites,
    roles,
}: {
    invites: InviteRow[];
    roles: string[];
}) {
    const [, copy] = useClipboard();
    const [copiedId, setCopiedId] = useState<number | null>(null);
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        role: 'operator',
        days: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(store.url(), {
            onSuccess: () => reset('email', 'days'),
        });
    }

    return (
        <>
            <Head title="Invites" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <Heading
                    className="mb-0"
                    title="Invites"
                    description="Create single-use registration links with a role."
                />

                <form
                    onSubmit={submit}
                    className="grid max-w-2xl gap-4 rounded-lg border border-sidebar-border/70 p-4 sm:grid-cols-4 dark:border-sidebar-border"
                >
                    <div className="space-y-1.5 sm:col-span-2">
                        <Label htmlFor="email">Email (optional)</Label>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="user@example.com"
                        />
                        <InputError message={errors.email} />
                    </div>
                    <div className="space-y-1.5">
                        <Label>Role</Label>
                        <Select
                            value={data.role}
                            onValueChange={(value) => setData('role', value)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((role) => (
                                    <SelectItem key={role} value={role}>
                                        {role}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.role} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="days">Expires (days)</Label>
                        <Input
                            id="days"
                            type="number"
                            min={1}
                            value={data.days}
                            onChange={(e) => setData('days', e.target.value)}
                            placeholder="Optional"
                        />
                        <InputError message={errors.days} />
                    </div>
                    <div className="sm:col-span-4">
                        <Button type="submit" size="sm" disabled={processing}>
                            {processing ? 'Creating…' : 'Create invite'}
                        </Button>
                    </div>
                </form>

                <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full min-w-[860px] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Email</th>
                                <th className="px-3 py-2 font-medium">Role</th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">Link</th>
                                <th className="px-3 py-2 font-medium">
                                    Created
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {invites.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No invites yet.
                                    </td>
                                </tr>
                            )}
                            {invites.map((invite) => (
                                <tr
                                    key={invite.id}
                                    className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                >
                                    <td className="px-3 py-2">
                                        {invite.email ?? (
                                            <span className="text-muted-foreground">
                                                Any email
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {invite.role}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge
                                            variant={
                                                invite.is_valid
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {invite.is_valid
                                                ? 'Valid'
                                                : invite.used_at
                                                  ? 'Used'
                                                  : 'Expired'}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={async () => {
                                                await copy(
                                                    invite.registration_url,
                                                );
                                                setCopiedId(invite.id);
                                            }}
                                        >
                                            {copiedId === invite.id
                                                ? 'Copied'
                                                : 'Copy link'}
                                        </Button>
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {invite.created_at
                                            ? new Date(
                                                  invite.created_at,
                                              ).toLocaleDateString(undefined, {
                                                  dateStyle: 'medium',
                                              })
                                            : '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

InvitesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Invites', href: index.url() },
    ],
};
