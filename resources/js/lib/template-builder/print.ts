import type { A4Block, A4PageBackground, A4PageSettings } from './a4';
import { renderA4PrintDocument, renderThermalPreviewHtml } from './preview';
import type { ThermalElement } from './thermal';

const THERMAL_PRINT_WIDTH_MM = 80;

function printHtml(html: string): void {
    const iframe = document.createElement('iframe');
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.cssText =
        'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
    document.body.appendChild(iframe);

    const frameWindow = iframe.contentWindow;

    if (!frameWindow) {
        document.body.removeChild(iframe);

        return;
    }

    frameWindow.document.open();
    frameWindow.document.write(html);
    frameWindow.document.close();

    const triggerPrint = () => {
        frameWindow.focus();
        frameWindow.print();
        setTimeout(() => {
            if (iframe.parentNode) {
                document.body.removeChild(iframe);
            }
        }, 1000);
    };

    if (frameWindow.document.readyState === 'complete') {
        setTimeout(triggerPrint, 150);
    } else {
        iframe.onload = () => setTimeout(triggerPrint, 150);
    }
}

function buildThermalPrintDocument(body: string): string {
    return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Thermal Receipt</title>
  <style>
    @page { size: ${THERMAL_PRINT_WIDTH_MM}mm auto; margin: 2mm 1mm; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; background: #fff; color: #000; }
    body {
      width: ${THERMAL_PRINT_WIDTH_MM}mm;
      font-family: "Courier New", Courier, Consolas, monospace;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .receipt { padding: 2mm 1.5mm 4mm; }
    table { width: 100%; table-layout: fixed; border-collapse: collapse; }
    td { vertical-align: top; word-break: break-word; }
  </style>
</head>
<body>
  <div class="receipt">${body}</div>
</body>
</html>`;
}

export function printThermalTemplate(elements: ThermalElement[]): boolean {
    const body = renderThermalPreviewHtml(elements);

    if (!body) {
        return false;
    }

    printHtml(buildThermalPrintDocument(body));

    return true;
}

export function printA4Template(
    blocks: A4Block[],
    pageBackground: A4PageBackground,
    pageSettings?: A4PageSettings,
): boolean {
    if (blocks.length === 0 && !pageBackground.enabled) {
        return false;
    }

    printHtml(renderA4PrintDocument(blocks, pageBackground, pageSettings));

    return true;
}
