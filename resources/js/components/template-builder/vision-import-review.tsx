import { Loader2, RefreshCw } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { ScaledA4IframePreview } from '@/components/template-builder/scaled-a4-iframe-preview';
import { renderImportedEjsPrintDocument } from '@/lib/template-builder/a4-ejs-preview';
import type { VisionImportResponse } from '@/lib/template-builder/vision-import';
import { cn } from '@/lib/utils';

type VisionImportReviewProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    referenceImageUrl: string | null;
    result: VisionImportResponse | null;
    loading: boolean;
    onApply: (ejs: string) => void;
    onRegenerate: (refinement: string) => void;
};

export function VisionImportReview({
    open,
    onOpenChange,
    referenceImageUrl,
    result,
    loading,
    onApply,
    onRegenerate,
}: VisionImportReviewProps) {
    const [refinement, setRefinement] = useState('');

    const previewDocument = useMemo(() => {
        if (!result?.ejs?.trim()) {
            return '';
        }

        return renderImportedEjsPrintDocument(result.ejs) ?? result.ejs;
    }, [result?.ejs]);

    function handleRegenerate() {
        onRegenerate(refinement);
    }

    function handleApply() {
        if (result?.ejs) {
            onApply(result.ejs);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[92vh] w-[min(96vw,1200px)] max-w-none flex-col gap-4 overflow-hidden">
                <DialogHeader>
                    <DialogTitle>Review generated EJS</DialogTitle>
                    <DialogDescription>
                        Compare the uploaded document with the AI-generated preview before applying.
                    </DialogDescription>
                </DialogHeader>

                {result?.warnings && result.warnings.length > 0 && (
                    <div className="rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-900 dark:text-amber-200">
                        <p className="font-medium">Warnings</p>
                        <ul className="mt-1 list-inside list-disc space-y-0.5">
                            {result.warnings.map((warning) => (
                                <li key={warning}>{warning}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <div className="grid min-h-0 flex-1 gap-4 lg:grid-cols-2">
                    <div className="flex min-h-0 min-w-0 flex-col gap-2">
                        <p className="text-xs font-medium text-muted-foreground">Reference image</p>
                        <div className="min-h-0 flex-1 overflow-auto rounded-lg border bg-muted/20 p-2">
                            {referenceImageUrl ? (
                                <img
                                    src={referenceImageUrl}
                                    alt="Uploaded document"
                                    className="mx-auto block max-w-full"
                                />
                            ) : (
                                <p className="p-4 text-center text-sm text-muted-foreground">No image</p>
                            )}
                        </div>
                    </div>

                    <div className="flex min-h-0 min-w-0 flex-col gap-2">
                        <p className="text-xs font-medium text-muted-foreground">
                            Generated preview
                            {result?.provider && result.model && (
                                <span className="ml-2 font-normal text-muted-foreground/80">
                                    ({result.provider} / {result.model})
                                </span>
                            )}
                        </p>
                        <div
                            className={cn(
                                'min-h-[320px] flex-1 overflow-y-auto overflow-x-hidden rounded-lg border bg-muted/20 p-2',
                                loading && 'opacity-60',
                            )}
                        >
                            {loading ? (
                                <div className="flex h-full min-h-[320px] items-center justify-center gap-2 text-sm text-muted-foreground">
                                    <Loader2 className="size-4 animate-spin" />
                                    Generating EJS from image…
                                </div>
                            ) : previewDocument ? (
                                <ScaledA4IframePreview
                                    title="Vision import preview"
                                    srcDoc={previewDocument}
                                    className="mx-auto"
                                />
                            ) : (
                                <p className="p-4 text-center text-sm text-muted-foreground">
                                    No preview available.
                                </p>
                            )}
                        </div>
                    </div>
                </div>

                <div className="space-y-2">
                    <Label htmlFor="vision-refinement" className="text-xs">
                        Refinement hints (optional)
                    </Label>
                    <textarea
                        id="vision-refinement"
                        value={refinement}
                        onChange={(event) => setRefinement(event.target.value)}
                        placeholder="e.g. Match table borders exactly, fix header font size, preserve full terms text"
                        className="border-input placeholder:text-muted-foreground min-h-[72px] w-full resize-y rounded-md border bg-transparent px-3 py-2 text-xs shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        disabled={loading}
                    />
                </div>

                <DialogFooter className="gap-2 sm:justify-between">
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={loading}>
                        Cancel
                    </Button>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleRegenerate}
                            disabled={loading || !referenceImageUrl}
                        >
                            {loading ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <RefreshCw className="size-4" />
                            )}
                            Regenerate
                        </Button>
                        <Button type="button" onClick={handleApply} disabled={loading || !result?.ejs}>
                            Apply to editor
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
