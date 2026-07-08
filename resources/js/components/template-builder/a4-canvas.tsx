import {
    DndContext,
    type DragEndEvent,
    type DragStartEvent,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import { SortableContext, arrayMove, verticalListSortingStrategy } from '@dnd-kit/sortable';
import {
    Columns3,
    FileText,
    Heading,
    ImageIcon,
    LayoutTemplate,
    Minus,
    Rows3,
    SeparatorHorizontal,
    Table,
    Type,
} from 'lucide-react';
import { useMemo, useState, type Dispatch, type SetStateAction } from 'react';
import { toast } from 'sonner';
import { A4BlockFields } from '@/components/template-builder/a4-block-fields';
import { A4PageSettingsPanel } from '@/components/template-builder/a4-page-settings-panel';
import {
    resolveActiveDragFromEvent,
    TemplateDragOverlay,
    type ActiveDragState,
} from '@/components/template-builder/drag-overlay';
import { DropFlashProvider, templateCollisionDetection, type VariablePayload } from '@/components/template-builder/dnd';
import { SortableItem } from '@/components/template-builder/sortable-item';
import { A4Preview } from '@/components/template-builder/template-preview';
import { VariableTree } from '@/components/template-builder/variable-tree';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { createA4Block, type A4Block, type A4BlockType, type A4PageBackground, type A4PageSettings } from '@/lib/template-builder/a4';
import { renderImportedEjsPrintDocument } from '@/lib/template-builder/a4-ejs-preview';
import { findBuiltinA4Template } from '@/lib/template-builder/a4-presets';
import { cloneA4Template } from '@/lib/template-builder/a4-template-storage';

const BLOCK_LABELS: Record<A4BlockType, string> = {
    heading: 'Heading',
    text: 'Text',
    keyValue: 'Label + value',
    divider: 'Divider',
    table: 'Table',
    image: 'Image',
    headerBand: 'Header band',
    summaryPanel: 'Summary panel',
    terms: 'Terms & conditions',
    spacer: 'Spacer',
};

export function A4Canvas({
    blocks,
    setBlocks,
    pageBackground,
    setPageBackground,
    pageSettings,
    setPageSettings,
    ejsPreviewSource,
    hasProductionPreview,
}: {
    blocks: A4Block[];
    setBlocks: Dispatch<SetStateAction<A4Block[]>>;
    pageBackground: A4PageBackground;
    setPageBackground: (next: A4PageBackground) => void;
    pageSettings: A4PageSettings;
    setPageSettings: (next: A4PageSettings) => void;
    ejsPreviewSource?: string;
    hasProductionPreview?: boolean;
}) {
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 4 } }));
    const [activeDrag, setActiveDrag] = useState<ActiveDragState | null>(null);
    const [dropFlashId, setDropFlashId] = useState<string | null>(null);
    const [previewVariant, setPreviewVariant] = useState<'live' | 'imported'>('live');
    const styledPreviewDocument = useMemo(
        () => (ejsPreviewSource?.trim() ? renderImportedEjsPrintDocument(ejsPreviewSource) : ''),
        [ejsPreviewSource],
    );
    const useStyledPreview = Boolean(hasProductionPreview && styledPreviewDocument);

    function handleBlockChange(id: string, next: A4Block) {
        if (!useStyledPreview) {
            setPreviewVariant('live');
        }

        updateBlock(id, next);
    }

    function handlePageSettingsChange(next: A4PageSettings) {
        if (!useStyledPreview) {
            setPreviewVariant('live');
        }

        setPageSettings(next);
    }

    function handlePageBackgroundChange(next: A4PageBackground) {
        if (!useStyledPreview) {
            setPreviewVariant('live');
        }

        setPageBackground(next);
    }

    function updateBlock(id: string, next: A4Block) {
        setBlocks((previous) => previous.map((block) => (block.id === id ? next : block)));
    }

    function addBlock(type: A4BlockType) {
        setPreviewVariant('live');
        setBlocks((previous) => [...previous, createA4Block(type)]);
    }

    function loadProfessionalTemplate() {
        const template = findBuiltinA4Template('tpl-tax-pro');

        if (!template) {
            toast.error('Professional template not found.');

            return;
        }

        const cloned = cloneA4Template(template);

        setPreviewVariant('live');
        setBlocks(cloned.blocks);
        setPageBackground(cloned.pageBackground);
        setPageSettings(cloned.pageSettings);
        toast.success('Professional Tax Invoice template loaded.');
    }

    function handleVariableDrop(overId: string, payload: VariablePayload): boolean {
        if (overId === 'a4-page-bg-path') {
            setPreviewVariant('live');
            setPageBackground({
                ...pageBackground,
                sourceType: 'variable',
                path: payload.a4Path,
            });

            return true;
        }

        const parts = overId.split(':');
        const target = parts[0];
        let applied = false;

        if (target === 'a4-header-left' || target === 'a4-header-center' || target === 'a4-header-right') {
            const blockId = parts[1];
            const slotKey = target.replace('a4-header-', '') as 'left' | 'center' | 'right';

            setBlocks((previous) =>
                previous.map((block) => {
                    if (block.id !== blockId || block.type !== 'headerBand') {
                        return block;
                    }

                    applied = true;

                    return {
                        ...block,
                        [slotKey]: {
                            ...block[slotKey],
                            tokens: [...block[slotKey].tokens, { kind: 'variable', path: payload.a4Path }],
                        },
                    };
                }),
            );

            return applied;
        }

        if (target === 'a4-header-img') {
            const blockId = parts[1];
            const slotKey = parts[2] as 'left' | 'center' | 'right';

            setBlocks((previous) =>
                previous.map((block) => {
                    if (block.id !== blockId || block.type !== 'headerBand') {
                        return block;
                    }

                    applied = true;

                    return {
                        ...block,
                        [slotKey]: { ...block[slotKey], path: payload.a4Path, sourceType: 'variable' },
                    };
                }),
            );

            return applied;
        }

        const blockId = parts[1];
        const extra = parts[2];

        setBlocks((previous) =>
            previous.map((block) => {
                if (block.id !== blockId) {
                    return block;
                }

                if (target === 'a4-img' && block.type === 'image') {
                    applied = true;

                    return { ...block, sourceType: 'variable', path: payload.a4Path };
                }

                if (target === 'a4-token' && (block.type === 'heading' || block.type === 'text' || block.type === 'terms')) {
                    applied = true;

                    return { ...block, tokens: [...block.tokens, { kind: 'variable', path: payload.a4Path }] };
                }

                if (target === 'a4-kv' && block.type === 'keyValue') {
                    applied = true;

                    return { ...block, path: payload.a4Path };
                }

                if (target === 'a4-array' && block.type === 'table') {
                    applied = true;

                    return { ...block, arrayPath: payload.a4Path };
                }

                if (target === 'a4-col' && block.type === 'table') {
                    applied = true;

                    return {
                        ...block,
                        columns: block.columns.map((column) =>
                            column.id === extra ? { ...column, path: payload.relativePath } : column,
                        ),
                    };
                }

                if (target === 'a4-col-token' && block.type === 'table') {
                    applied = true;

                    return {
                        ...block,
                        columns: block.columns.map((column) =>
                            column.id === extra
                                ? {
                                      ...column,
                                      tokens: [...column.tokens, { kind: 'variable', path: payload.relativePath }],
                                  }
                                : column,
                        ),
                    };
                }

                if (target === 'a4-summary-left' && block.type === 'summaryPanel') {
                    applied = true;

                    return {
                        ...block,
                        leftTokens: [...block.leftTokens, { kind: 'variable', path: payload.a4Path }],
                    };
                }

                if (target === 'a4-summary-line' && block.type === 'summaryPanel') {
                    applied = true;

                    return {
                        ...block,
                        rightLines: block.rightLines.map((line) =>
                            line.id === extra ? { ...line, path: payload.a4Path } : line,
                        ),
                    };
                }

                return block;
            }),
        );

        return applied;
    }

    function handleDragStart(event: DragStartEvent) {
        setActiveDrag(resolveActiveDragFromEvent(event, blocks, BLOCK_LABELS));
    }

    function handleDragEnd(event: DragEndEvent) {
        const { active, over } = event;

        if (over) {
            const payload = active.data.current as VariablePayload | undefined;

            if (payload?.kind === 'variable') {
                const applied = handleVariableDrop(String(over.id), payload);

                if (applied) {
                    setPreviewVariant('live');
                    setDropFlashId(String(over.id));
                    window.setTimeout(() => setDropFlashId(null), 650);
                }
            } else if (active.id !== over.id) {
                setPreviewVariant('live');
                setBlocks((previous) => {
                    const oldIndex = previous.findIndex((block) => block.id === active.id);
                    const newIndex = previous.findIndex((block) => block.id === over.id);

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
                <div className="grid h-[calc(100vh-200px)] min-h-[520px] gap-4 overflow-hidden lg:grid-cols-[260px_minmax(0,1fr)_minmax(520px,1fr)]">
                    <div className="min-h-0 overflow-hidden">
                        <VariableTree />
                    </div>

                    <div className="min-h-0 space-y-3 overflow-y-auto pr-1 [-ms-overflow-style:none] [scrollbar-width:thin] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-border">
                        <A4PageSettingsPanel
                            pageSettings={pageSettings}
                            onPageSettingsChange={handlePageSettingsChange}
                            pageBackground={pageBackground}
                            onPageBackgroundChange={handlePageBackgroundChange}
                        />

                        <div className="flex flex-wrap items-center gap-2">
                            <Button type="button" size="sm" onClick={loadProfessionalTemplate}>
                                <LayoutTemplate className="size-4" />
                                Load professional template
                            </Button>
                            <Button type="button" variant="outline" size="sm" onClick={() => addBlock('headerBand')}>
                                <Columns3 className="size-4" />
                                Header
                            </Button>
                            <Button type="button" variant="outline" size="sm" onClick={() => addBlock('heading')}>
                                <Heading className="size-4" />
                                Heading
                            </Button>
                            <Button type="button" variant="outline" size="sm" onClick={() => addBlock('text')}>
                                <Type className="size-4" />
                                Text
                            </Button>
                            <Button type="button" variant="outline" size="sm" onClick={() => addBlock('image')}>
                                <ImageIcon className="size-4" />
                                Image
                            </Button>
                            <Button type="button" variant="outline" size="sm" onClick={() => addBlock('keyValue')}>
                                <Rows3 className="size-4" />
                                Label + value
                            </Button>
                            <Button type="button" variant="outline" size="sm" onClick={() => addBlock('table')}>
                                <Table className="size-4" />
                                Table
                            </Button>
                            <Button type="button" variant="outline" size="sm" onClick={() => addBlock('summaryPanel')}>
                                <FileText className="size-4" />
                                Summary
                            </Button>
                            <Button type="button" variant="outline" size="sm" onClick={() => addBlock('terms')}>
                                <FileText className="size-4" />
                                Terms
                            </Button>
                            <Button type="button" variant="outline" size="sm" onClick={() => addBlock('divider')}>
                                <Minus className="size-4" />
                                Divider
                            </Button>
                            <Button type="button" variant="outline" size="sm" onClick={() => addBlock('spacer')}>
                                <SeparatorHorizontal className="size-4" />
                                Spacer
                            </Button>
                        </div>

                        <SortableContext items={blocks.map((block) => block.id)} strategy={verticalListSortingStrategy}>
                            <div className="space-y-2">
                                {blocks.length === 0 && (
                                    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed p-8 text-center">
                                        <LayoutTemplate className="size-8 text-muted-foreground/50" />
                                        <div>
                                            <p className="text-sm font-medium">Start with a professional template</p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Load the Tax Invoice layout or add blocks manually.
                                            </p>
                                        </div>
                                        <Button type="button" size="sm" onClick={loadProfessionalTemplate}>
                                            Load professional template
                                        </Button>
                                    </div>
                                )}
                                {blocks.map((block) => (
                                    <SortableItem
                                        key={block.id}
                                        id={block.id}
                                        label={BLOCK_LABELS[block.type]}
                                        badge={
                                            block.type === 'table'
                                                ? block.variant === 'invoice'
                                                    ? 'invoice'
                                                    : block.variant === 'tax'
                                                      ? 'tax'
                                                      : undefined
                                                : undefined
                                        }
                                        onRemove={() =>
                                            setBlocks((previous) => previous.filter((item) => item.id !== block.id))
                                        }
                                    >
                                        <A4BlockFields block={block} onChange={(next) => handleBlockChange(block.id, next)} />
                                    </SortableItem>
                                ))}
                            </div>
                        </SortableContext>
                    </div>

                    <div className="flex min-h-0 min-w-0 flex-col overflow-hidden">
                        <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border bg-muted/30 shadow-sm">
                            <div className="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b bg-muted/50 px-3 py-2">
                                <p className="text-xs font-medium text-muted-foreground">
                                    Live preview — A4
                                    {useStyledPreview ? ' (imported design)' : previewVariant === 'imported' ? ' (imported design)' : ' (editing)'}
                                </p>
                                {styledPreviewDocument && !useStyledPreview && (
                                    <ToggleGroup
                                        type="single"
                                        variant="outline"
                                        size="sm"
                                        value={previewVariant}
                                        onValueChange={(value) => value && setPreviewVariant(value as 'live' | 'imported')}
                                    >
                                        <ToggleGroupItem value="live" className="h-7 px-2 text-[10px]">
                                            Live edit
                                        </ToggleGroupItem>
                                        <ToggleGroupItem value="imported" className="h-7 px-2 text-[10px]">
                                            Imported
                                        </ToggleGroupItem>
                                    </ToggleGroup>
                                )}
                            </div>
                            <div className="min-h-0 flex-1 overflow-auto bg-muted/20 [-ms-overflow-style:none] [scrollbar-width:thin] [&::-webkit-scrollbar]:h-1.5 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-border">
                                {useStyledPreview || (previewVariant === 'imported' && styledPreviewDocument) ? (
                                    <iframe
                                        title="Imported EJS preview"
                                        srcDoc={styledPreviewDocument}
                                        className="block min-h-full min-w-[794px] w-full bg-white"
                                        style={{ height: '1123px' }}
                                        sandbox="allow-same-origin"
                                    />
                                ) : (
                                    <div className="p-3">
                                        <A4Preview
                                            blocks={blocks}
                                            pageBackground={pageBackground}
                                            pageSettings={pageSettings}
                                        />
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
                <TemplateDragOverlay active={activeDrag} />
            </DropFlashProvider>
        </DndContext>
    );
}
