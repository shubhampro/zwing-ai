import { renderA4PreviewHtml, renderThermalPreviewHtml } from '@/lib/template-builder/preview';
import type { A4Block, A4PageBackground, A4PageSettings } from '@/lib/template-builder/a4';
import type { ThermalElement } from '@/lib/template-builder/thermal';

/** ~80mm thermal roll width at screen DPI. */
const THERMAL_PAPER_WIDTH_PX = 302;

export function ThermalPreview({ elements }: { elements: ThermalElement[] }) {
    if (elements.length === 0) {
        return (
            <div className="flex min-h-[320px] items-center justify-center bg-gradient-to-b from-zinc-300 to-zinc-200 px-4 py-10 dark:from-zinc-800 dark:to-zinc-900">
                <p className="text-center text-xs text-muted-foreground">
                    Add elements to preview the receipt.
                </p>
            </div>
        );
    }

    const html = renderThermalPreviewHtml(elements);

    return (
        <div className="overflow-hidden bg-gradient-to-b from-zinc-300 to-zinc-200 px-3 py-8 dark:from-zinc-800 dark:to-zinc-900">
            <div className="mx-auto flex w-full max-w-[340px] flex-col items-center">
                {/* Printer slot */}
                <div
                    className="h-2.5 rounded-t-md bg-zinc-800 shadow-inner dark:bg-zinc-950"
                    style={{ width: THERMAL_PAPER_WIDTH_PX + 20 }}
                />

                {/* Paper strip */}
                <div
                    className="relative overflow-hidden bg-[#fffef8] text-black shadow-[0_10px_28px_rgba(0,0,0,0.14),-3px_0_10px_rgba(0,0,0,0.05),3px_0_10px_rgba(0,0,0,0.05)]"
                    style={{ width: THERMAL_PAPER_WIDTH_PX }}
                >
                    <div
                        className="px-2.5 py-3 font-mono text-[13px] leading-snug text-black [&_table]:w-full"
                        // Preview markup is generated locally from the schema, no user HTML is injected.
                        dangerouslySetInnerHTML={{ __html: html }}
                    />
                    <div className="border-t border-dashed border-zinc-300" aria-hidden />
                </div>

                {/* Paper tail hanging from roll */}
                <div
                    className="bg-[#fffef8]/90 shadow-[0_6px_16px_rgba(0,0,0,0.08)]"
                    style={{ width: THERMAL_PAPER_WIDTH_PX - 12, height: 28 }}
                />
            </div>
        </div>
    );
}

export function A4Preview({
    blocks,
    pageBackground,
    pageSettings,
}: {
    blocks: A4Block[];
    pageBackground?: A4PageBackground;
    pageSettings?: A4PageSettings;
}) {
    const html = renderA4PreviewHtml(blocks, pageBackground, pageSettings);

    return (
        <div className="relative mx-auto w-full min-w-[794px] overflow-hidden bg-white text-black shadow-inner">
            {blocks.length === 0 && !pageBackground?.enabled ? (
                <p className="py-8 text-center text-xs text-muted-foreground">
                    Load a template or add blocks to preview the A4 page.
                </p>
            ) : (
                <div
                    className="relative min-h-[280px] text-[11px] leading-relaxed"
                    dangerouslySetInnerHTML={{ __html: html }}
                />
            )}
        </div>
    );
}
