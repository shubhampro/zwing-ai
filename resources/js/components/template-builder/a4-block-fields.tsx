import { Plus, X } from 'lucide-react';
import { A4ColumnsEditor } from '@/components/template-builder/a4-columns-editor';
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
    createSummaryLine,
    type A4Align,
    type A4Block,
    type A4HeaderSlot,
    type A4ImageSourceType,
    type A4TextSize,
} from '@/lib/template-builder/a4';

function AlignSelect({ value, onChange }: { value: A4Align; onChange: (value: A4Align) => void }) {
    return (
        <Select value={value} onValueChange={(next) => onChange(next as A4Align)}>
            <SelectTrigger size="sm" className="w-[90px]">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="left">Left</SelectItem>
                <SelectItem value="center">Center</SelectItem>
                <SelectItem value="right">Right</SelectItem>
            </SelectContent>
        </Select>
    );
}

function HeaderSlotEditor({
    label,
    slot,
    blockId,
    slotKey,
    onChange,
}: {
    label: string;
    slot: A4HeaderSlot;
    blockId: string;
    slotKey: 'left' | 'center' | 'right';
    onChange: (slot: A4HeaderSlot) => void;
}) {
    return (
        <div className="space-y-2 rounded-md border p-2">
            <div className="flex items-center justify-between">
                <span className="text-xs font-semibold">{label}</span>
                <Select
                    value={slot.slotType}
                    onValueChange={(value) =>
                        onChange({
                            ...slot,
                            slotType: value as 'text' | 'image',
                        })
                    }
                >
                    <SelectTrigger size="sm" className="h-7 w-[90px] text-xs">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="text">Text</SelectItem>
                        <SelectItem value="image">Logo</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            {slot.slotType === 'text' ? (
                <TextDataEditor
                    tokens={slot.tokens}
                    dropZoneId={`a4-header-${slotKey}:${blockId}`}
                    onChange={(tokens) => onChange({ ...slot, tokens })}
                />
            ) : (
                <div className="space-y-2">
                    <VariableDropField
                        id={`a4-header-img:${blockId}:${slotKey}`}
                        value={slot.path}
                        onChange={(path) => onChange({ ...slot, path, sourceType: 'variable' })}
                        placeholder="Logo field"
                    />
                    <div className="grid grid-cols-2 gap-2">
                        <Input
                            value={slot.width}
                            onChange={(event) => onChange({ ...slot, width: event.target.value })}
                            placeholder="Width"
                            className="h-8 text-xs"
                        />
                        <Input
                            value={slot.maxHeight}
                            onChange={(event) => onChange({ ...slot, maxHeight: event.target.value })}
                            placeholder="Max height"
                            className="h-8 text-xs"
                        />
                    </div>
                </div>
            )}
        </div>
    );
}

export function A4BlockFields({ block, onChange }: { block: A4Block; onChange: (next: A4Block) => void }) {
    if (block.type === 'divider') {
        return (
            <div className="flex items-center gap-2">
                <Label className="text-xs">Line weight</Label>
                <Select
                    value={block.weight}
                    onValueChange={(value) => onChange({ ...block, weight: value as 'thin' | 'medium' | 'bold' })}
                >
                    <SelectTrigger size="sm" className="w-[100px]">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="thin">Thin</SelectItem>
                        <SelectItem value="medium">Medium</SelectItem>
                        <SelectItem value="bold">Bold</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        );
    }

    if (block.type === 'spacer') {
        return (
            <div className="flex items-center gap-2">
                <Label className="text-xs">Height (px)</Label>
                <Input
                    type="number"
                    value={block.height}
                    onChange={(event) => onChange({ ...block, height: Number(event.target.value) || 8 })}
                    className="h-8 w-24"
                />
            </div>
        );
    }

    if (block.type === 'heading' || block.type === 'text') {
        return (
            <div className="space-y-3">
                <div className="flex flex-wrap items-center gap-3">
                    <div className="flex items-center gap-2">
                        <Label className="text-xs">Align</Label>
                        <AlignSelect value={block.align} onChange={(align) => onChange({ ...block, align })} />
                    </div>
                    {block.type === 'heading' ? (
                        <div className="flex items-center gap-2">
                            <Label className="text-xs">Size</Label>
                            <Select
                                value={block.size}
                                onValueChange={(value) =>
                                    onChange({ ...block, size: value as 'lg' | 'md' | 'sm' })
                                }
                            >
                                <SelectTrigger size="sm" className="w-[90px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="lg">Large</SelectItem>
                                    <SelectItem value="md">Medium</SelectItem>
                                    <SelectItem value="sm">Small</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    ) : (
                        <div className="flex items-center gap-2">
                            <Label className="text-xs">Size</Label>
                            <Select
                                value={block.size}
                                onValueChange={(value) => onChange({ ...block, size: value as A4TextSize })}
                            >
                                <SelectTrigger size="sm" className="w-[90px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="xs">Extra small</SelectItem>
                                    <SelectItem value="sm">Small</SelectItem>
                                    <SelectItem value="md">Medium</SelectItem>
                                    <SelectItem value="lg">Large</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                    <label className="flex items-center gap-1.5 text-xs">
                        <input
                            type="checkbox"
                            checked={block.bold}
                            onChange={(event) => onChange({ ...block, bold: event.target.checked })}
                        />
                        Bold
                    </label>
                    <label className="flex items-center gap-1.5 text-xs">
                        <input
                            type="checkbox"
                            checked={block.uppercase}
                            onChange={(event) => onChange({ ...block, uppercase: event.target.checked })}
                        />
                        Uppercase
                    </label>
                </div>
                <TextDataEditor
                    tokens={block.tokens}
                    dropZoneId={`a4-token:${block.id}`}
                    onChange={(tokens) => onChange({ ...block, tokens })}
                />
            </div>
        );
    }

    if (block.type === 'keyValue') {
        return (
            <div className="space-y-3">
                <div className="flex flex-wrap items-end gap-3">
                    <div className="space-y-1">
                        <Label className="text-xs">Label</Label>
                        <Input
                            value={block.label}
                            onChange={(event) => onChange({ ...block, label: event.target.value })}
                            className="h-8 w-40"
                        />
                    </div>
                    <div className="min-w-[180px] flex-1 space-y-1">
                        <Label className="text-xs">Value field</Label>
                        <VariableDropField
                            id={`a4-kv:${block.id}`}
                            value={block.path}
                            onChange={(path) => onChange({ ...block, path })}
                            placeholder="Drop a variable here"
                        />
                    </div>
                </div>
                <div className="flex gap-4 text-xs">
                    <label className="flex items-center gap-1.5">
                        <input
                            type="checkbox"
                            checked={block.boldLabel}
                            onChange={(event) => onChange({ ...block, boldLabel: event.target.checked })}
                        />
                        Bold label
                    </label>
                    <label className="flex items-center gap-1.5">
                        <input
                            type="checkbox"
                            checked={block.boldValue}
                            onChange={(event) => onChange({ ...block, boldValue: event.target.checked })}
                        />
                        Bold value
                    </label>
                </div>
            </div>
        );
    }

    if (block.type === 'image') {
        return (
            <div className="space-y-3">
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1">
                        <Label className="text-xs">Source</Label>
                        <Select
                            value={block.sourceType}
                            onValueChange={(value) =>
                                onChange({ ...block, sourceType: value as A4ImageSourceType })
                            }
                        >
                            <SelectTrigger size="sm">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="variable">Data field (URL)</SelectItem>
                                <SelectItem value="url">Custom URL</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex items-center gap-2">
                        <Label className="text-xs">Align</Label>
                        <AlignSelect value={block.align} onChange={(align) => onChange({ ...block, align })} />
                    </div>
                </div>
                {block.sourceType === 'variable' ? (
                    <VariableDropField
                        id={`a4-img:${block.id}`}
                        value={block.path}
                        onChange={(path) => onChange({ ...block, path, sourceType: 'variable' })}
                        placeholder="Drop logo / image field"
                    />
                ) : (
                    <Input
                        value={block.url}
                        onChange={(event) => onChange({ ...block, url: event.target.value })}
                        placeholder="https://example.com/logo.png"
                        className="h-8"
                    />
                )}
                <div className="grid gap-3 sm:grid-cols-3">
                    <Input
                        value={block.width}
                        onChange={(event) => onChange({ ...block, width: event.target.value })}
                        placeholder="Width"
                        className="h-8"
                    />
                    <Input
                        value={block.maxHeight}
                        onChange={(event) => onChange({ ...block, maxHeight: event.target.value })}
                        placeholder="Max height"
                        className="h-8"
                    />
                    <Input
                        value={block.alt}
                        onChange={(event) => onChange({ ...block, alt: event.target.value })}
                        placeholder="Alt text"
                        className="h-8"
                    />
                </div>
            </div>
        );
    }

    if (block.type === 'headerBand') {
        return (
            <div className="space-y-3">
                <label className="flex items-center gap-2 text-xs">
                    <input
                        type="checkbox"
                        checked={block.showBorder}
                        onChange={(event) => onChange({ ...block, showBorder: event.target.checked })}
                    />
                    Bottom border line
                </label>
                <HeaderSlotEditor
                    label="Left — company / customer"
                    slot={block.left}
                    blockId={block.id}
                    slotKey="left"
                    onChange={(left) => onChange({ ...block, left })}
                />
                <HeaderSlotEditor
                    label="Center — logo"
                    slot={block.center}
                    blockId={block.id}
                    slotKey="center"
                    onChange={(center) => onChange({ ...block, center })}
                />
                <HeaderSlotEditor
                    label="Right — invoice meta"
                    slot={block.right}
                    blockId={block.id}
                    slotKey="right"
                    onChange={(right) => onChange({ ...block, right })}
                />
            </div>
        );
    }

    if (block.type === 'summaryPanel') {
        return (
            <div className="space-y-3">
                <div className="space-y-1">
                    <Label className="text-xs">Left label</Label>
                    <Input
                        value={block.leftLabel}
                        onChange={(event) => onChange({ ...block, leftLabel: event.target.value })}
                        className="h-8 w-40"
                    />
                </div>
                <TextDataEditor
                    tokens={block.leftTokens}
                    dropZoneId={`a4-summary-left:${block.id}`}
                    onChange={(leftTokens) => onChange({ ...block, leftTokens })}
                />
                <div className="space-y-1.5">
                    <div className="flex items-center justify-between">
                        <Label className="text-xs">Totals (right)</Label>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-6 px-2 text-xs"
                            onClick={() =>
                                onChange({
                                    ...block,
                                    rightLines: [...block.rightLines, createSummaryLine('Line')],
                                })
                            }
                        >
                            <Plus className="size-3" />
                            Line
                        </Button>
                    </div>
                    {block.rightLines.map((line) => (
                        <div key={line.id} className="flex items-center gap-1.5 rounded border p-1.5">
                            <Input
                                value={line.label}
                                onChange={(event) =>
                                    onChange({
                                        ...block,
                                        rightLines: block.rightLines.map((item) =>
                                            item.id === line.id ? { ...item, label: event.target.value } : item,
                                        ),
                                    })
                                }
                                placeholder="Label"
                                className="h-8 w-28 shrink-0 text-xs"
                            />
                            <VariableDropField
                                id={`a4-summary-line:${block.id}:${line.id}`}
                                value={line.path}
                                onChange={(path) =>
                                    onChange({
                                        ...block,
                                        rightLines: block.rightLines.map((item) =>
                                            item.id === line.id ? { ...item, path } : item,
                                        ),
                                    })
                                }
                                placeholder="Amount field"
                                className="flex-1"
                            />
                            <label className="flex shrink-0 items-center gap-1 text-[10px]">
                                <input
                                    type="checkbox"
                                    checked={line.bold}
                                    onChange={(event) =>
                                        onChange({
                                            ...block,
                                            rightLines: block.rightLines.map((item) =>
                                                item.id === line.id
                                                    ? { ...item, bold: event.target.checked }
                                                    : item,
                                            ),
                                        })
                                    }
                                />
                                Bold
                            </label>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7 shrink-0"
                                onClick={() =>
                                    onChange({
                                        ...block,
                                        rightLines: block.rightLines.filter((item) => item.id !== line.id),
                                    })
                                }
                            >
                                <X className="size-3.5" />
                            </Button>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    if (block.type === 'terms') {
        return (
            <div className="space-y-3">
                <div className="flex flex-wrap items-center gap-3">
                    <div className="space-y-1">
                        <Label className="text-xs">Title</Label>
                        <Input
                            value={block.title}
                            onChange={(event) => onChange({ ...block, title: event.target.value })}
                            className="h-8 w-48"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-xs">Font size</Label>
                        <Select
                            value={block.size}
                            onValueChange={(value) => onChange({ ...block, size: value as 'xs' | 'sm' })}
                        >
                            <SelectTrigger size="sm" className="w-[100px]">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="xs">Fine print</SelectItem>
                                <SelectItem value="sm">Small</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <TextDataEditor
                    tokens={block.tokens}
                    dropZoneId={`a4-terms:${block.id}`}
                    onChange={(tokens) => onChange({ ...block, tokens })}
                />
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <VariableDropField
                id={`a4-array:${block.id}`}
                value={block.arrayPath}
                onChange={(arrayPath) => onChange({ ...block, arrayPath })}
                placeholder="Drop a list variable here"
            />
            <A4ColumnsEditor
                columns={block.columns}
                blockId={block.id}
                onChange={(columns) => onChange({ ...block, columns })}
                showVariant
                variant={block.variant}
                compact={block.compact}
                showHeader={block.showHeader}
                onTablePatch={(patch) => onChange({ ...block, ...patch })}
            />
        </div>
    );
}
