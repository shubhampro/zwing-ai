import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type ScalarField = {
    key: string;
    required: boolean;
    default: string;
};

export type SlotField = {
    key: string;
    saved_sql_query_id: string;
    shape: string;
    sort_order: number;
};

export type SavedQueryOption = {
    id: number;
    title: string;
};

export const emptyScalar = (): ScalarField => ({
    key: '',
    required: false,
    default: '',
});

export const emptySlot = (sortOrder = 0): SlotField => ({
    key: '',
    saved_sql_query_id: '',
    shape: 'array',
    sort_order: sortOrder,
});

type Props = {
    name: string;
    description: string;
    scalars: ScalarField[];
    slots: SlotField[];
    savedQueries: SavedQueryOption[];
    slotShapes: string[];
    errors: Record<string, string>;
    processing: boolean;
    submitLabel: string;
    onNameChange: (value: string) => void;
    onDescriptionChange: (value: string) => void;
    onScalarsChange: (scalars: ScalarField[]) => void;
    onSlotsChange: (slots: SlotField[]) => void;
    onSubmit: (e: FormEvent) => void;
};

export function PayloadComposerForm({
    name,
    description,
    scalars,
    slots,
    savedQueries,
    slotShapes,
    errors,
    processing,
    submitLabel,
    onNameChange,
    onDescriptionChange,
    onScalarsChange,
    onSlotsChange,
    onSubmit,
}: Props) {
    return (
        <form onSubmit={onSubmit} className="flex max-w-3xl flex-col gap-6">
            <div className="space-y-1.5">
                <Label htmlFor="name">Name</Label>
                <Input
                    id="name"
                    value={name}
                    onChange={(e) => onNameChange(e.target.value)}
                    placeholder="Stock audit post"
                />
                <InputError message={errors.name} />
            </div>

            <div className="space-y-1.5">
                <Label htmlFor="description">Description</Label>
                <textarea
                    id="description"
                    value={description}
                    onChange={(e) => onDescriptionChange(e.target.value)}
                    rows={2}
                    className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                />
                <InputError message={errors.description} />
            </div>

            <div className="space-y-3">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-sm font-medium">Scalars</h2>
                        <p className="text-xs text-muted-foreground">
                            Header fields copied into the payload as-is.
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            onScalarsChange([...scalars, emptyScalar()])
                        }
                    >
                        <Plus className="size-4" />
                        Add scalar
                    </Button>
                </div>

                {scalars.map((scalar, index) => (
                    <div
                        key={index}
                        className="grid gap-3 rounded-lg border border-sidebar-border/70 p-3 sm:grid-cols-[1fr_1fr_auto_auto]"
                    >
                        <div className="space-y-1.5">
                            <Label>Key</Label>
                            <Input
                                value={scalar.key}
                                onChange={(e) => {
                                    const next = [...scalars];
                                    next[index] = {
                                        ...scalar,
                                        key: e.target.value,
                                    };
                                    onScalarsChange(next);
                                }}
                                placeholder="storeId"
                                className="font-mono text-sm"
                            />
                            <InputError
                                message={errors[`scalars.${index}.key`]}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Default</Label>
                            <Input
                                value={scalar.default}
                                onChange={(e) => {
                                    const next = [...scalars];
                                    next[index] = {
                                        ...scalar,
                                        default: e.target.value,
                                    };
                                    onScalarsChange(next);
                                }}
                            />
                        </div>
                        <div className="flex items-end gap-2 pb-2">
                            <Checkbox
                                id={`scalar-required-${index}`}
                                checked={scalar.required}
                                onCheckedChange={(checked) => {
                                    const next = [...scalars];
                                    next[index] = {
                                        ...scalar,
                                        required: checked === true,
                                    };
                                    onScalarsChange(next);
                                }}
                            />
                            <Label htmlFor={`scalar-required-${index}`}>
                                Required
                            </Label>
                        </div>
                        <div className="flex items-end">
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                onClick={() =>
                                    onScalarsChange(
                                        scalars.filter((_, i) => i !== index),
                                    )
                                }
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    </div>
                ))}
                <InputError message={errors.scalars} />
            </div>

            <div className="space-y-3">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-sm font-medium">Query slots</h2>
                        <p className="text-xs text-muted-foreground">
                            Each slot runs a saved SQL query. Column aliases
                            become JSON keys. Use{' '}
                            <code className="font-mono">:binding</code> in SQL.
                            Object + empty key = merge into root like scalars.
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            onSlotsChange([
                                ...slots,
                                emptySlot(slots.length),
                            ])
                        }
                    >
                        <Plus className="size-4" />
                        Add slot
                    </Button>
                </div>

                {slots.map((slot, index) => (
                    <div
                        key={index}
                        className="grid gap-3 rounded-lg border border-sidebar-border/70 p-3 sm:grid-cols-[1fr_1.4fr_auto_auto]"
                    >
                        <div className="space-y-1.5">
                            <Label>
                                Payload key
                                {slot.shape === 'object' ? ' (optional)' : ''}
                            </Label>
                            <Input
                                value={slot.key}
                                onChange={(e) => {
                                    const next = [...slots];
                                    next[index] = {
                                        ...slot,
                                        key: e.target.value,
                                    };
                                    onSlotsChange(next);
                                }}
                                placeholder={
                                    slot.shape === 'object'
                                        ? 'empty = merge like scalars'
                                        : 'stockAuditItems'
                                }
                                className="font-mono text-sm"
                            />
                            <InputError
                                message={errors[`slots.${index}.key`]}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Saved SQL query</Label>
                            <Select
                                value={slot.saved_sql_query_id}
                                onValueChange={(value) => {
                                    const next = [...slots];
                                    next[index] = {
                                        ...slot,
                                        saved_sql_query_id: value,
                                    };
                                    onSlotsChange(next);
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select query" />
                                </SelectTrigger>
                                <SelectContent>
                                    {savedQueries.map((query) => (
                                        <SelectItem
                                            key={query.id}
                                            value={String(query.id)}
                                        >
                                            {query.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError
                                message={
                                    errors[`slots.${index}.saved_sql_query_id`]
                                }
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Shape</Label>
                            <Select
                                value={slot.shape}
                                onValueChange={(value) => {
                                    const next = [...slots];
                                    next[index] = {
                                        ...slot,
                                        shape: value,
                                    };
                                    onSlotsChange(next);
                                }}
                            >
                                <SelectTrigger className="w-28">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {slotShapes.map((shape) => (
                                        <SelectItem key={shape} value={shape}>
                                            {shape}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex items-end">
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                onClick={() =>
                                    onSlotsChange(
                                        slots.filter((_, i) => i !== index),
                                    )
                                }
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    </div>
                ))}
                <InputError message={errors.slots} />
            </div>

            <div>
                <Button type="submit" disabled={processing}>
                    {processing ? 'Saving…' : submitLabel}
                </Button>
            </div>
        </form>
    );
}
