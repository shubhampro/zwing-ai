import { createSnapshot, embedSnapshotInHtml } from '@/lib/template-builder/a4-export-meta';

export type A4Align = 'left' | 'center' | 'right';

export type A4ImageSourceType = 'variable' | 'url';

export type A4BackgroundSize = 'cover' | 'contain' | 'auto';

export type A4BackgroundPosition = 'center' | 'top' | 'bottom';

export type A4FontFamily = 'Arial' | 'Georgia' | 'Times New Roman';

export type A4TextSize = 'xs' | 'sm' | 'md' | 'lg';

export type A4TextToken =
    | { kind: 'literal'; value: string }
    | { kind: 'variable'; path: string };

export type A4ColumnMode = 'index' | 'variable' | 'static';

export type A4ColumnFormat = 'text' | 'number' | 'currency';

export type A4TableVariant = 'standard' | 'invoice' | 'tax';

export type A4Column = {
    id: string;
    header: string;
    mode: A4ColumnMode;
    /** Path relative to the loop item, e.g. `name`, `effectivePrice`. */
    path: string;
    /** Static cell text when mode is `static`. */
    staticValue: string;
    /** Multi-part cell content (overrides path when non-empty). */
    tokens: A4TextToken[];
    align: A4Align;
    /** CSS width e.g. `8%`, `120px`. */
    width: string;
    format: A4ColumnFormat;
};

export type A4HeadingBlock = {
    id: string;
    type: 'heading';
    align: A4Align;
    size: 'lg' | 'md' | 'sm';
    bold: boolean;
    uppercase: boolean;
    tokens: A4TextToken[];
};

export type A4TextBlock = {
    id: string;
    type: 'text';
    align: A4Align;
    size: A4TextSize;
    bold: boolean;
    uppercase: boolean;
    tokens: A4TextToken[];
};

export type A4KeyValueBlock = {
    id: string;
    type: 'keyValue';
    label: string;
    path: string;
    boldLabel: boolean;
    boldValue: boolean;
};

export type A4DividerBlock = {
    id: string;
    type: 'divider';
    weight: 'thin' | 'medium' | 'bold';
};

export type A4TableBlock = {
    id: string;
    type: 'table';
    arrayPath: string;
    columns: A4Column[];
    variant: A4TableVariant;
    compact: boolean;
    showHeader: boolean;
};

export type A4ImageBlock = {
    id: string;
    type: 'image';
    sourceType: A4ImageSourceType;
    path: string;
    url: string;
    align: A4Align;
    width: string;
    maxHeight: string;
    alt: string;
};

export type A4HeaderSlotType = 'text' | 'image';

export type A4HeaderSlot = {
    slotType: A4HeaderSlotType;
    align: A4Align;
    tokens: A4TextToken[];
    sourceType: A4ImageSourceType;
    path: string;
    url: string;
    width: string;
    maxHeight: string;
};

export type A4HeaderBandBlock = {
    id: string;
    type: 'headerBand';
    left: A4HeaderSlot;
    center: A4HeaderSlot;
    right: A4HeaderSlot;
    showBorder: boolean;
};

export type A4SummaryLine = {
    id: string;
    label: string;
    path: string;
    bold: boolean;
};

export type A4SummaryPanelBlock = {
    id: string;
    type: 'summaryPanel';
    leftLabel: string;
    leftTokens: A4TextToken[];
    rightLines: A4SummaryLine[];
};

export type A4TermsBlock = {
    id: string;
    type: 'terms';
    title: string;
    tokens: A4TextToken[];
    size: 'xs' | 'sm';
};

export type A4SpacerBlock = {
    id: string;
    type: 'spacer';
    height: number;
};

export type A4PageBackground = {
    enabled: boolean;
    sourceType: A4ImageSourceType;
    path: string;
    url: string;
    opacity: number;
    size: A4BackgroundSize;
    position: A4BackgroundPosition;
};

export type A4PageSettings = {
    marginMm: number;
    fontFamily: A4FontFamily;
    baseFontSize: number;
};

export type A4Block =
    | A4HeadingBlock
    | A4TextBlock
    | A4KeyValueBlock
    | A4DividerBlock
    | A4TableBlock
    | A4ImageBlock
    | A4HeaderBandBlock
    | A4SummaryPanelBlock
    | A4TermsBlock
    | A4SpacerBlock;

export type A4BlockType = A4Block['type'];

let uidCounter = 0;

function uid(prefix: string): string {
    uidCounter += 1;

    return `${prefix}-${Date.now().toString(36)}-${uidCounter}`;
}

export function createDefaultPageBackground(): A4PageBackground {
    return {
        enabled: false,
        sourceType: 'variable',
        path: 'printData.header.organizationDetails.orgLogo',
        url: '',
        opacity: 12,
        size: 'contain',
        position: 'center',
    };
}

export function createDefaultPageSettings(): A4PageSettings {
    return {
        marginMm: 14,
        fontFamily: 'Arial',
        baseFontSize: 12,
    };
}

export function createHeaderSlot(slotType: A4HeaderSlotType = 'text'): A4HeaderSlot {
    return {
        slotType,
        align: slotType === 'image' ? 'center' : 'left',
        tokens: slotType === 'text' ? [{ kind: 'literal', value: '' }] : [],
        sourceType: 'variable',
        path: 'printData.header.organizationDetails.orgLogo',
        url: '',
        width: slotType === 'image' ? '100px' : '',
        maxHeight: slotType === 'image' ? '60px' : '',
    };
}

export function createSummaryLine(label = 'Total', bold = false): A4SummaryLine {
    return { id: uid('sum'), label, path: '', bold };
}

export function createA4Column(mode: A4ColumnMode = 'variable'): A4Column {
    return {
        id: uid('col'),
        header: mode === 'index' ? '#' : mode === 'static' ? 'Label' : 'Column',
        mode,
        path: '',
        staticValue: '',
        tokens: [],
        align: mode === 'index' || mode === 'variable' ? 'left' : 'left',
        width: '',
        format: 'text',
    };
}

export function createA4Block(type: A4BlockType): A4Block {
    switch (type) {
        case 'heading':
            return {
                id: uid('heading'),
                type: 'heading',
                align: 'center',
                size: 'lg',
                bold: true,
                uppercase: false,
                tokens: [{ kind: 'literal', value: 'Tax Invoice' }],
            };
        case 'text':
            return {
                id: uid('text'),
                type: 'text',
                align: 'left',
                size: 'sm',
                bold: false,
                uppercase: false,
                tokens: [{ kind: 'literal', value: 'Text' }],
            };
        case 'keyValue':
            return {
                id: uid('kv'),
                type: 'keyValue',
                label: 'Label',
                path: '',
                boldLabel: true,
                boldValue: false,
            };
        case 'divider':
            return { id: uid('divider'), type: 'divider', weight: 'thin' };
        case 'table':
            return {
                id: uid('table'),
                type: 'table',
                arrayPath: '',
                columns: [createA4Column('index'), createA4Column('variable')],
                variant: 'standard',
                compact: false,
                showHeader: true,
            };
        case 'image':
            return {
                id: uid('image'),
                type: 'image',
                sourceType: 'variable',
                path: '',
                url: '',
                align: 'center',
                width: '120px',
                maxHeight: '80px',
                alt: 'Image',
            };
        case 'headerBand':
            return {
                id: uid('header'),
                type: 'headerBand',
                left: createHeaderSlot('text'),
                center: { ...createHeaderSlot('image'), align: 'center' },
                right: { ...createHeaderSlot('text'), align: 'right' },
                showBorder: true,
            };
        case 'summaryPanel':
            return {
                id: uid('summary'),
                type: 'summaryPanel',
                leftLabel: 'In words:',
                leftTokens: [{ kind: 'literal', value: '' }],
                rightLines: [
                    createSummaryLine('Gross Amount'),
                    createSummaryLine('Total GST'),
                    createSummaryLine('Net Amount', true),
                ],
            };
        case 'terms':
            return {
                id: uid('terms'),
                type: 'terms',
                title: 'Terms & Conditions',
                tokens: [{ kind: 'literal', value: 'Enter terms and conditions here…' }],
                size: 'xs',
            };
        case 'spacer':
            return { id: uid('spacer'), type: 'spacer', height: 16 };
    }
}

function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function renderTokens(tokens: A4TextToken[]): string {
    return tokens
        .map((token) =>
            token.kind === 'literal'
                ? escapeHtml(token.value)
                : `<%= ${token.path} %>`,
        )
        .join('');
}

function renderCellTokens(tokens: A4TextToken[]): string {
    return tokens
        .map((token) =>
            token.kind === 'literal'
                ? escapeHtml(token.value)
                : `<%= item.${token.path.split('.').pop() ?? token.path} %>`,
        )
        .join('');
}

const HEADING_SIZE: Record<A4HeadingBlock['size'], string> = {
    lg: '22px',
    md: '18px',
    sm: '15px',
};

const TEXT_SIZE: Record<A4TextSize, string> = {
    xs: '10px',
    sm: '12px',
    md: '13px',
    lg: '15px',
};

function imageSrcEjs(sourceType: A4ImageSourceType, path: string, url: string): string {
    if (sourceType === 'url') {
        return escapeHtml(url);
    }

    return `<%= ${path} %>`;
}

function renderImageHtml(
    sourceType: A4ImageSourceType,
    path: string,
    url: string,
    align: A4Align,
    width: string,
    maxHeight: string,
    alt: string,
): string {
    const src = imageSrcEjs(sourceType, path, url);

    return `<div class="tb-image-wrap" style="text-align:${align};"><img class="tb-image" src="${src}" alt="${escapeHtml(alt || 'Image')}" style="width:${width || 'auto'};max-height:${maxHeight || 'auto'};object-fit:contain;" /></div>`;
}

function renderImageBlock(block: A4ImageBlock): string {
    return `      ${renderImageHtml(block.sourceType, block.path, block.url, block.align, block.width, block.maxHeight, block.alt)}`;
}

function renderHeaderSlot(slot: A4HeaderSlot): string {
    if (slot.slotType === 'image') {
        return renderImageHtml(
            slot.sourceType,
            slot.path,
            slot.url,
            slot.align,
            slot.width,
            slot.maxHeight,
            'Logo',
        );
    }

    return `<div class="tb-header-text" style="text-align:${slot.align};">${renderTokens(slot.tokens)}</div>`;
}

function pageBackgroundStyle(background: A4PageBackground): string {
    if (!background.enabled) {
        return '';
    }

    const src =
        background.sourceType === 'url'
            ? escapeHtml(background.url)
            : `<%= ${background.path} %>`;
    const opacity = Math.min(100, Math.max(0, background.opacity)) / 100;

    return `background-image:url('${src}');background-size:${background.size};background-position:${background.position};background-repeat:no-repeat;opacity:${opacity};`;
}

function columnCellEjs(column: A4Column): string {
    if (column.mode === 'index') {
        return '<%= index + 1 %>';
    }

    if (column.mode === 'static') {
        return escapeHtml(column.staticValue);
    }

    if (column.tokens.length > 0) {
        return renderCellTokens(column.tokens);
    }

    return `<%= item.${column.path} %>`;
}

function columnClass(column: A4Column): string {
    const classes = [];

    if (column.format === 'number' || column.format === 'currency') {
        classes.push('tb-num');
    }

    return classes.join(' ');
}

function renderBlock(block: A4Block): string {
    switch (block.type) {
        case 'heading':
            return `      <div class="tb-heading" style="text-align:${block.align};font-size:${HEADING_SIZE[block.size]};font-weight:${block.bold ? '700' : '400'};text-transform:${block.uppercase ? 'uppercase' : 'none'};">${renderTokens(block.tokens)}</div>`;
        case 'text':
            return `      <div class="tb-text" style="text-align:${block.align};font-size:${TEXT_SIZE[block.size]};font-weight:${block.bold ? '700' : '400'};text-transform:${block.uppercase ? 'uppercase' : 'none'};">${renderTokens(block.tokens)}</div>`;
        case 'keyValue':
            return `      <div class="tb-kv"><span class="tb-kv-label" style="font-weight:${block.boldLabel ? '700' : '600'};">${escapeHtml(block.label)}</span><span class="tb-kv-value" style="font-weight:${block.boldValue ? '700' : '400'};"><%= ${block.path} %></span></div>`;
        case 'divider': {
            const weight = block.weight === 'bold' ? '2px' : block.weight === 'medium' ? '1.5px' : '1px';

            return `      <hr class="tb-divider" style="border-top-width:${weight};" />`;
        }
        case 'image':
            return renderImageBlock(block);
        case 'headerBand': {
            const border = block.showBorder ? ' tb-header-band--border' : '';

            return [
                `      <div class="tb-header-band${border}">`,
                `        <div class="tb-header-slot tb-header-slot--left">${renderHeaderSlot(block.left)}</div>`,
                `        <div class="tb-header-slot tb-header-slot--center">${renderHeaderSlot(block.center)}</div>`,
                `        <div class="tb-header-slot tb-header-slot--right">${renderHeaderSlot(block.right)}</div>`,
                '      </div>',
            ].join('\n');
        }
        case 'summaryPanel': {
            const lines = block.rightLines
                .map(
                    (line) =>
                        `          <div class="tb-summary-row${line.bold ? ' tb-summary-row--bold' : ''}"><span>${escapeHtml(line.label)}</span><span class="tb-num"><%= ${line.path} %></span></div>`,
                )
                .join('\n');

            return [
                '      <div class="tb-summary-panel">',
                '        <div class="tb-summary-left">',
                `          <div class="tb-summary-left-label">${escapeHtml(block.leftLabel)}</div>`,
                `          <div class="tb-summary-left-value">${renderTokens(block.leftTokens)}</div>`,
                '        </div>',
                '        <div class="tb-summary-right">',
                lines,
                '        </div>',
                '      </div>',
            ].join('\n');
        }
        case 'terms':
            return [
                '      <div class="tb-terms">',
                `        <div class="tb-terms-title">${escapeHtml(block.title)}</div>`,
                `        <div class="tb-terms-body tb-terms-body--${block.size}">${renderTokens(block.tokens)}</div>`,
                '      </div>',
            ].join('\n');
        case 'spacer':
            return `      <div class="tb-spacer" style="height:${block.height}px;"></div>`;
        case 'table': {
            const tableClass = [
                'tb-table',
                block.variant !== 'standard' ? `tb-table--${block.variant}` : '',
                block.compact ? 'tb-table--compact' : '',
            ]
                .filter(Boolean)
                .join(' ');
            const colgroup = block.columns.some((column) => column.width)
                ? `        <colgroup>${block.columns.map((column) => `<col style="width:${column.width || 'auto'};" />`).join('')}</colgroup>\n`
                : '';
            const headerCells = block.showHeader
                ? block.columns
                      .map(
                          (column) =>
                              `<th class="${columnClass(column)}" style="text-align:${column.align};">${escapeHtml(column.header)}</th>`,
                      )
                      .join('')
                : '';
            const headerRow = block.showHeader ? `        <thead><tr>${headerCells}</tr></thead>\n` : '';
            const bodyCells = block.columns
                .map((column) => {
                    const content = columnCellEjs(column);

                    return `<td class="${columnClass(column)}" style="text-align:${column.align};">${content}</td>`;
                })
                .join('');

            return [
                `      <table class="${tableClass}" data-tb-array-path="${escapeHtml(block.arrayPath)}">`,
                colgroup,
                headerRow,
                '        <tbody>',
                `        <% (${block.arrayPath} || []).forEach(function(item, index){ %>`,
                `          <tr>${bodyCells}</tr>`,
                '        <% }); %>',
                '        </tbody>',
                '      </table>',
            ].join('\n');
        }
    }
}

function baseStyles(settings: A4PageSettings): string {
    return `
    * { box-sizing: border-box; }
    body { font-family: ${settings.fontFamily}, Helvetica, sans-serif; color: #111; margin: 0; font-size: ${settings.baseFontSize}px; }
    .tb-page { position: relative; width: 210mm; min-height: 297mm; margin: 0 auto; padding: ${settings.marginMm}mm; }
    .tb-page-bg { position: absolute; inset: 0; z-index: 0; pointer-events: none; }
    .tb-page-content { position: relative; z-index: 1; }
    .tb-heading { font-weight: 700; margin: 6px 0; line-height: 1.25; }
    .tb-text { margin: 3px 0; line-height: 1.45; white-space: pre-wrap; }
    .tb-kv { display: flex; justify-content: space-between; gap: 12px; font-size: ${settings.baseFontSize}px; margin: 2px 0; }
    .tb-kv-label { font-weight: 600; }
    .tb-divider { border: none; border-top: 1px solid #111; margin: 8px 0; }
    .tb-image-wrap { line-height: 0; margin: 4px 0; }
    .tb-image { display: inline-block; vertical-align: middle; }
    .tb-header-band { display: grid; grid-template-columns: minmax(0,1fr) auto minmax(0,1fr); gap: 14px; align-items: start; margin-bottom: 10px; }
    .tb-header-band--border { padding-bottom: 10px; border-bottom: 1px solid #111; }
    .tb-header-slot { min-width: 0; font-size: ${settings.baseFontSize}px; line-height: 1.4; }
    .tb-header-slot--center { display: flex; justify-content: center; }
    .tb-header-slot--right { text-align: right; }
    .tb-header-text { white-space: pre-wrap; }
    .tb-table { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: ${Math.max(10, settings.baseFontSize - 1)}px; }
    .tb-table th, .tb-table td { border: 1px solid #333; padding: 5px 6px; vertical-align: top; }
    .tb-table thead { background: #ececec; }
    .tb-table--invoice thead th { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; }
    .tb-table--tax { font-size: 10px; }
    .tb-table--tax thead { background: #f5f5f5; }
    .tb-table--compact th, .tb-table--compact td { padding: 3px 5px; font-size: 10px; }
    .tb-num { text-align: right; font-variant-numeric: tabular-nums; }
    .tb-summary-panel { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: 20px; align-items: start; margin: 10px 0 12px; }
    .tb-summary-left-label { font-weight: 700; font-size: ${settings.baseFontSize}px; margin-bottom: 4px; }
    .tb-summary-left-value { font-size: ${settings.baseFontSize}px; line-height: 1.45; white-space: pre-wrap; }
    .tb-summary-right { min-width: 200px; border: 1px solid #333; }
    .tb-summary-row { display: flex; justify-content: space-between; gap: 16px; padding: 5px 8px; border-bottom: 1px solid #ccc; font-size: ${settings.baseFontSize}px; }
    .tb-summary-row:last-child { border-bottom: none; }
    .tb-summary-row--bold { font-weight: 700; background: #f3f3f3; }
    .tb-terms { margin-top: 14px; border-top: 1px solid #999; padding-top: 10px; }
    .tb-terms-title { font-weight: 700; font-size: 11px; margin-bottom: 6px; }
    .tb-terms-body { white-space: pre-wrap; line-height: 1.4; color: #222; }
    .tb-terms-body--xs { font-size: 8px; }
    .tb-terms-body--sm { font-size: 9px; }
    .tb-spacer { width: 100%; }
    @media print { .tb-page { margin: 0; } }`;
}

/** Generates a self-contained A4 EJS/HTML document from the block list. */
export function generateEjs(
    blocks: A4Block[],
    pageBackground: A4PageBackground = createDefaultPageBackground(),
    pageSettings: A4PageSettings = createDefaultPageSettings(),
): string {
    const body = blocks.map(renderBlock).join('\n');
    const bgLayer = pageBackground.enabled
        ? `  <div class="tb-page-bg" style="${pageBackgroundStyle(pageBackground)}"></div>\n`
        : '';

    const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Invoice</title>
  <style>${baseStyles(pageSettings)}
  </style>
</head>
<body>
  <div class="tb-page">
${bgLayer}    <div class="tb-page-content">
${body}
    </div>
  </div>
</body>
</html>
`;

    return embedSnapshotInHtml(html, createSnapshot(blocks, pageBackground, pageSettings));
}
