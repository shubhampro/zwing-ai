import {
    closestCenter,
    pointerWithin,
    rectIntersection,
    useDndContext,
    useDraggable,
    useDroppable,
    type CollisionDetection,
} from '@dnd-kit/core';
import { Braces, GripVertical, Plus } from 'lucide-react';
import { createContext, type ReactNode, useContext } from 'react';
import { cn } from '@/lib/utils';

export type VariablePayload = {
    kind: 'variable';
    label: string;
    thermalPath: string;
    a4Path: string;
    relativePath: string;
    isArray: boolean;
};

const DROP_ZONE_MARKERS = [
    'token:',
    'var-path:',
    'table-array:',
    'col-path:',
    'a4-token:',
    'a4-kv:',
    'a4-array:',
    'a4-col:',
    'a4-col-token:',
    'a4-img:',
    'a4-page-bg-path',
    'a4-header-left:',
    'a4-header-center:',
    'a4-header-right:',
    'a4-header-img:',
    'a4-summary-left:',
    'a4-summary-line:',
    'a4-terms:',
] as const;

export function isTemplateDropZoneId(id: string): boolean {
    return DROP_ZONE_MARKERS.some((marker) => id.startsWith(marker) || id === marker);
}

function preferDropZoneCollisions<T extends { id: string | number }>(collisions: T[]): T[] {
    const dropZoneHit = collisions.find((collision) => isTemplateDropZoneId(String(collision.id)));

    if (dropZoneHit) {
        return [dropZoneHit];
    }

    return collisions;
}

/** Prioritises nested drop targets over sortable block containers. */
export const templateCollisionDetection: CollisionDetection = (args) => {
    const pointerCollisions = pointerWithin(args);

    if (pointerCollisions.length > 0) {
        return preferDropZoneCollisions(pointerCollisions);
    }

    const rectCollisions = rectIntersection(args);

    if (rectCollisions.length > 0) {
        return preferDropZoneCollisions(rectCollisions);
    }

    return closestCenter(args);
};

const DropFlashContext = createContext<string | null>(null);

export function DropFlashProvider({
    flashId,
    children,
}: {
    flashId: string | null;
    children: ReactNode;
}) {
    return <DropFlashContext.Provider value={flashId}>{children}</DropFlashContext.Provider>;
}

export function DraggableVariable({
    id,
    payload,
    children,
    className,
}: {
    id: string;
    payload: VariablePayload;
    children: ReactNode;
    className?: string;
}) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
        id,
        data: payload,
    });

    return (
        <button
            ref={setNodeRef}
            type="button"
            {...listeners}
            {...attributes}
            className={cn(
                'cursor-grab touch-none rounded-md transition-all duration-200 ease-out active:cursor-grabbing',
                isDragging
                    ? 'scale-[0.96] border border-dashed border-primary/40 bg-primary/5 opacity-35 shadow-none'
                    : 'hover:bg-muted hover:shadow-sm active:scale-[0.98]',
                className,
            )}
        >
            {children}
        </button>
    );
}

export function DropZone({
    id,
    children,
    className,
    compact = false,
}: {
    id: string;
    children: ReactNode;
    className?: string;
    compact?: boolean;
}) {
    const { active } = useDndContext();
    const flashId = useContext(DropFlashContext);
    const { setNodeRef, isOver } = useDroppable({ id });

    const isDraggingVariable = (active?.data.current as VariablePayload | undefined)?.kind === 'variable';
    const isFlashing = flashId === id;

    return (
        <div
            ref={setNodeRef}
            className={cn(
                'relative rounded-lg border-2 border-dashed transition-all duration-200 ease-out',
                compact ? 'min-h-[36px]' : 'min-h-[44px]',
                isDraggingVariable && !isOver && !isFlashing && 'animate-drop-zone-pulse border-primary/35 bg-primary/[0.04]',
                isOver &&
                    isDraggingVariable &&
                    'scale-[1.02] border-primary border-solid bg-primary/12 shadow-lg ring-2 ring-primary/30',
                isFlashing && 'animate-drop-success border-emerald-500/70 bg-emerald-500/10 ring-2 ring-emerald-500/35',
                !isDraggingVariable && !isOver && !isFlashing && 'border-border/70 bg-muted/20',
                className,
            )}
        >
            {isOver && isDraggingVariable && (
                <span className="pointer-events-none absolute -top-2.5 left-2 z-10 rounded-full bg-primary px-2 py-0.5 text-[10px] font-semibold text-primary-foreground shadow-md animate-drop-in">
                    Release to drop
                </span>
            )}
            {children}
        </div>
    );
}

export function VariableDropField({
    id,
    value,
    onChange,
    placeholder = 'Drop a field here',
    className,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    className?: string;
}) {
    const displayName = value.split('.').pop() ?? value;

    return (
        <DropZone id={id} compact className={cn('flex items-center gap-1.5 p-1', className)}>
            {value ? (
                <span className="inline-flex animate-drop-in items-center gap-1.5 rounded-md border border-primary/25 bg-primary/10 px-2 py-1 text-xs font-medium text-primary">
                    <Braces className="size-3 shrink-0" />
                    <span className="max-w-[140px] truncate" title={value}>
                        {displayName}
                    </span>
                    <button
                        type="button"
                        onClick={() => onChange('')}
                        className="rounded-sm text-primary/70 hover:bg-primary/15 hover:text-destructive"
                        aria-label="Clear field"
                    >
                        ×
                    </button>
                </span>
            ) : (
                <span className="flex flex-1 items-center gap-1.5 px-1.5 text-xs text-muted-foreground">
                    <GripVertical className="size-3.5 opacity-40" />
                    {placeholder}
                </span>
            )}
            <input
                type="text"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={value ? '' : 'or type path'}
                className={cn(
                    'min-w-0 flex-1 bg-transparent text-xs outline-none placeholder:text-muted-foreground/50',
                    value ? 'max-w-[80px] opacity-60' : '',
                )}
            />
        </DropZone>
    );
}

export function ContentDropZone({
    id,
    children,
    isEmpty,
}: {
    id: string;
    children: ReactNode;
    isEmpty: boolean;
}) {
    return (
        <DropZone
            id={id}
            className={cn(
                'flex flex-wrap items-start gap-1.5 p-2',
                isEmpty && 'justify-center',
            )}
        >
            {isEmpty && (
                <span className="flex items-center gap-1.5 px-1 text-xs text-muted-foreground">
                    <Plus className="size-3.5 opacity-50" />
                    Drop a field here or add text
                </span>
            )}
            {children}
        </DropZone>
    );
}
