import {
    DragOverlay,
    defaultDropAnimationSideEffects,
} from '@dnd-kit/core';
import type { DragStartEvent, DropAnimation } from '@dnd-kit/core';
import { GripVertical, List, Type } from 'lucide-react';
import type { VariablePayload } from '@/components/template-builder/dnd';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type ActiveDragState =
    | { kind: 'variable'; payload: VariablePayload }
    | { kind: 'block'; label: string; badge?: string };

const dropAnimation: DropAnimation = {
    duration: 260,
    easing: 'cubic-bezier(0.18, 0.67, 0.6, 1.22)',
    sideEffects: defaultDropAnimationSideEffects({
        styles: {
            active: {
                opacity: '0.4',
            },
        },
    }),
};

const variableDropAnimation: DropAnimation = {
    duration: 180,
    easing: 'ease-out',
    keyframes({ transform }) {
        return [
            { opacity: 1, transform: transform.initial },
            { opacity: 0, transform: `${transform.initial} scale(0.88)` },
        ];
    },
    sideEffects: defaultDropAnimationSideEffects({
        styles: {
            active: {
                opacity: '0.5',
            },
        },
    }),
};

function VariableLiftCard({ payload }: { payload: VariablePayload }) {
    const Icon = payload.isArray ? List : Type;

    return (
        <div
            className={cn(
                'flex min-w-[180px] max-w-[240px] items-center gap-2 rounded-lg border border-primary/30',
                'bg-card px-3 py-2 shadow-2xl ring-2 ring-primary/25 animate-drag-lift',
            )}
        >
            <div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                <Icon className="size-4" />
            </div>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold">{payload.label}</p>
                <p className="truncate text-[10px] text-muted-foreground">{payload.a4Path.split('.').pop()}</p>
            </div>
            {payload.isArray && (
                <Badge className="shrink-0 bg-amber-500/15 text-[10px] text-amber-700 hover:bg-amber-500/15 dark:text-amber-400">
                    list
                </Badge>
            )}
        </div>
    );
}

function BlockLiftCard({ label, badge }: { label: string; badge?: string }) {
    return (
        <div
            className={cn(
                'flex min-w-[220px] items-center gap-2 rounded-lg border border-primary/30 bg-card px-3 py-2.5',
                'shadow-2xl ring-2 ring-primary/25 animate-drag-lift',
            )}
        >
            <div className="flex size-7 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                <GripVertical className="size-4" />
            </div>
            <span className="text-sm font-semibold">{label}</span>
            {badge && (
                <Badge variant="secondary" className="text-[10px]">
                    {badge}
                </Badge>
            )}
        </div>
    );
}

export function TemplateDragOverlay({ active }: { active: ActiveDragState | null }) {
    const animation = active?.kind === 'variable' ? variableDropAnimation : dropAnimation;

    return (
        <DragOverlay dropAnimation={animation} zIndex={100}>
            {active?.kind === 'variable' ? (
                <VariableLiftCard payload={active.payload} />
            ) : active?.kind === 'block' ? (
                <BlockLiftCard label={active.label} badge={active.badge} />
            ) : null}
        </DragOverlay>
    );
}

export function resolveActiveDragFromEvent<T extends { id: string; type?: string; loop?: boolean }>(
    event: DragStartEvent,
    items: T[],
    labels: Record<string, string>,
): ActiveDragState | null {
    const payload = event.active.data.current as VariablePayload | undefined;

    if (payload?.kind === 'variable') {
        return { kind: 'variable', payload };
    }

    const item = items.find((entry) => entry.id === event.active.id);

    if (!item) {
        return null;
    }

    const typeKey = item.type ?? 'block';

    return {
        kind: 'block',
        label: labels[typeKey] ?? typeKey,
        badge: 'loop' in item && item.loop ? 'loop' : undefined,
    };
}
