import { Head, Link, router, useForm } from '@inertiajs/react';
import { DataTable } from '@niveshmintra/react-datatable';
import type { ColumnDef } from '@niveshmintra/react-datatable';
import { Link2, MoreHorizontal, Pencil, Eye, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
    attachZwingVendor,
    destroy,
    updateFromZwingVendor,
    zwingVendors,
} from '@/actions/App/Http/Controllers/OrganizationController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useCan } from '@/hooks/use-can';
import { dashboard } from '@/routes';
import { create, edit, index, show } from '@/routes/organizations';

type Organization = {
    id: number;
    name: string;
    ba_code: string;
    vendor_id: number;
    organization_connections_count?: number;
    created_at: string;
};

type ZwingVendor = {
    id: number;
    name: string;
    ba_code: string;
};

function DeleteDialog({
    organization,
    open,
    onOpenChange,
}: {
    organization: Organization;
    open: boolean;
    onOpenChange: (v: boolean) => void;
}) {
    const { delete: deleteOrg, processing } = useForm();

    function confirm() {
        deleteOrg(destroy.url(organization.id), {
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete organization?</DialogTitle>
                    <DialogDescription>
                        This will permanently delete{' '}
                        <span className="font-medium text-foreground">
                            "{organization.name}"
                        </span>
                        . This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={confirm}
                        disabled={processing}
                    >
                        {processing ? 'Deleting…' : 'Delete'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function AttachZwingVendorDialog({
    onOpenChange,
}: {
    onOpenChange: (v: boolean) => void;
}) {
    const [mode, setMode] = useState<'attach' | 'update'>('attach');
    const [vendors, setVendors] = useState<ZwingVendor[]>([]);
    const [attachedVendorIds, setAttachedVendorIds] = useState<Set<number>>(
        new Set(),
    );
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [attachingId, setAttachingId] = useState<number | null>(null);
    const [processing, setProcessing] = useState(false);
    const [vendorError, setVendorError] = useState<string | undefined>();

    useEffect(() => {
        let cancelled = false;

        fetch(zwingVendors.url(), {
            headers: { Accept: 'application/json' },
        })
            .then(async (res) => {
                if (!res.ok) {
                    throw new Error(`Failed to load vendors (${res.status})`);
                }

                return res.json() as Promise<{
                    vendors: ZwingVendor[];
                    attached_vendor_ids: number[];
                }>;
            })
            .then((json) => {
                if (cancelled) {
                    return;
                }

                setVendors(json.vendors ?? []);
                setAttachedVendorIds(new Set(json.attached_vendor_ids ?? []));
            })
            .catch((err: unknown) => {
                if (cancelled) {
                    return;
                }

                setLoadError(
                    err instanceof Error
                        ? err.message
                        : 'Failed to load vendors',
                );
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, []);

    const filteredVendors = useMemo(
        () =>
            vendors.filter((vendor) =>
                mode === 'attach'
                    ? !attachedVendorIds.has(vendor.id)
                    : attachedVendorIds.has(vendor.id),
            ),
        [attachedVendorIds, mode, vendors],
    );

    const syncVendor = useCallback(
        (vendor: ZwingVendor) => {
            setVendorError(undefined);
            setAttachingId(vendor.id);
            setProcessing(true);

            router.post(
                mode === 'attach'
                    ? attachZwingVendor.url()
                    : updateFromZwingVendor.url(),
                { vendor_id: vendor.id },
                {
                    preserveScroll: true,
                    onSuccess: () => onOpenChange(false),
                    onError: (errors) => {
                        setVendorError(
                            typeof errors.vendor_id === 'string'
                                ? errors.vendor_id
                                : mode === 'attach'
                                  ? 'Failed to attach vendor.'
                                  : 'Failed to update organization.',
                        );
                    },
                    onFinish: () => {
                        setProcessing(false);
                        setAttachingId(null);
                    },
                },
            );
        },
        [mode, onOpenChange],
    );

    const columns = useMemo<ColumnDef<ZwingVendor>[]>(
        () => [
            {
                id: 'id',
                header: 'V_id',
                accessorKey: 'id',
                cell: ({ value }) => (
                    <span className="font-mono text-xs tabular-nums">
                        {value as number}
                    </span>
                ),
            },
            {
                id: 'name',
                header: 'Name',
                accessorKey: 'name',
                cell: ({ value }) => (
                    <span className="font-medium">{String(value)}</span>
                ),
            },
            {
                id: 'ba_code',
                header: 'BA Code',
                accessorKey: 'ba_code',
                cell: ({ value }) => (
                    <span className="font-mono text-xs">{String(value)}</span>
                ),
            },
            {
                id: 'actions',
                header: '',
                enableSorting: false,
                pinned: 'right',
                width: 110,
                cell: ({ row }) => {
                    const busy = processing && attachingId === row.id;

                    return (
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={processing || busy}
                            onClick={(e) => {
                                e.stopPropagation();
                                syncVendor(row);
                            }}
                        >
                            {busy
                                ? mode === 'attach'
                                    ? 'Attaching…'
                                    : 'Updating…'
                                : mode === 'attach'
                                  ? 'Attach'
                                  : 'Update'}
                        </Button>
                    );
                },
            },
        ],
        [attachingId, mode, processing, syncVendor],
    );

    return (
        <Dialog open onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[90vh] max-w-4xl flex-col gap-4 overflow-hidden sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle>
                        {mode === 'attach'
                            ? 'Attach from Zwing Master'
                            : 'Update from Zwing Master'}
                    </DialogTitle>
                    <DialogDescription>
                        {mode === 'attach'
                            ? 'Pick a vendor not yet linked. Name, BA code, vendor ID, and DB name are copied into a new organization.'
                            : 'Pick an already linked vendor. Name, BA code, and encrypted DB name refresh from Zwing Master.'}
                    </DialogDescription>
                </DialogHeader>

                <ToggleGroup
                    type="single"
                    variant="outline"
                    size="sm"
                    value={mode}
                    onValueChange={(value) => {
                        if (value === 'attach' || value === 'update') {
                            setMode(value);
                            setVendorError(undefined);
                        }
                    }}
                    className="w-fit"
                    disabled={processing}
                >
                    <ToggleGroupItem value="attach" className="px-3">
                        Attach new
                    </ToggleGroupItem>
                    <ToggleGroupItem value="update" className="px-3">
                        Update existing
                    </ToggleGroupItem>
                </ToggleGroup>

                <InputError message={vendorError} />

                <div className="min-h-0 flex-1 overflow-auto">
                    {loading && (
                        <p className="px-3 py-8 text-center text-sm text-muted-foreground">
                            Loading vendors…
                        </p>
                    )}

                    {!loading && loadError && (
                        <p className="px-3 py-8 text-center text-sm text-destructive">
                            {loadError}
                        </p>
                    )}

                    {!loading && !loadError && (
                        <DataTable
                            columns={columns}
                            data={filteredVendors}
                            getRowId={(row) => String(row.id)}
                            pageSize={10}
                            searchPlaceholder="Search V_id, name, BA code, DB name…"
                            emptyMessage={
                                mode === 'attach'
                                    ? 'No new vendors to attach.'
                                    : 'No attached organizations to update.'
                            }
                        />
                    )}
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function OrganizationActions({
    organization,
    onDelete,
}: {
    organization: Organization;
    onDelete: (org: Organization) => void;
}) {
    const can = useCan();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="sm" className="cursor-pointer">
                    <MoreHorizontal className="size-4" />
                    <span className="sr-only">Actions</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {can('organizations.view') && (
                    <DropdownMenuItem asChild>
                        <Link href={show.url(organization.id)}>
                            <Eye className="size-4" />
                            View & APIs
                        </Link>
                    </DropdownMenuItem>
                )}
                {can('organizations.update') && (
                    <DropdownMenuItem asChild>
                        <Link href={edit.url(organization.id)}>
                            <Pencil className="size-4" />
                            Edit
                        </Link>
                    </DropdownMenuItem>
                )}
                {can('organizations.delete') && (
                    <DropdownMenuItem
                        className="text-destructive focus:text-destructive"
                        onSelect={() => onDelete(organization)}
                    >
                        <Trash2 className="size-4" />
                        Delete
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default function OrganizationsIndex({
    organizations,
}: {
    organizations: Organization[];
}) {
    const can = useCan();
    const [deletingOrg, setDeletingOrg] = useState<Organization | null>(null);
    const [attachOpen, setAttachOpen] = useState(false);
    const canCreate = can('organizations.create');
    const canAttach = can('organizations.attach-zwing');

    const columns = useMemo<ColumnDef<Organization>[]>(
        () => [
            {
                id: 'id',
                header: '#',
                accessorKey: 'id',
                width: 64,
                cell: ({ value }) => (
                    <span className="font-mono text-xs text-muted-foreground">
                        {value as number}
                    </span>
                ),
            },
            {
                id: 'name',
                header: 'Name',
                accessorKey: 'name',
                cell: ({ value }) => (
                    <span className="font-medium">{String(value)}</span>
                ),
            },
            {
                id: 'ba_code',
                header: 'BA Code',
                accessorKey: 'ba_code',
                width: 140,
                cell: ({ value }) => (
                    <span className="font-mono text-xs">{String(value)}</span>
                ),
            },
            {
                id: 'vendor_id',
                header: 'Vendor ID',
                accessorKey: 'vendor_id',
                width: 110,
                cell: ({ value }) => (
                    <span className="tabular-nums">{value as number}</span>
                ),
            },
            {
                id: 'apis',
                header: 'APIs',
                accessorFn: (row) => row.organization_connections_count ?? 0,
                width: 80,
                cell: ({ value }) => (
                    <span className="tabular-nums">{value as number}</span>
                ),
            },
            {
                id: 'created_at',
                header: 'Created at',
                accessorKey: 'created_at',
                width: 140,
                cell: ({ value }) => (
                    <span className="text-muted-foreground">
                        {new Date(String(value)).toLocaleDateString(undefined, {
                            dateStyle: 'medium',
                        })}
                    </span>
                ),
            },
            {
                id: 'actions',
                header: '',
                enableSorting: false,
                pinned: 'right',
                width: 56,
                cell: ({ row }) => (
                    <div className="text-right">
                        <OrganizationActions
                            organization={row}
                            onDelete={setDeletingOrg}
                        />
                    </div>
                ),
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Organizations" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        className="mb-0"
                        title="Organizations"
                        description="Manage organizations, BA codes and vendor mappings."
                    />
                    {(canAttach || canCreate) && (
                        <div className="flex flex-wrap gap-2 sm:shrink-0">
                            {canAttach && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setAttachOpen(true)}
                                >
                                    <Link2 className="size-4" />
                                    Attach from Zwing Master
                                </Button>
                            )}
                            {canCreate && (
                                <Button size="sm" asChild>
                                    <Link href={create.url()}>
                                        New organization
                                    </Link>
                                </Button>
                            )}
                        </div>
                    )}
                </div>

                {organizations.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-4 rounded-lg border border-dashed border-sidebar-border/70 px-6 py-16 text-center dark:border-sidebar-border">
                        <div className="space-y-1">
                            <p className="text-sm font-medium">
                                No organizations yet
                            </p>
                            <p className="max-w-sm text-sm text-muted-foreground">
                                {canAttach || canCreate
                                    ? 'Attach one from Zwing Master or create a new organization to get started.'
                                    : 'No organizations are available yet.'}
                            </p>
                        </div>
                        {(canAttach || canCreate) && (
                            <div className="flex flex-wrap justify-center gap-2">
                                {canAttach && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setAttachOpen(true)}
                                    >
                                        <Link2 className="size-4" />
                                        Attach from Zwing Master
                                    </Button>
                                )}
                                {canCreate && (
                                    <Button size="sm" asChild>
                                        <Link href={create.url()}>
                                            New organization
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>
                ) : (
                    <DataTable
                        columns={columns}
                        data={organizations}
                        getRowId={(row) => String(row.id)}
                        pageSize={10}
                        searchPlaceholder="Search organizations…"
                        emptyMessage={
                            <>
                                No organizations found.{' '}
                                <Link
                                    href={create.url()}
                                    className="text-foreground underline"
                                >
                                    Create one
                                </Link>
                                .
                            </>
                        }
                    />
                )}
            </div>

            {deletingOrg && (
                <DeleteDialog
                    organization={deletingOrg}
                    open={deletingOrg !== null}
                    onOpenChange={(v) => {
                        if (!v) {
                            setDeletingOrg(null);
                        }
                    }}
                />
            )}

            {attachOpen && (
                <AttachZwingVendorDialog onOpenChange={setAttachOpen} />
            )}
        </>
    );
}

OrganizationsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Organizations', href: index.url() },
    ],
};
