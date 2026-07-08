import type {
    A4Block,
    A4Column,
    A4HeaderSlot,
    A4PageBackground,
    A4PageSettings,
    A4TextToken,
} from './a4';
import { resolvePlaceholder } from './schema';
import type { ThermalColumn, ThermalElement, ThermalTextToken } from './thermal';

const PREVIEW_LOOP_ROWS = 2;

const LOOP_SAMPLE_ROWS: Record<string, Record<string, string>[]> = {
    'header.invoice.invoiceDetails.productList': [
        {
            name: 'Embroidered Kurta Set',
            hsnCode: '6204',
            category: 'Kurta',
            brand: 'Twamev',
            taxper: '18',
            qty: '1',
            unitMrp: '4999.00',
            effectivePrice: '3999.00',
            total: '3999.00',
        },
        {
            name: 'Silk Dupatta',
            hsnCode: '6214',
            category: 'Dupatta',
            brand: 'Twamev',
            taxper: '5',
            qty: '1',
            unitMrp: '2499.00',
            effectivePrice: '1999.00',
            total: '1999.00',
        },
    ],
    'header.payments': [
        { mop_name: 'Cash', amount: '399.00' },
        { mop_name: 'UPI', amount: '549.00' },
    ],
    'header.invoice.invoiceDetails.taxSummary': [
        { name: 'GST 18%', taxable: '3372.88', cgst: '303.56', sgst: '303.56', cess: '0.00', igst: '0.00' },
        { name: 'GST 5%', taxable: '1903.81', cgst: '47.60', sgst: '47.60', cess: '0.00', igst: '0.00' },
    ],
};

function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function thermalFontSize(font: number): number {
    return Math.min(22, Math.max(11, Math.round(font * 0.7)));
}

const THERMAL_FONT = '"Courier New",Courier,Consolas,monospace';

function resolveLoopSample(loopPath: string, fieldPath: string, rowIndex: number): string | null {
    const normalized = loopPath.replace(/^printData\./, '');
    const rows = LOOP_SAMPLE_ROWS[normalized];

    if (!rows?.[rowIndex]) {
        return null;
    }

    const field = fieldPath.includes('.') ? (fieldPath.split('.').pop() ?? fieldPath) : fieldPath;

    return rows[rowIndex][field] ?? null;
}

export function resolveThermalColumn(
    column: ThermalColumn,
    rowIndex: number,
    loopPath?: string,
): string {
    if (column.mode === 'index') {
        return String(rowIndex + 1);
    }

    if (column.mode === 'static') {
        return column.value;
    }

    if (loopPath) {
        const sample = resolveLoopSample(loopPath, column.path, rowIndex);

        if (sample !== null) {
            return `${column.prefix}${sample}`;
        }
    }

    return `${column.prefix}${resolvePlaceholder(column.path, loopPath)}`;
}

export function resolveThermalText(tokens: ThermalTextToken[], contextPath?: string): string {
    return tokens
        .map((token) =>
            token.kind === 'literal' ? token.value : resolvePlaceholder(token.path, contextPath),
        )
        .join('');
}

function thermalTextStyle(
    font: number,
    align: 'left' | 'center' | 'right',
    bold: boolean,
): string {
    return [
        'display:block',
        'width:100%',
        'box-sizing:border-box',
        `font-size:${thermalFontSize(font)}px`,
        `text-align:${align}`,
        `font-weight:${bold ? '700' : '400'}`,
        `font-family:${THERMAL_FONT}`,
        'margin:0',
        'padding:3px 4px',
        'line-height:1.45',
        'color:#000',
        'white-space:pre-wrap',
        'word-break:break-word',
    ].join(';');
}

function dashedDivider(): string {
    return '<div style="width:100%;margin:8px 0;border-top:1px dashed #333;box-sizing:border-box;"></div>';
}

function columnWidthPercent(columns: ThermalColumn[], index: number): number {
    const totalWeight = columns.reduce((sum, column) => sum + (column.weight || 1), 0) || 1;

    return Math.round(((columns[index]?.weight || 1) / totalWeight) * 1000) / 10;
}

function renderThermalTable(element: Extract<ThermalElement, { type: 'table' }>): string {
    const rowCount = element.loop ? PREVIEW_LOOP_ROWS : 1;
    const rows: string[] = [];

    for (let rowIndex = 0; rowIndex < rowCount; rowIndex += 1) {
        const cells = element.columns
            .map((column, columnIndex) => {
                const value = resolveThermalColumn(
                    column,
                    rowIndex,
                    element.loop ? element.path : undefined,
                );
                const align =
                    columnIndex === element.columns.length - 1 && element.columns.length > 1
                        ? 'right'
                        : 'left';

                return `<td style="width:${columnWidthPercent(element.columns, columnIndex)}%;text-align:${align};padding:2px 4px;vertical-align:top;">${escapeHtml(value)}</td>`;
            })
            .join('');

        rows.push(`<tr>${cells}</tr>`);
    }

    return `<table style="width:100%;table-layout:fixed;border-collapse:collapse;margin:2px 0;font-family:${THERMAL_FONT};font-size:${thermalFontSize(element.font)}px;font-weight:${element.bold ? '700' : '400'};color:#000;"><tbody>${rows.join('')}</tbody></table>`;
}

function renderThermalElement(element: ThermalElement): string {
    if (element.type === 'divider') {
        return dashedDivider();
    }

    if (element.type === 'table') {
        return renderThermalTable(element);
    }

    if (element.type === 'text') {
        const text = resolveThermalText(element.tokens);

        return `<div style="${thermalTextStyle(element.font, element.align, element.bold)}">${escapeHtml(text)}</div>`;
    }

    const text = resolvePlaceholder(element.path);

    return `<div style="${thermalTextStyle(element.font, element.align, element.bold)}">${escapeHtml(text)}</div>`;
}

/** Produces thermal receipt HTML matching the Print Invoice preview format. */
export function renderThermalPreviewHtml(elements: ThermalElement[]): string {
    if (elements.length === 0) {
        return '';
    }

    return elements.map(renderThermalElement).join('');
}

function resolveA4Tokens(tokens: A4TextToken[], contextPath?: string): string {
    return tokens
        .map((token) =>
            token.kind === 'literal'
                ? escapeHtml(token.value)
                : escapeHtml(resolvePlaceholder(token.path, contextPath)),
        )
        .join('');
}

const HEADING_SIZE = { lg: '22px', md: '18px', sm: '15px' } as const;
const TEXT_SIZE = { xs: '10px', sm: '12px', md: '13px', lg: '15px' } as const;

const PREVIEW_ROWS = 2;

function normalizeArrayPath(arrayPath: string): string {
    return arrayPath.replace(/^printData\./, '');
}

function resolveColumnCell(column: A4Column, rowIndex: number, arrayPath: string): string {
    if (column.mode === 'index') {
        return String(rowIndex + 1);
    }

    if (column.mode === 'static') {
        return escapeHtml(column.staticValue ?? '');
    }

    const loopKey = normalizeArrayPath(arrayPath);

    if (column.tokens && column.tokens.length > 0) {
        return column.tokens
            .map((token) => {
                if (token.kind === 'literal') {
                    return escapeHtml(token.value);
                }

                const field = token.path.split('.').pop() ?? token.path;
                const sample = resolveLoopSample(loopKey, field, rowIndex);

                return escapeHtml(sample ?? resolvePlaceholder(field, loopKey));
            })
            .join('');
    }

    const sample = resolveLoopSample(loopKey, column.path, rowIndex);

    return escapeHtml(sample ?? resolvePlaceholder(column.path, loopKey));
}

function resolveImageSrc(sourceType: 'variable' | 'url', path: string, url: string): string {
    if (sourceType === 'url' && url) {
        return escapeHtml(url);
    }

    if (sourceType === 'variable' && path) {
        return escapeHtml(resolvePlaceholder(path));
    }

    return (
        'data:image/svg+xml,' +
        encodeURIComponent(
            '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="60"><rect fill="#e5e7eb" width="120" height="60"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#9ca3af" font-size="11">Logo</text></svg>',
        )
    );
}

function renderHeaderSlotPreview(slot: A4HeaderSlot): string {
    if (slot.slotType === 'image') {
        const src = resolveImageSrc(slot.sourceType, slot.path, slot.url);

        return `<div style="text-align:${slot.align};"><img src="${src}" alt="Logo" style="width:${slot.width || '90px'};max-height:${slot.maxHeight || '60px'};object-fit:contain;" /></div>`;
    }

    return `<div style="text-align:${slot.align};white-space:pre-wrap;line-height:1.4;">${resolveA4Tokens(slot.tokens)}</div>`;
}

function renderPageBackgroundStyle(background: A4PageBackground): string {
    if (!background.enabled) {
        return '';
    }

    const src = resolveImageSrc(background.sourceType, background.path, background.url);
    const opacity = Math.min(100, Math.max(0, background.opacity)) / 100;

    return `position:absolute;inset:0;z-index:0;pointer-events:none;background-image:url('${src}');background-size:${background.size};background-position:${background.position};background-repeat:no-repeat;opacity:${opacity};`;
}

function previewStyles(settings?: A4PageSettings): string {
    const font = settings?.fontFamily ?? 'Arial';
    const base = settings?.baseFontSize ?? 12;
    const margin = settings?.marginMm ?? 14;

    return [
        `font-family:${font},Helvetica,sans-serif`,
        `font-size:${base}px`,
        `padding:${Math.round(margin * 0.55)}px`,
        'color:#111',
        'line-height:1.4',
        'position:relative',
    ].join(';');
}

function columnAlignClass(column: A4Column): string {
    return column.format === 'number' || column.format === 'currency' ? 'text-align:right;font-variant-numeric:tabular-nums;' : '';
}

/** Produces resolved (placeholder-filled) HTML for the A4 preview pane. */
export function renderA4PreviewHtml(
    blocks: A4Block[],
    pageBackground?: A4PageBackground,
    pageSettings?: A4PageSettings,
): string {
    const bgHtml = pageBackground?.enabled
        ? `<div style="${renderPageBackgroundStyle(pageBackground)}"></div>`
        : '';

    const body = blocks
        .map((block) => {
            switch (block.type) {
                case 'heading':
                    return `<div style="text-align:${block.align};font-size:${HEADING_SIZE[block.size]};font-weight:${block.bold ? 700 : 400};text-transform:${block.uppercase ? 'uppercase' : 'none'};margin:6px 0;">${resolveA4Tokens(block.tokens)}</div>`;
                case 'text':
                    return `<div style="text-align:${block.align};font-size:${TEXT_SIZE[block.size]};font-weight:${block.bold ? 700 : 400};text-transform:${block.uppercase ? 'uppercase' : 'none'};margin:3px 0;white-space:pre-wrap;">${resolveA4Tokens(block.tokens)}</div>`;
                case 'keyValue':
                    return `<div style="display:flex;justify-content:space-between;gap:12px;margin:2px 0;"><span style="font-weight:${block.boldLabel ? 700 : 600};">${escapeHtml(block.label)}</span><span style="font-weight:${block.boldValue ? 700 : 400};">${escapeHtml(resolvePlaceholder(block.path))}</span></div>`;
                case 'divider': {
                    const w = block.weight === 'bold' ? '2px' : block.weight === 'medium' ? '1.5px' : '1px';

                    return `<hr style="border:none;border-top:${w} solid #111;margin:8px 0;" />`;
                }
                case 'image': {
                    const src = resolveImageSrc(block.sourceType, block.path, block.url);

                    return `<div style="text-align:${block.align};margin:6px 0;"><img src="${src}" alt="${escapeHtml(block.alt || 'Image')}" style="width:${block.width || 'auto'};max-height:${block.maxHeight || 'auto'};object-fit:contain;" /></div>`;
                }
                case 'headerBand': {
                    const border = block.showBorder ? 'padding-bottom:10px;border-bottom:1px solid #111;margin-bottom:10px;' : 'margin-bottom:10px;';

                    return `<div style="display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);gap:12px;align-items:start;${border}"><div>${renderHeaderSlotPreview(block.left)}</div><div style="display:flex;justify-content:center;">${renderHeaderSlotPreview(block.center)}</div><div>${renderHeaderSlotPreview(block.right)}</div></div>`;
                }
                case 'summaryPanel': {
                    const lines = block.rightLines
                        .map(
                            (line) =>
                                `<div style="display:flex;justify-content:space-between;gap:12px;padding:5px 8px;border-bottom:1px solid #ccc;font-weight:${line.bold ? 700 : 400};${line.bold ? 'background:#f3f3f3;' : ''}"><span>${escapeHtml(line.label)}</span><span style="text-align:right;">${escapeHtml(resolvePlaceholder(line.path))}</span></div>`,
                        )
                        .join('');

                    return `<div style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:16px;margin:10px 0;"><div><div style="font-weight:700;margin-bottom:4px;">${escapeHtml(block.leftLabel)}</div><div style="white-space:pre-wrap;">${resolveA4Tokens(block.leftTokens)}</div></div><div style="min-width:180px;border:1px solid #333;">${lines}</div></div>`;
                }
                case 'terms':
                    return `<div style="margin-top:12px;border-top:1px solid #999;padding-top:8px;"><div style="font-weight:700;font-size:11px;margin-bottom:4px;">${escapeHtml(block.title)}</div><div style="white-space:pre-wrap;font-size:${block.size === 'xs' ? '8px' : '9px'};line-height:1.4;color:#222;">${resolveA4Tokens(block.tokens)}</div></div>`;
                case 'spacer':
                    return `<div style="height:${block.height}px;"></div>`;
                case 'table': {
                    const tableStyle = [
                        'width:100%;border-collapse:collapse;margin:8px 0;',
                        block.compact ? 'font-size:10px;' : 'font-size:11px;',
                    ].join('');
                    const colgroup = block.columns.some((c) => c.width)
                        ? `<colgroup>${block.columns.map((c) => `<col style="width:${c.width || 'auto'};" />`).join('')}</colgroup>`
                        : '';
                    const headerCells = block.showHeader
                        ? block.columns
                              .map(
                                  (column) =>
                                      `<th style="text-align:${column.align};border:1px solid #333;padding:4px 6px;background:#ececec;${block.variant === 'invoice' ? 'font-size:10px;text-transform:uppercase;' : ''}">${escapeHtml(column.header)}</th>`,
                              )
                              .join('')
                        : '';
                    const headerRow = block.showHeader ? `<thead><tr>${headerCells}</tr></thead>` : '';
                    const rows = Array.from({ length: PREVIEW_ROWS }, (_, rowIndex) => {
                        const cells = block.columns
                            .map(
                                (column) =>
                                    `<td style="text-align:${column.align};border:1px solid #333;padding:${block.compact ? '3px 5px' : '4px 6px'};vertical-align:top;${columnAlignClass(column)}">${resolveColumnCell(column, rowIndex, block.arrayPath)}</td>`,
                            )
                            .join('');

                        return `<tr>${cells}</tr>`;
                    }).join('');

                    return `<table style="${tableStyle}">${colgroup}${headerRow}<tbody>${rows}</tbody></table>`;
                }
                default:
                    return '';
            }
        })
        .join('\n');

    return `${bgHtml}<div style="${previewStyles(pageSettings)};position:relative;z-index:1;">${body}</div>`;
}

export function renderA4PrintDocument(
    blocks: A4Block[],
    pageBackground?: A4PageBackground,
    pageSettings?: A4PageSettings,
): string {
    const body = renderA4PreviewHtml(blocks, pageBackground, pageSettings);
    const margin = pageSettings?.marginMm ?? 14;
    const font = pageSettings?.fontFamily ?? 'Arial';

    return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>A4 Print</title>
  <style>
    @page { size: A4; margin: ${margin}mm; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; background: #fff; color: #111; font-family: ${font}, Helvetica, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    img { max-width: 100%; }
  </style>
</head>
<body>${body}</body>
</html>`;
}
