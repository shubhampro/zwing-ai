import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export function SortableItem({
    id,
    label,
    badge,
    onRemove,
    children,
}: {
    id: string;
    label: string;
    badge?: string;
    onRemove: () => void;
    children?: ReactNode;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } =
        useSortable({ id });

    return (
        <div
            ref={setNodeRef}
            style={{
                transform: CSS.Transform.toString(transform),
                transition: isDragging ? undefined : transition,
            }}
            className={cn(
                'rounded-lg border bg-card transition-shadow duration-200',
                isDragging && 'opacity-30 ring-2 ring-dashed ring-primary/35',
            )}
        >
            <div className="flex items-center gap-2 border-b px-2 py-1.5">
                <button
                    type="button"
                    className="flex size-6 shrink-0 cursor-grab touch-none items-center justify-center rounded text-muted-foreground hover:bg-muted active:cursor-grabbing"
                    {...attributes}
                    {...listeners}
                >
                    <GripVertical className="size-4" />
                </button>
                <span className="text-sm font-medium">{label}</span>
                {badge && (
                    <Badge variant="secondary" className="text-[10px]">
                        {badge}
                    </Badge>
                )}
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="ml-auto size-7 text-muted-foreground hover:text-destructive"
                    onClick={onRemove}
                >
                    <Trash2 className="size-4" />
                </Button>
            </div>
            {children && <div className="space-y-3 p-3">{children}</div>}
        </div>
    );
}
