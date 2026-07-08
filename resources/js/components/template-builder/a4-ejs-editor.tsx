import { Code2 } from 'lucide-react';
import { useMemo } from 'react';
import { Label } from '@/components/ui/label';
import { renderImportedEjsPrintDocument } from '@/lib/template-builder/a4-ejs-preview';

export function A4EjsEditor({
    source,
    onChange,
}: {
    source: string;
    onChange: (value: string) => void;
}) {
    const previewDocument = useMemo(() => renderImportedEjsPrintDocument(source), [source]);

    return (
        <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(420px,520px)]">
            <div className="space-y-2">
                <div className="flex items-center gap-2">
                    <Code2 className="size-4 text-muted-foreground" />
                    <Label className="text-sm font-medium">EJS / HTML source</Label>
                </div>
                <textarea
                    value={source}
                    onChange={(event) => onChange(event.target.value)}
                    spellCheck={false}
                    className="min-h-[calc(100vh-280px)] w-full resize-y rounded-lg border bg-muted/20 p-3 font-mono text-xs leading-relaxed outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                    placeholder="Paste or import EJS/HTML template here…"
                />
                <p className="text-[11px] text-muted-foreground">
                    Production templates (HOAD, etc.) — edit EJS directly. Preview uses sample invoice data.
                </p>
            </div>

            <div className="xl:sticky xl:top-4 xl:h-fit">
                <div className="overflow-hidden rounded-lg border bg-muted/30 shadow-sm">
                    <p className="border-b bg-muted/50 px-3 py-2 text-xs font-medium text-muted-foreground">
                        Live preview — A4 (sample data)
                    </p>
                    {previewDocument ? (
                        <iframe
                            title="EJS template preview"
                            srcDoc={previewDocument}
                            className="block h-[calc(100vh-280px)] min-h-[640px] w-full bg-white"
                            sandbox="allow-same-origin"
                        />
                    ) : (
                        <div className="flex min-h-[280px] items-center justify-center p-6 text-sm text-muted-foreground">
                            Import or paste EJS to preview
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
