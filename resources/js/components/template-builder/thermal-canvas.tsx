import {
    DndContext,
    
    
    PointerSensor,
    useSensor,
    useSensors
} from '@dnd-kit/core';
import type {DragEndEvent, DragStartEvent} from '@dnd-kit/core';
import { SortableContext, arrayMove, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { AlignCenter, AlignLeft, AlignRight, Minus, Plus, Table, Type, Variable } from 'lucide-react';
import { useState } from 'react';
import { ColumnsEditor } from '@/components/template-builder/columns-editor';
import { DropFlashProvider, templateCollisionDetection, VariableDropField  } from '@/components/template-builder/dnd';
import type {VariablePayload} from '@/components/template-builder/dnd';
import {
    resolveActiveDragFromEvent,
    TemplateDragOverlay
    
} from '@/components/template-builder/drag-overlay';
import type {ActiveDragState} from '@/components/template-builder/drag-overlay';
import { SortableItem } from '@/components/template-builder/sortable-item';
import { ThermalPreview } from '@/components/template-builder/template-preview';
import { TextDataEditor } from '@/components/template-builder/text-data-editor';
import { VariableTree } from '@/components/template-builder/variable-tree';
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
import {
    createThermalElement
    
    
    
} from '@/lib/template-builder/thermal';
import type {ThermalAlign, ThermalElement, ThermalElementType} from '@/lib/template-builder/thermal';

const ELEMENT_LABELS: Record<ThermalElementType, string> = {
    text: 'Text',
    variable: 'Variable',
    divider: 'Divider',
    table: 'Table',
};

function AlignPicker({ value, onChange }: { value: ThermalAlign; onChange: (value: ThermalAlign) => void }) {
    const options: { value: ThermalAlign; icon: typeof AlignLeft }[] = [
        { value: 'left', icon: AlignLeft },
        { value: 'center', icon: AlignCenter },
        { value: 'right', icon: AlignRight },
    ];

    return (
        <div className="flex overflow-hidden rounded-md border">
            {options.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    onClick={() => onChange(option.value)}
                    className={
                        'flex size-8 items-center justify-center ' +
                        (value === option.value ? 'bg-primary text-primary-foreground' : 'hover:bg-muted')
                    }
                >
                    <option.icon className="size-4" />
                </button>
            ))}
        </div>
    );
}

function CommonRow({ element, onChange }: { element: ThermalElement; onChange: (next: ThermalElement) => void }) {
    if (element.type === 'divider') {
        return null;
    }

    return (
        <div className="flex flex-wrap items-end gap-3">
            <div className="space-y-1">
                <Label className="text-xs">Font</Label>
                <Input
                    type="number"
                    value={element.font}
                    onChange={(event) => onChange({ ...element, font: Number(event.target.value) || 0 })}
                    className="h-8 w-20"
                />
            </div>
            {element.type !== 'table' && (
                <div className="space-y-1">
                    <Label className="text-xs">Align</Label>
                    <AlignPicker value={element.align} onChange={(align) => onChange({ ...element, align })} />
                </div>
            )}
            <label className="flex h-8 items-center gap-2 text-xs">
                <Checkbox
                    checked={element.bold}
                    onCheckedChange={(checked) => onChange({ ...element, bold: checked === true })}
                />
                Bold
            </label>
            <div className="space-y-1">
                <Label className="text-xs">Key</Label>
                <Input
                    value={element.key}
                    onChange={(event) => onChange({ ...element, key: event.target.value })}
                    className="h-8 w-28"
                />
            </div>
        </div>
    );
}

function ThermalElementFields({
    element,
    onChange,
}: {
    element: ThermalElement;
    onChange: (next: ThermalElement) => void;
}) {
    return (
        <div className="space-y-3">
            <CommonRow element={element} onChange={onChange} />

            {element.type === 'text' && (
                <TextDataEditor
                    tokens={element.tokens}
                    dropZoneId={`token:${element.id}`}
                    onChange={(tokens) => onChange({ ...element, tokens })}
                />
            )}

            {element.type === 'variable' && (
                <div className="space-y-1">
                    <Label className="text-xs">Field path</Label>
                    <VariableDropField
                        id={`var-path:${element.id}`}
                        value={element.path}
                        onChange={(path) => onChange({ ...element, path })}
                        placeholder="Drop a variable here"
                    />
                </div>
            )}

            {element.type === 'table' && (
                <div className="space-y-3">
                    <div className="flex flex-wrap items-center gap-3">
                        <label className="flex items-center gap-2 text-xs">
                            <Checkbox
                                checked={element.loop}
                                onCheckedChange={(checked) => onChange({ ...element, loop: checked === true })}
                            />
                            Repeat for each list row
                        </label>
                        <div className="flex items-center gap-2">
                            <Label className="text-xs">Table type</Label>
                            <Select
                                value={element.tableType || 'none'}
                                onValueChange={(value) =>
                                    onChange({ ...element, tableType: value === 'none' ? '' : value })
                                }
                            >
                                <SelectTrigger size="sm" className="w-[120px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Default</SelectItem>
                                    <SelectItem value="saptham">saptham</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {element.loop && (
                        <div className="space-y-1">
                            <Label className="text-xs">List path</Label>
                            <VariableDropField
                                id={`table-array:${element.id}`}
                                value={element.path}
                                onChange={(path) => onChange({ ...element, path })}
                                placeholder="Drop a list variable here"
                            />
                        </div>
                    )}

                    <ColumnsEditor
                        columns={element.columns}
                        elementId={element.id}
                        onChange={(columns) => onChange({ ...element, columns })}
                    />
                </div>
            )}

            <div className="space-y-1">
                <Label className="text-xs text-muted-foreground">Conditional (optional)</Label>
                <Input
                    value={element.conditional}
                    onChange={(event) => onChange({ ...element, conditional: event.target.value })}
                    placeholder="e.g. totalcess > 0"
                    className="h-8"
                />
            </div>
        </div>
    );
}

export function ThermalCanvas({
    elements,
    setElements,
}: {
    elements: ThermalElement[];
    setElements: (updater: (previous: ThermalElement[]) => ThermalElement[]) => void;
}) {
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 4 } }));
    const [activeDrag, setActiveDrag] = useState<ActiveDragState | null>(null);
    const [dropFlashId, setDropFlashId] = useState<string | null>(null);

    function updateElement(id: string, next: ThermalElement) {
        setElements((previous) => previous.map((element) => (element.id === id ? next : element)));
    }

    function removeElement(id: string) {
        setElements((previous) => previous.filter((element) => element.id !== id));
    }

    function addElement(type: ThermalElementType) {
        setElements((previous) => [...previous, createThermalElement(type)]);
    }

    function handleVariableDrop(overId: string, payload: VariablePayload): boolean {
        const [target, id, extra] = overId.split(':');
        const validTargets = ['token', 'var-path', 'table-array', 'col-path'];

        if (!target || !id || !validTargets.includes(target)) {
            return false;
        }

        let applied = false;

        setElements((previous) =>
            previous.map((element) => {
                if (element.id !== id) {
                    return element;
                }

                if (target === 'token' && element.type === 'text') {
                    applied = true;

                    return {
                        ...element,
                        tokens: [...element.tokens, { kind: 'variable', path: payload.thermalPath }],
                    };
                }

                if (target === 'var-path' && element.type === 'variable') {
                    applied = true;

                    return { ...element, path: payload.thermalPath };
                }

                if (target === 'table-array' && element.type === 'table') {
                    applied = true;

                    return { ...element, path: payload.thermalPath };
                }

                if (target === 'col-path' && element.type === 'table') {
                    const columnIndex = Number(extra);
                    const path = element.loop ? payload.relativePath : payload.thermalPath;

                    applied = true;

                    return {
                        ...element,
                        columns: element.columns.map((column, index) =>
                            index === columnIndex ? { ...column, path, mode: 'variable' } : column,
                        ),
                    };
                }

                return element;
            }),
        );

        return applied;
    }

    function handleDragStart(event: DragStartEvent) {
        setActiveDrag(resolveActiveDragFromEvent(event, elements, ELEMENT_LABELS));
    }

    function handleDragEnd(event: DragEndEvent) {
        const { active, over } = event;

        if (over) {
            const payload = active.data.current as VariablePayload | undefined;

            if (payload?.kind === 'variable') {
                const applied = handleVariableDrop(String(over.id), payload);

                if (applied) {
                    setDropFlashId(String(over.id));
                    window.setTimeout(() => setDropFlashId(null), 650);
                }
            } else if (active.id !== over.id) {
                setElements((previous) => {
                    const oldIndex = previous.findIndex((element) => element.id === active.id);
                    const newIndex = previous.findIndex((element) => element.id === over.id);

                    if (oldIndex === -1 || newIndex === -1) {
                        return previous;
                    }

                    return arrayMove(previous, oldIndex, newIndex);
                });
            }
        }

        setActiveDrag(null);
    }

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={templateCollisionDetection}
            onDragStart={handleDragStart}
            onDragEnd={handleDragEnd}
            onDragCancel={() => setActiveDrag(null)}
        >
            <DropFlashProvider flashId={dropFlashId}>
            <div className="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)_minmax(280px,340px)]">
                <div className="lg:h-[calc(100vh-220px)]">
                    <VariableTree />
                </div>

                <div className="space-y-3">
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" size="sm" onClick={() => addElement('text')}>
                            <Type className="size-4" />
                            Text
                        </Button>
                        <Button type="button" variant="outline" size="sm" onClick={() => addElement('variable')}>
                            <Variable className="size-4" />
                            Variable
                        </Button>
                        <Button type="button" variant="outline" size="sm" onClick={() => addElement('table')}>
                            <Table className="size-4" />
                            Table
                        </Button>
                        <Button type="button" variant="outline" size="sm" onClick={() => addElement('divider')}>
                            <Minus className="size-4" />
                            Divider
                        </Button>
                    </div>

                    <SortableContext items={elements.map((element) => element.id)} strategy={verticalListSortingStrategy}>
                        <div className="space-y-2">
                            {elements.length === 0 && (
                                <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed p-8 text-center">
                                    <Plus className="size-5 text-muted-foreground" />
                                    <p className="text-sm text-muted-foreground">
                                        Add an element to start building the receipt.
                                    </p>
                                </div>
                            )}
                            {elements.map((element) => (
                                <SortableItem
                                    key={element.id}
                                    id={element.id}
                                    label={ELEMENT_LABELS[element.type]}
                                    badge={element.type === 'table' && element.loop ? 'loop' : undefined}
                                    onRemove={() => removeElement(element.id)}
                                >
                                    <ThermalElementFields
                                        element={element}
                                        onChange={(next) => updateElement(element.id, next)}
                                    />
                                </SortableItem>
                            ))}
                        </div>
                    </SortableContext>
                </div>

                <div className="min-w-0 lg:sticky lg:top-4 lg:h-fit lg:max-h-[calc(100vh-180px)] lg:overflow-y-auto">
                    <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                        <p className="border-b border-sidebar-border/70 bg-muted/40 px-3 py-2 text-xs font-medium text-muted-foreground dark:border-sidebar-border">
                            Live preview
                        </p>
                        <ThermalPreview elements={elements} />
                    </div>
                </div>
            </div>
            <TemplateDragOverlay active={activeDrag} />
            </DropFlashProvider>
        </DndContext>
    );
}
