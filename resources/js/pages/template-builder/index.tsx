import { Head } from '@inertiajs/react';
import { Download, FileImage, FileUp, Loader2, Printer, RotateCcw } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { A4Canvas } from '@/components/template-builder/a4-canvas';
import { ThermalCanvas } from '@/components/template-builder/thermal-canvas';
import { VisionImportReview } from '@/components/template-builder/vision-import-review';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    createDefaultPageBackground,
    createDefaultPageSettings,
    generateEjs,
    type A4Block,
    type A4PageBackground,
    type A4PageSettings,
} from '@/lib/template-builder/a4';
import { renderImportedEjsPrintDocument } from '@/lib/template-builder/a4-ejs-preview';
import { parseA4Ejs } from '@/lib/template-builder/a4-import';
import { normalizeDocumentToImageBlob } from '@/lib/template-builder/pdf-to-image';
import { syncBlocksToProductionEjs, type ProductionSegment } from '@/lib/template-builder/a4-production-sync';
import { printA4Template, printThermalTemplate } from '@/lib/template-builder/print';
import { parseThermal, serializeThermal, type ThermalElement } from '@/lib/template-builder/thermal';
import { importTemplateFromVision, type VisionImportResponse } from '@/lib/template-builder/vision-import';
import { dashboard } from '@/routes';
import { index } from '@/routes/template-builder';

type BuilderMode = 'thermal' | 'a4';

function download(filename: string, content: string, mime: string) {
    const blob = new Blob([content], { type: mime });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    anchor.click();
    URL.revokeObjectURL(url);
}

export default function TemplateBuilderIndex() {
    const [mode, setMode] = useState<BuilderMode>('thermal');
    const [thermalElements, setThermalElements] = useState<ThermalElement[]>([]);
    const [a4Blocks, setA4Blocks] = useState<A4Block[]>([]);
    const [a4EjsSource, setA4EjsSource] = useState('');
    const [a4ImportedBlockSnapshots, setA4ImportedBlockSnapshots] = useState<Record<string, string>>({});
    const [a4ProductionSegments, setA4ProductionSegments] = useState<ProductionSegment[]>([]);

    const changedBlockIds = useMemo(() => {
        const ids = new Set<string>();

        for (const block of a4Blocks) {
            const snapshot = a4ImportedBlockSnapshots[block.id];

            if (snapshot && JSON.stringify(block) !== snapshot) {
                ids.add(block.id);
            }
        }

        return ids;
    }, [a4Blocks, a4ImportedBlockSnapshots]);

    const syncedEjsPreview = useMemo(() => {
        if (!a4EjsSource.trim() || a4ProductionSegments.length === 0) {
            return a4EjsSource;
        }

        if (changedBlockIds.size === 0) {
            return a4EjsSource;
        }

        return syncBlocksToProductionEjs(a4EjsSource, a4Blocks, a4ProductionSegments, changedBlockIds);
    }, [a4EjsSource, a4Blocks, a4ProductionSegments, changedBlockIds]);

    const [pageBackground, setPageBackground] = useState<A4PageBackground>(createDefaultPageBackground);
    const [pageSettings, setPageSettings] = useState<A4PageSettings>(createDefaultPageSettings);
    const thermalFileInputRef = useRef<HTMLInputElement>(null);
    const a4FileInputRef = useRef<HTMLInputElement>(null);
    const visionFileInputRef = useRef<HTMLInputElement>(null);
    const [visionLoading, setVisionLoading] = useState(false);
    const [visionReviewOpen, setVisionReviewOpen] = useState(false);
    const [visionResult, setVisionResult] = useState<VisionImportResponse | null>(null);
    const [visionReferenceUrl, setVisionReferenceUrl] = useState<string | null>(null);
    const [visionImageBlob, setVisionImageBlob] = useState<Blob | null>(null);

    function handleDownload() {
        if (mode === 'thermal') {
            if (thermalElements.length === 0) {
                toast.error('Add at least one element first.');

                return;
            }

            download('thermal-template.json', JSON.stringify(serializeThermal(thermalElements), null, 2), 'application/json');
            toast.success('Thermal template downloaded.');

            return;
        }

        if (syncedEjsPreview.trim() && a4ProductionSegments.length > 0) {
            download('template.ejs', syncedEjsPreview, 'text/html');
            toast.success('EJS template downloaded.');

            return;
        }

        if (a4Blocks.length === 0 && !pageBackground.enabled) {
            toast.error('Add at least one block or enable a page background.');

            return;
        }

        download('a4-template.ejs', generateEjs(a4Blocks, pageBackground, pageSettings), 'text/html');
        toast.success('A4 template downloaded.');
    }

    async function handleThermalImport(file: File) {
        try {
            const text = await file.text();
            const parsed = parseThermal(JSON.parse(text));
            setThermalElements(parsed);
            setMode('thermal');
            toast.success('Thermal template imported.');
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not import file.');
        } finally {
            if (thermalFileInputRef.current) {
                thermalFileInputRef.current.value = '';
            }
        }
    }

    useEffect(() => {
        return () => {
            if (visionReferenceUrl) {
                URL.revokeObjectURL(visionReferenceUrl);
            }
        };
    }, [visionReferenceUrl]);

    function applyA4EjsText(text: string): boolean {
        const result = parseA4Ejs(text);

        setMode('a4');
        setA4EjsSource(result.ejsSource);

        if (result.blocks.length === 0) {
            toast.error('Could not parse blocks from this EJS file.');

            return false;
        }

        setA4Blocks(result.blocks);
        setPageBackground(result.pageBackground);
        setPageSettings(result.pageSettings);
        setA4ImportedBlockSnapshots(
            Object.fromEntries(result.blocks.map((block) => [block.id, JSON.stringify(block)])),
        );
        setA4ProductionSegments(result.productionSegments);
        toast.success(`Imported ${result.blocks.length} sections — edit blocks on the left.`);

        return true;
    }

    async function handleA4EjsImport(file: File) {
        try {
            const text = await file.text();

            applyA4EjsText(text);
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not import EJS file.');
        } finally {
            if (a4FileInputRef.current) {
                a4FileInputRef.current.value = '';
            }
        }
    }

    async function runVisionImport(imageBlob: Blob, refinement?: string) {
        setVisionLoading(true);
        setVisionResult(null);

        try {
            const response = await importTemplateFromVision(imageBlob, refinement);

            if (!response.success || !response.ejs) {
                toast.error(response.message ?? 'Vision import failed.');

                return;
            }

            setVisionResult(response);
            setVisionReviewOpen(true);
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Vision import failed.');
        } finally {
            setVisionLoading(false);
        }
    }

    async function handleVisionFileImport(file: File) {
        try {
            const imageBlob = await normalizeDocumentToImageBlob(file);

            if (visionReferenceUrl) {
                URL.revokeObjectURL(visionReferenceUrl);
            }

            const referenceUrl = URL.createObjectURL(imageBlob);
            setVisionReferenceUrl(referenceUrl);
            setVisionImageBlob(imageBlob);
            setMode('a4');
            setVisionReviewOpen(true);
            await runVisionImport(imageBlob);
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not process file.');
        } finally {
            if (visionFileInputRef.current) {
                visionFileInputRef.current.value = '';
            }
        }
    }

    function handleVisionApply(ejs: string) {
        if (applyA4EjsText(ejs)) {
            setVisionReviewOpen(false);
        }
    }

    async function handleVisionRegenerate(refinement: string) {
        if (!visionImageBlob) {
            toast.error('No image available to regenerate.');

            return;
        }

        await runVisionImport(visionImageBlob, refinement);
    }

    function handleReset() {
        if (mode === 'thermal') {
            setThermalElements([]);

            return;
        }

        setA4EjsSource('');
        setA4ImportedBlockSnapshots({});
        setA4ProductionSegments([]);
        setA4Blocks([]);
        setPageBackground(createDefaultPageBackground());
        setPageSettings(createDefaultPageSettings());
    }

    function handlePrint() {
        if (mode === 'thermal') {
            if (thermalElements.length === 0) {
                toast.error('Add at least one element to print.');

                return;
            }

            if (!printThermalTemplate(thermalElements)) {
                toast.error('Nothing to print.');
            }

            return;
        }

        if (syncedEjsPreview.trim() && a4ProductionSegments.length > 0) {
            const doc = renderImportedEjsPrintDocument(syncedEjsPreview);

            if (!doc) {
                toast.error('Nothing to print.');

                return;
            }

            const iframe = document.createElement('iframe');
            iframe.setAttribute('aria-hidden', 'true');
            iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
            document.body.appendChild(iframe);
            const win = iframe.contentWindow;

            if (win) {
                win.document.open();
                win.document.write(doc);
                win.document.close();
                setTimeout(() => {
                    win.focus();
                    win.print();
                    setTimeout(() => iframe.remove(), 1000);
                }, 250);
            }

            return;
        }

        if (a4Blocks.length === 0 && !pageBackground.enabled) {
            toast.error('Add at least one block or enable a page background to print.');

            return;
        }

        if (!printA4Template(a4Blocks, pageBackground, pageSettings)) {
            toast.error('Nothing to print.');
        }
    }

    return (
        <>
            <Head title="Template Builder" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Template Builder"
                        description="Design professional A4 tax invoices and thermal receipts with drag-and-drop fields."
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={mode}
                            onValueChange={(value) => value && setMode(value as BuilderMode)}
                        >
                            <ToggleGroupItem value="thermal" className="px-3">
                                Thermal
                            </ToggleGroupItem>
                            <ToggleGroupItem value="a4" className="px-3">
                                A4
                            </ToggleGroupItem>
                        </ToggleGroup>

                        <input
                            ref={thermalFileInputRef}
                            type="file"
                            accept=".json,application/json"
                            className="hidden"
                            onChange={(event) => {
                                const file = event.target.files?.[0];

                                if (file) {
                                    void handleThermalImport(file);
                                }
                            }}
                        />
                        <input
                            ref={a4FileInputRef}
                            type="file"
                            accept=".ejs,.html,.htm,text/html"
                            className="hidden"
                            onChange={(event) => {
                                const file = event.target.files?.[0];

                                if (file) {
                                    void handleA4EjsImport(file);
                                }
                            }}
                        />
                        <input
                            ref={visionFileInputRef}
                            type="file"
                            accept="image/*,.pdf,application/pdf"
                            className="hidden"
                            onChange={(event) => {
                                const file = event.target.files?.[0];

                                if (file) {
                                    void handleVisionFileImport(file);
                                }
                            }}
                        />
                        {mode === 'thermal' && (
                            <Button size="sm" variant="outline" onClick={() => thermalFileInputRef.current?.click()}>
                                <FileUp className="size-4" />
                                Import JSON
                            </Button>
                        )}
                        {mode === 'a4' && (
                            <>
                                <Button size="sm" variant="outline" onClick={() => a4FileInputRef.current?.click()}>
                                    <FileUp className="size-4" />
                                    Import EJS
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => visionFileInputRef.current?.click()}
                                    disabled={visionLoading}
                                >
                                    {visionLoading ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : (
                                        <FileImage className="size-4" />
                                    )}
                                    Import Image/PDF
                                </Button>
                            </>
                        )}
                        <Button size="sm" variant="outline" onClick={handleReset}>
                            <RotateCcw className="size-4" />
                            Reset
                        </Button>
                        <Button size="sm" variant="outline" onClick={handlePrint}>
                            <Printer className="size-4" />
                            Print
                        </Button>
                        <Button size="sm" onClick={handleDownload}>
                            <Download className="size-4" />
                            Download {mode === 'thermal' ? 'JSON' : 'EJS'}
                        </Button>
                    </div>
                </div>

                {mode === 'thermal' ? (
                    <ThermalCanvas elements={thermalElements} setElements={setThermalElements} />
                ) : (
                    <A4Canvas
                        blocks={a4Blocks}
                        setBlocks={setA4Blocks}
                        pageBackground={pageBackground}
                        setPageBackground={setPageBackground}
                        pageSettings={pageSettings}
                        setPageSettings={setPageSettings}
                        ejsPreviewSource={syncedEjsPreview}
                        hasProductionPreview={a4ProductionSegments.length > 0}
                    />
                )}
            </div>

            <VisionImportReview
                open={visionReviewOpen}
                onOpenChange={setVisionReviewOpen}
                referenceImageUrl={visionReferenceUrl}
                result={visionResult}
                loading={visionLoading}
                onApply={handleVisionApply}
                onRegenerate={handleVisionRegenerate}
            />
        </>
    );
}

TemplateBuilderIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Template Builder', href: index.url() },
    ],
};
