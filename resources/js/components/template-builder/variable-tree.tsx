import {
    Braces,
    ChevronRight,
    GripVertical,
    Hash,
    List,
    Search,
    SearchX,
    Sparkles,
    Type,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { DraggableVariable, type VariablePayload } from '@/components/template-builder/dnd';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { flattenSchema } from '@/lib/template-builder/schema';
import type { VariableNode } from '@/lib/template-builder/schema';
import { cn } from '@/lib/utils';

const GROUP_ACCENTS = [
    {
        bar: 'bg-sky-500',
        surface: 'from-sky-500/8 to-transparent dark:from-sky-500/12',
        icon: 'bg-sky-500/12 text-sky-700 dark:text-sky-400',
        ring: 'ring-sky-500/15',
    },
    {
        bar: 'bg-violet-500',
        surface: 'from-violet-500/8 to-transparent dark:from-violet-500/12',
        icon: 'bg-violet-500/12 text-violet-700 dark:text-violet-400',
        ring: 'ring-violet-500/15',
    },
    {
        bar: 'bg-emerald-500',
        surface: 'from-emerald-500/8 to-transparent dark:from-emerald-500/12',
        icon: 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-400',
        ring: 'ring-emerald-500/15',
    },
    {
        bar: 'bg-amber-500',
        surface: 'from-amber-500/8 to-transparent dark:from-amber-500/12',
        icon: 'bg-amber-500/12 text-amber-700 dark:text-amber-400',
        ring: 'ring-amber-500/15',
    },
    {
        bar: 'bg-rose-500',
        surface: 'from-rose-500/8 to-transparent dark:from-rose-500/12',
        icon: 'bg-rose-500/12 text-rose-700 dark:text-rose-400',
        ring: 'ring-rose-500/15',
    },
    {
        bar: 'bg-cyan-500',
        surface: 'from-cyan-500/8 to-transparent dark:from-cyan-500/12',
        icon: 'bg-cyan-500/12 text-cyan-700 dark:text-cyan-400',
        ring: 'ring-cyan-500/15',
    },
] as const;

function toPayload(node: VariableNode): VariablePayload {
    return {
        kind: 'variable',
        label: node.label,
        thermalPath: node.thermalPath,
        a4Path: node.a4Path,
        relativePath: node.relativePath,
        isArray: node.isArray,
    };
}

function nodeMatches(node: VariableNode, term: string): boolean {
    if (!term) {
        return true;
    }

    const haystack = `${node.label} ${node.a4Path} ${node.thermalPath}`.toLowerCase();

    if (haystack.includes(term)) {
        return true;
    }

    return node.children.some((child) => nodeMatches(child, term));
}

function countLeaves(node: VariableNode): number {
    if (node.isLeaf) {
        return 1;
    }

    return node.children.reduce((total, child) => total + countLeaves(child), 0);
}

function countMatchingLeaves(node: VariableNode, term: string): number {
    if (!nodeMatches(node, term)) {
        return 0;
    }

    if (node.isLeaf) {
        return 1;
    }

    return node.children.reduce((total, child) => total + countMatchingLeaves(child, term), 0);
}

function fieldKey(node: VariableNode): string {
    return node.relativePath.split('.').pop() ?? node.label;
}

function fieldTypeMeta(node: VariableNode): {
    Icon: typeof Type;
    badge: string;
    iconClass: string;
    badgeClass: string;
} {
    if (node.isArray) {
        return {
            Icon: List,
            badge: 'list',
            iconClass: 'bg-amber-500/12 text-amber-700 dark:text-amber-400',
            badgeClass: 'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-400',
        };
    }

    if (node.type === 'number') {
        return {
            Icon: Hash,
            badge: 'num',
            iconClass: 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-400',
            badgeClass: 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        };
    }

    return {
        Icon: Type,
        badge: 'text',
        iconClass: 'bg-sky-500/12 text-sky-700 dark:text-sky-400',
        badgeClass: 'border-sky-500/25 bg-sky-500/10 text-sky-700 dark:text-sky-400',
    };
}

function VariableFieldCard({ node }: { node: VariableNode }) {
    const { Icon, badge, iconClass, badgeClass } = fieldTypeMeta(node);
    const preview =
        node.placeholder !== undefined ? String(node.placeholder) : null;

    return (
        <DraggableVariable
            id={`var:${node.a4Path}`}
            payload={toPayload(node)}
            className={cn(
                'group flex w-full items-center gap-2 rounded-lg border border-border/60 bg-background/80 px-2 py-1.5 text-left',
                'shadow-sm hover:border-primary/30 hover:bg-accent/50 hover:shadow-md',
                'active:scale-[0.98]',
            )}
        >
            <div
                className={cn(
                    'flex size-7 shrink-0 items-center justify-center rounded-md transition-transform group-hover:scale-105',
                    iconClass,
                )}
            >
                <Icon className="size-3.5" />
            </div>

            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-1.5">
                    <span className="truncate text-xs font-medium">{node.label}</span>
                    <Badge
                        variant="outline"
                        className={cn('h-4 px-1 text-[9px] font-semibold uppercase tracking-wide', badgeClass)}
                    >
                        {badge}
                    </Badge>
                </div>
                <p className="truncate font-mono text-[10px] text-muted-foreground/80">{fieldKey(node)}</p>
                {preview && (
                    <p className="truncate text-[10px] text-muted-foreground/60 italic">{preview}</p>
                )}
            </div>

            <GripVertical className="size-3.5 shrink-0 text-muted-foreground/30 transition-colors group-hover:text-muted-foreground/70" />
        </DraggableVariable>
    );
}

function ArraySourceCard({ node }: { node: VariableNode }) {
    return (
        <DraggableVariable
            id={`var:${node.a4Path}`}
            payload={toPayload(node)}
            className={cn(
                'group flex w-full items-center gap-2 rounded-lg border border-amber-500/30 bg-gradient-to-r from-amber-500/10 to-amber-500/5 px-2.5 py-2',
                'text-left shadow-sm hover:border-amber-500/50 hover:from-amber-500/15 hover:shadow-md',
            )}
        >
            <div className="flex size-7 shrink-0 items-center justify-center rounded-md bg-amber-500/15 text-amber-700 dark:text-amber-400">
                <List className="size-3.5" />
            </div>
            <div className="min-w-0 flex-1">
                <p className="truncate text-xs font-semibold">{node.label}</p>
                <p className="text-[10px] text-muted-foreground">Drop on table loop</p>
            </div>
            <Badge className="shrink-0 border-amber-500/30 bg-amber-500/15 text-[9px] text-amber-800 hover:bg-amber-500/15 dark:text-amber-300">
                array
            </Badge>
        </DraggableVariable>
    );
}

function VariableGroup({
    node,
    depth,
    term,
    accentIndex,
}: {
    node: VariableNode;
    depth: number;
    term: string;
    accentIndex: number;
}) {
    const [open, setOpen] = useState(depth < 1);
    const expanded = open || term.length > 0;
    const accent = GROUP_ACCENTS[accentIndex % GROUP_ACCENTS.length];
    const matchCount = countMatchingLeaves(node, term);

    if (!nodeMatches(node, term)) {
        return null;
    }

    if (node.isLeaf) {
        return <VariableFieldCard node={node} />;
    }

    const isTopLevel = depth === 0;
    const isArrayGroup = node.isArray;

    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border border-border/70 bg-card/50',
                isTopLevel && 'shadow-sm',
            )}
        >
            <div className="relative flex items-stretch">
                {isTopLevel && <div className={cn('w-1 shrink-0', accent.bar)} />}

                <div className="min-w-0 flex-1">
                    <button
                        type="button"
                        onClick={() => setOpen((value) => !value)}
                        className={cn(
                            'flex w-full items-center gap-2 px-2.5 py-2 text-left transition-colors hover:bg-muted/40',
                            isTopLevel && cn('bg-gradient-to-r', accent.surface),
                        )}
                    >
                        <div
                            className={cn(
                                'flex size-7 shrink-0 items-center justify-center rounded-lg',
                                isTopLevel ? accent.icon : 'bg-muted text-muted-foreground',
                            )}
                        >
                            {isArrayGroup ? (
                                <List className="size-3.5" />
                            ) : (
                                <Braces className="size-3.5" />
                            )}
                        </div>

                        <div className="min-w-0 flex-1">
                            <p className="truncate text-xs font-semibold">{node.label}</p>
                            {!isTopLevel && (
                                <p className="truncate font-mono text-[10px] text-muted-foreground">
                                    {fieldKey(node)}
                                </p>
                            )}
                        </div>

                        <Badge variant="secondary" className="h-5 shrink-0 px-1.5 text-[10px] tabular-nums">
                            {term ? matchCount : countLeaves(node)}
                        </Badge>

                        <ChevronRight
                            className={cn(
                                'size-4 shrink-0 text-muted-foreground transition-transform duration-200',
                                expanded && 'rotate-90',
                            )}
                        />
                    </button>

                    {expanded && (
                        <div
                            className={cn(
                                'space-y-1 border-t border-border/50 p-1.5',
                                depth > 0 && 'ml-2 border-l-2 border-l-border/60 pl-2',
                            )}
                        >
                            {isArrayGroup && <ArraySourceCard node={node} />}

                            {node.children.map((child) =>
                                child.isLeaf ? (
                                    <VariableFieldCard key={child.id} node={child} />
                                ) : (
                                    <VariableGroup
                                        key={child.id}
                                        node={child}
                                        depth={depth + 1}
                                        term={term}
                                        accentIndex={accentIndex}
                                    />
                                ),
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

export function VariableTree() {
    const nodes = useMemo(() => flattenSchema(), []);
    const [search, setSearch] = useState('');
    const term = search.trim().toLowerCase();

    const totalFields = useMemo(
        () => nodes.reduce((total, node) => total + countLeaves(node), 0),
        [nodes],
    );

    const visibleCount = useMemo(
        () => nodes.reduce((total, node) => total + countMatchingLeaves(node, term), 0),
        [nodes, term],
    );

    const hasResults = nodes.some((node) => nodeMatches(node, term));

    return (
        <div className="flex h-full flex-col gap-3 rounded-xl border border-border/80 bg-gradient-to-b from-muted/30 to-background p-3 shadow-sm">
            <div className="space-y-1">
                <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <div className="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Sparkles className="size-4" />
                        </div>
                        <div>
                            <h3 className="text-sm font-semibold leading-none">Variables</h3>
                            <p className="mt-0.5 text-[11px] text-muted-foreground">
                                Drag onto template blocks
                            </p>
                        </div>
                    </div>
                    <Badge variant="outline" className="tabular-nums">
                        {term ? `${visibleCount}/${totalFields}` : totalFields}
                    </Badge>
                </div>
            </div>

            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Search fields…"
                    className="h-9 bg-background/80 pl-8 text-xs shadow-inner"
                />
            </div>

            <div className="flex-1 space-y-2 overflow-y-auto pr-0.5 [-ms-overflow-style:none] [scrollbar-width:thin] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-border">
                {!hasResults && (
                    <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed px-4 py-8 text-center">
                        <SearchX className="size-8 text-muted-foreground/50" />
                        <p className="text-sm font-medium">No fields found</p>
                        <p className="text-xs text-muted-foreground">Try a different search term</p>
                    </div>
                )}

                {nodes.map((node, index) => (
                    <VariableGroup
                        key={node.id}
                        node={node}
                        depth={0}
                        term={term}
                        accentIndex={index}
                    />
                ))}
            </div>
        </div>
    );
}
