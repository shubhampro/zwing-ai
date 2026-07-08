import { Braces, Plus, X } from 'lucide-react';
import { ContentDropZone } from '@/components/template-builder/dnd';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type EditorToken =
    | { kind: 'literal'; value: string }
    | { kind: 'variable'; path: string };

export function TextDataEditor({
    tokens,
    dropZoneId,
    onChange,
}: {
    tokens: EditorToken[];
    dropZoneId: string;
    onChange: (tokens: EditorToken[]) => void;
}) {
    function updateToken(index: number, next: EditorToken) {
        onChange(tokens.map((token, tokenIndex) => (tokenIndex === index ? next : token)));
    }

    function removeToken(index: number) {
        onChange(tokens.filter((_, tokenIndex) => tokenIndex !== index));
    }

    function addLiteral() {
        onChange([...tokens, { kind: 'literal', value: '' }]);
    }

    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-muted-foreground">Content</span>
                <Button type="button" variant="ghost" size="sm" className="h-6 px-2 text-xs" onClick={addLiteral}>
                    <Plus className="size-3" />
                    Text
                </Button>
            </div>
            <ContentDropZone id={dropZoneId} isEmpty={tokens.length === 0}>
                {tokens.map((token, index) =>
                    token.kind === 'literal' ? (
                        <span
                            key={index}
                            className="inline-flex w-full basis-full animate-drop-in items-start gap-1 rounded-md border border-border/70 bg-muted p-2"
                        >
                            <textarea
                                value={token.value}
                                onChange={(event) =>
                                    updateToken(index, { kind: 'literal', value: event.target.value })
                                }
                                onKeyDown={(event) => event.stopPropagation()}
                                placeholder="text"
                                rows={Math.min(
                                    8,
                                    Math.max(
                                        2,
                                        token.value.split('\n').length +
                                            (token.value.endsWith('\n') ? 1 : 0),
                                    ),
                                )}
                                className="min-h-[2.25rem] w-full flex-1 resize-y bg-transparent text-xs leading-relaxed outline-none"
                            />
                            <button
                                type="button"
                                onClick={() => removeToken(index)}
                                className="mt-0.5 shrink-0 text-muted-foreground hover:text-destructive"
                            >
                                <X className="size-3" />
                            </button>
                        </span>
                    ) : (
                        <span
                            key={index}
                            className={cn(
                                'inline-flex animate-drop-in items-center gap-1.5 rounded-md border border-primary/30',
                                'bg-primary/10 px-2 py-1 text-xs font-medium text-primary shadow-sm',
                            )}
                            title={token.path}
                        >
                            <Braces className="size-3 shrink-0" />
                            {token.path.split('.').pop()}
                            <button
                                type="button"
                                onClick={() => removeToken(index)}
                                className="rounded-sm hover:text-destructive"
                            >
                                <X className="size-3" />
                            </button>
                        </span>
                    ),
                )}
            </ContentDropZone>
        </div>
    );
}
