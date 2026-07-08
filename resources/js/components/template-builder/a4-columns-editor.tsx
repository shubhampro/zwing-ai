import { Plus, X } from 'lucide-react';
import { VariableDropField } from '@/components/template-builder/dnd';
import { TextDataEditor } from '@/components/template-builder/text-data-editor';
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
import {
    createA4Column,
    type A4Align,
    type A4Column,
    type A4ColumnFormat,
    type A4ColumnMode,
    type A4TableVariant,
} from '@/lib/template-builder/a4';

export function A4ColumnsEditor({
    columns,
    blockId,
    onChange,
    showVariant = false,
    variant,
    compact,
    showHeader,
    onTablePatch,
}: {
    columns: A4Column[];
    blockId: string;
    onChange: (columns: A4Column[]) => void;
    showVariant?: boolean;
    variant?: A4TableVariant;
    compact?: boolean;
    showHeader?: boolean;
    onTablePatch?: (patch: { variant?: A4TableVariant; compact?: boolean; showHeader?: boolean }) => void;
}) {
    function updateColumn(id: string, patch: Partial<A4Column>) {
        onChange(columns.map((column) => (column.id === id ? { ...column, ...patch } : column)));
    }

    return (
        <div className="space-y-2">
            {showVariant && onTablePatch && (
                <div className="flex flex-wrap gap-3 rounded-md border border-dashed bg-muted/20 p-2">
                    <div className="space-y-1">
                        <Label className="text-[10px] uppercase tracking-wide text-muted-foreground">Style</Label>
                        <Select
                            value={variant}
                            onValueChange={(value) => onTablePatch({ variant: value as A4TableVariant })}
                        >
                            <SelectTrigger size="sm" className="h-8 w-[110px]">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="standard">Standard</SelectItem>
                                <SelectItem value="invoice">Invoice</SelectItem>
                                <SelectItem value="tax">Tax breakup</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <label className="flex items-center gap-1.5 text-xs">
                        <input
                            type="checkbox"
                            checked={compact}
                            onChange={(event) => onTablePatch({ compact: event.target.checked })}
                        />
                        Compact rows
                    </label>
                    <label className="flex items-center gap-1.5 text-xs">
                        <input
                            type="checkbox"
                            checked={showHeader}
                            onChange={(event) => onTablePatch({ showHeader: event.target.checked })}
                        />
                        Show header
                    </label>
                </div>
            )}

            <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-muted-foreground">Columns</span>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-6 px-2 text-xs"
                    onClick={() => onChange([...columns, createA4Column('variable')])}
                >
                    <Plus className="size-3" />
                    Column
                </Button>
            </div>

            {columns.map((column) => (
                <div key={column.id} className="space-y-1.5 rounded-lg border bg-muted/10 p-2">
                    <div className="flex flex-wrap items-center gap-1.5">
                        <Input
                            value={column.header}
                            onChange={(event) => updateColumn(column.id, { header: event.target.value })}
                            placeholder="Header"
                            className="h-8 w-24 shrink-0 text-xs"
                        />
                        <Select
                            value={column.mode}
                            onValueChange={(value) => updateColumn(column.id, { mode: value as A4ColumnMode })}
                        >
                            <SelectTrigger size="sm" className="h-8 w-[88px] shrink-0 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="variable">Field</SelectItem>
                                <SelectItem value="static">Static</SelectItem>
                                <SelectItem value="index">Index</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select
                            value={column.format}
                            onValueChange={(value) =>
                                updateColumn(column.id, { format: value as A4ColumnFormat })
                            }
                        >
                            <SelectTrigger size="sm" className="h-8 w-[88px] shrink-0 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="text">Text</SelectItem>
                                <SelectItem value="number">Number</SelectItem>
                                <SelectItem value="currency">Currency</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select
                            value={column.align}
                            onValueChange={(value) => updateColumn(column.id, { align: value as A4Align })}
                        >
                            <SelectTrigger size="sm" className="h-8 w-[76px] shrink-0 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="left">Left</SelectItem>
                                <SelectItem value="center">Center</SelectItem>
                                <SelectItem value="right">Right</SelectItem>
                            </SelectContent>
                        </Select>
                        <Input
                            value={column.width}
                            onChange={(event) => updateColumn(column.id, { width: event.target.value })}
                            placeholder="Width %"
                            className="h-8 w-16 shrink-0 text-xs"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="ml-auto size-7 shrink-0 text-muted-foreground hover:text-destructive"
                            onClick={() => onChange(columns.filter((item) => item.id !== column.id))}
                        >
                            <X className="size-3.5" />
                        </Button>
                    </div>

                    {column.mode === 'static' && (
                        <Input
                            value={column.staticValue}
                            onChange={(event) => updateColumn(column.id, { staticValue: event.target.value })}
                            placeholder="Static cell value"
                            className="h-8 text-xs"
                        />
                    )}

                    {column.mode === 'variable' && (
                        <div className="space-y-1">
                            <VariableDropField
                                id={`a4-col:${blockId}:${column.id}`}
                                value={column.path}
                                onChange={(path) => updateColumn(column.id, { path })}
                                placeholder="Primary field path"
                                className="w-full"
                            />
                            <TextDataEditor
                                tokens={column.tokens}
                                dropZoneId={`a4-col-token:${blockId}:${column.id}`}
                                onChange={(tokens) => updateColumn(column.id, { tokens })}
                            />
                            <p className="text-[10px] text-muted-foreground">
                                Use multi-line content for product name + attributes. Leave empty to use primary path only.
                            </p>
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}
