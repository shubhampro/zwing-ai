import {
    createA4Column,
    createDefaultPageBackground,
    createDefaultPageSettings,
    createHeaderSlot,
    createSummaryLine,
    type A4Align,
    type A4Block,
    type A4Column,
    type A4ColumnFormat,
    type A4FontFamily,
    type A4HeaderSlot,
    type A4PageBackground,
    type A4PageSettings,
    type A4TableVariant,
    type A4TextSize,
    type A4TextToken,
} from '@/lib/template-builder/a4';
import { extractSnapshotFromSource } from '@/lib/template-builder/a4-export-meta';
import { shouldUseEjsSourceMode } from '@/lib/template-builder/a4-ejs-preview';
import { parseProductionEjsBlocks } from '@/lib/template-builder/a4-production-import';
import { extractProductionSegments, type ProductionSegment } from '@/lib/template-builder/a4-production-sync';

export type A4ImportResult = {
    mode: 'blocks' | 'ejs';
    blocks: A4Block[];
    pageBackground: A4PageBackground;
    pageSettings: A4PageSettings;
    ejsSource: string;
    productionSegments: ProductionSegment[];
};

let importUid = 0;

function importId(prefix: string): string {
    importUid += 1;

    return `import-${prefix}-${importUid}`;
}

function decodeHtml(value: string): string {
    return value
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"');
}

function styleValue(style: string | null, property: string): string {
    if (!style) {
        return '';
    }

    const match = style.match(new RegExp(`${property}\\s*:\\s*([^;]+)`, 'i'));

    return match?.[1]?.trim() ?? '';
}

function parseAlign(value: string): A4Align {
    if (value === 'center' || value === 'right') {
        return value;
    }

    return 'left';
}

function stripEjsForDom(html: string): string {
    return html
        .replace(/<%=\s*([\s\S]+?)\s*%>/g, (_, expression: string) => {
            return `__EJS__${encodeURIComponent(expression.trim())}__`;
        })
        .replace(/<%-\s*([\s\S]+?)\s*%>/g, (_, expression: string) => {
            return `__EJS__${encodeURIComponent(expression.trim())}__`;
        })
        .replace(/<%[\s\S]*?%>/g, '');
}

function extractForEachPaths(source: string): string[] {
    const paths: string[] = [];
    const patterns = [
        /\(([^)]+?)\s*\|\|\s*\[\]\)\s*\.forEach/g,
        /<%[\s\S]*?\b((?:printData\.)?[\w.]+)\s*(?:\|\|\s*\[\])?\s*\.forEach\s*\(/g,
    ];

    for (const pattern of patterns) {
        let match = pattern.exec(source);

        while (match) {
            const path = match[1].trim();

            if (path && !paths.includes(path)) {
                paths.push(path);
            }

            match = pattern.exec(source);
        }
    }

    return paths;
}

const TB_BLOCK_SELECTOR = [
    '.tb-header-band',
    '.tb-table',
    'table.tb-table',
    '.tb-summary-panel',
    '.tb-terms',
    '.tb-spacer',
    '.tb-heading',
    '.tb-text',
    '.tb-kv',
    '.tb-image-wrap',
    'hr.tb-divider',
].join(', ');

function isTbBlockElement(element: Element): boolean {
    return (
        element.classList.contains('tb-header-band') ||
        element.classList.contains('tb-table') ||
        element.classList.contains('tb-summary-panel') ||
        element.classList.contains('tb-terms') ||
        element.classList.contains('tb-spacer') ||
        element.classList.contains('tb-heading') ||
        element.classList.contains('tb-text') ||
        element.classList.contains('tb-kv') ||
        element.classList.contains('tb-image-wrap') ||
        element.matches('hr.tb-divider')
    );
}

function shouldSkipElement(element: Element): boolean {
    const tag = element.tagName;

    return tag === 'STYLE' || tag === 'SCRIPT' || tag === 'HEAD' || tag === 'META' || tag === 'LINK' || element.classList.contains('tb-page-bg');
}

function hasVisibleContent(element: Element): boolean {
    const text = element.textContent?.replace(/\s+/g, ' ').trim() ?? '';
    const html = element.innerHTML;

    return text.length > 0 || html.includes('__EJS__') || html.includes('<%=');
}

function findDirectBlockElements(doc: Document): Element[] {
    const content =
        doc.querySelector('.tb-page-content') ??
        doc.querySelector('.tb-page') ??
        doc.body;

    if (!content) {
        return [];
    }

    if (content.classList.contains('tb-page-content') || content.classList.contains('tb-page')) {
        return [...content.children].filter((child) => !child.classList.contains('tb-page-bg'));
    }

    return [...content.children].filter((child) => !shouldSkipElement(child));
}

function collectGenericBlockElements(root: Element): Element[] {
    const results: Element[] = [];
    const consumed = new Set<Element>();

    function walk(parent: Element): void {
        for (const child of parent.children) {
            if (shouldSkipElement(child)) {
                continue;
            }

            if (isTbBlockElement(child)) {
                if (!consumed.has(child)) {
                    results.push(child);
                    consumed.add(child);
                }

                continue;
            }

            if (child.classList.contains('tb-page') || child.classList.contains('tb-page-content')) {
                walk(child);

                continue;
            }

            const nestedTb = child.querySelector(TB_BLOCK_SELECTOR);

            if (nestedTb && child.tagName !== 'TABLE' && !/^H[1-6]$/.test(child.tagName)) {
                walk(child);

                continue;
            }

            if (child.tagName === 'TABLE' || /^H[1-6]$/.test(child.tagName) || child.tagName === 'HR' || child.tagName === 'IMG') {
                if (!consumed.has(child)) {
                    results.push(child);
                    consumed.add(child);
                }

                continue;
            }

            if ((child.tagName === 'DIV' || child.tagName === 'P') && hasVisibleContent(child)) {
                const nestedTable = child.querySelector('table');
                const nestedHeading = child.querySelector('h1,h2,h3,h4,h5,h6');

                if (nestedTable || nestedHeading) {
                    walk(child);

                    continue;
                }

                if (!consumed.has(child)) {
                    results.push(child);
                    consumed.add(child);
                }

                continue;
            }

            if (child.children.length > 0) {
                walk(child);
            } else if (hasVisibleContent(child) && !consumed.has(child)) {
                results.push(child);
                consumed.add(child);
            }
        }
    }

    walk(root);

    return results;
}

function findTbBlocksDeep(doc: Document): Element[] {
    return [...doc.querySelectorAll(TB_BLOCK_SELECTOR)];
}

function readEjsExpression(value: string): string {
    const match = value.match(/__EJS__(.*?)__/);

    if (match) {
        return decodeURIComponent(match[1]);
    }

    return value.replace(/^<%=|%>$/g, '').trim();
}

function parseTokensFromHtml(html: string): A4TextToken[] {
    const tokens: A4TextToken[] = [];
    const parts = html.split(/(__EJS__.*?__)/g);

    for (const part of parts) {
        if (!part) {
            continue;
        }

        if (part.startsWith('__EJS__')) {
            tokens.push({
                kind: 'variable',
                path: readEjsExpression(part),
            });

            continue;
        }

        const text = decodeHtml(part.replace(/<[^>]+>/g, ''));

        if (text.length > 0) {
            tokens.push({ kind: 'literal', value: text });
        }
    }

    return tokens;
}

function extractDocumentHtml(source: string): string {
    const trimmed = source.trim();

    if (trimmed.includes('<html')) {
        return stripEjsForDom(trimmed);
    }

    return stripEjsForDom(`<!DOCTYPE html><html><head></head><body>${trimmed}</body></html>`);
}

function parsePageSettings(doc: Document): A4PageSettings {
    const settings = createDefaultPageSettings();
    const styleText = doc.querySelector('style')?.textContent ?? '';

    const marginMatch = styleText.match(/\.tb-page\s*\{[^}]*padding:\s*([0-9.]+)mm/i);
    const fontMatch = styleText.match(/body\s*\{[^}]*font-family:\s*([^;]+);/i);
    const sizeMatch = styleText.match(/body\s*\{[^}]*font-size:\s*([0-9.]+)px/i);

    if (marginMatch) {
        settings.marginMm = Number(marginMatch[1]) || settings.marginMm;
    }

    if (fontMatch) {
        const font = fontMatch[1].trim().replace(/,.*$/, '') as A4FontFamily;

        if (font === 'Arial' || font === 'Georgia' || font === 'Times New Roman') {
            settings.fontFamily = font;
        }
    }

    if (sizeMatch) {
        settings.baseFontSize = Number(sizeMatch[1]) || settings.baseFontSize;
    }

    return settings;
}

function parsePageBackground(doc: Document): A4PageBackground {
    const background = createDefaultPageBackground();
    const layer = doc.querySelector('.tb-page-bg');

    if (!layer) {
        return background;
    }

    const style = layer.getAttribute('style') ?? '';
    background.enabled = true;
    background.opacity = Math.round(Number(styleValue(style, 'opacity') || 0.1) * 100);

    const size = styleValue(style, 'background-size');

    if (size === 'cover' || size === 'contain' || size === 'auto') {
        background.size = size;
    }

    const position = styleValue(style, 'background-position');

    if (position === 'center' || position === 'top' || position === 'bottom') {
        background.position = position;
    }

    const imageMatch = style.match(/background-image:url\('([^']*)'\)/i);

    if (imageMatch) {
        const src = imageMatch[1];

        if (src.startsWith('__EJS__')) {
            background.sourceType = 'variable';
            background.path = readEjsExpression(src);
        } else if (src.includes('data-ejs') || src.startsWith('<%= ')) {
            background.sourceType = 'variable';
            background.path = src.replace(/^<%=|%>$/g, '').trim();
        } else {
            background.sourceType = 'url';
            background.url = decodeHtml(src);
        }
    }

    return background;
}

function parseHeaderSlot(element: Element | null, fallbackAlign: A4Align): A4HeaderSlot {
    if (!element) {
        return createHeaderSlot('text');
    }

    const image = element.querySelector('img');

    if (image) {
        const wrap = image.closest('.tb-image-wrap');
        const style = wrap?.getAttribute('style') ?? '';
        const src = image.getAttribute('src') ?? '';

        return {
            slotType: 'image',
            align: parseAlign(styleValue(style, 'text-align') || fallbackAlign),
            tokens: [],
            sourceType: src.startsWith('http') ? 'url' : 'variable',
            path: src.startsWith('http') ? '' : readEjsExpression(src),
            url: src.startsWith('http') ? src : '',
            width: styleValue(image.getAttribute('style'), 'width') || '90px',
            maxHeight: styleValue(image.getAttribute('style'), 'max-height') || '60px',
        };
    }

    const text = element.querySelector('.tb-header-text');

    return {
        slotType: 'text',
        align: parseAlign(styleValue(text?.getAttribute('style') ?? '', 'text-align') || fallbackAlign),
        tokens: parseTokensFromHtml(text?.innerHTML ?? element.innerHTML),
        sourceType: 'variable',
        path: '',
        url: '',
        width: '',
        maxHeight: '',
    };
}

function parseColumnCell(html: string): Pick<A4Column, 'mode' | 'path' | 'staticValue' | 'tokens'> {
    const trimmed = html.trim();

    if (trimmed.includes('data-ejs="index + 1"') || trimmed.includes('<%= index + 1 %>') || trimmed.includes('__EJS__index%20%2B%201__')) {
        return { mode: 'index', path: '', staticValue: '', tokens: [] };
    }

    const tokens = parseTokensFromHtml(trimmed);

    if (tokens.length === 1 && tokens[0].kind === 'variable') {
        const path = tokens[0].path.replace(/^item\./, '');

        return { mode: 'variable', path, staticValue: '', tokens: [] };
    }

    if (tokens.some((token) => token.kind === 'variable')) {
        return {
            mode: 'variable',
            path: '',
            staticValue: '',
            tokens: tokens.map((token) =>
                token.kind === 'variable'
                    ? { kind: 'variable', path: token.path.replace(/^item\./, '') }
                    : token,
            ),
        };
    }

    return { mode: 'static', path: '', staticValue: decodeHtml(trimmed.replace(/<[^>]+>/g, '')), tokens: [] };
}

type ParseContext = {
    arrayPaths: string[];
    tableIndex: number;
};

function parseGenericHeading(element: Element): A4Block {
    const tag = element.tagName;

    return {
        id: importId('heading'),
        type: 'heading',
        align: parseAlign(styleValue(element.getAttribute('style'), 'text-align')),
        size: tag === 'H1' ? 'lg' : tag === 'H2' ? 'md' : 'sm',
        bold: true,
        uppercase: styleValue(element.getAttribute('style'), 'text-transform') === 'uppercase',
        tokens: parseTokensFromHtml(element.innerHTML),
    };
}

function parseGenericText(element: Element): A4Block | null {
    const tokens = parseTokensFromHtml(element.innerHTML);

    if (tokens.length === 0) {
        return null;
    }

    const style = element.getAttribute('style') ?? '';
    const fontSize = styleValue(style, 'font-size');
    const sizeMap: Record<string, A4TextSize> = {
        '10px': 'xs',
        '11px': 'xs',
        '12px': 'sm',
        '13px': 'md',
        '15px': 'lg',
    };

    return {
        id: importId('text'),
        type: 'text',
        align: parseAlign(styleValue(style, 'text-align')),
        size: sizeMap[fontSize] ?? 'sm',
        bold: styleValue(style, 'font-weight') === '700' || element.tagName.startsWith('B'),
        uppercase: styleValue(style, 'text-transform') === 'uppercase',
        tokens,
    };
}

function parseGenericImage(element: Element): A4Block | null {
    const image = element.tagName === 'IMG' ? element : element.querySelector('img');

    if (!image) {
        return null;
    }

    const wrap = image.closest('.tb-image-wrap') ?? image.parentElement ?? element;
    const style = wrap?.getAttribute('style') ?? '';
    const src = image.getAttribute('src') ?? '';

    return {
        id: importId('image'),
        type: 'image',
        sourceType: src.startsWith('http') ? 'url' : 'variable',
        path: src.startsWith('http') ? '' : readEjsExpression(src),
        url: src.startsWith('http') ? src : '',
        align: parseAlign(styleValue(style, 'text-align')),
        width: styleValue(image.getAttribute('style'), 'width') || '120px',
        maxHeight: styleValue(image.getAttribute('style'), 'max-height') || '80px',
        alt: image.getAttribute('alt') ?? 'Image',
    };
}

function parseTableBlock(element: Element, context: ParseContext): A4Block | null {
    const block = parseTable(element);

    if (!block) {
        return null;
    }

    if (!block.arrayPath) {
        block.arrayPath = context.arrayPaths[context.tableIndex] ?? '';
    }

    context.tableIndex += 1;

    return block;
}

function parseTable(element: Element): A4Block | null {
    const className = typeof element.className === 'string' ? element.className : '';
    const variant: A4TableVariant = className.includes('tb-table--invoice')
        ? 'invoice'
        : className.includes('tb-table--tax')
          ? 'tax'
          : 'standard';
    const compact = className.includes('tb-table--compact');
    const showHeader = element.querySelector('thead') !== null;

    const loopMatch = element.innerHTML.match(/\(([^)]+)\s*\|\|\s*\[\]\)\.forEach/);
    const arrayPath =
        element.getAttribute('data-tb-array-path')?.trim() ?? loopMatch?.[1]?.trim() ?? '';

    const columns: A4Column[] = [];
    const headerCells = element.querySelectorAll('thead th');

    headerCells.forEach((cell, index) => {
        const align = parseAlign(styleValue(cell.getAttribute('style'), 'text-align'));
        const format: A4ColumnFormat = cell.classList.contains('tb-num') ? 'currency' : 'text';
        const width =
            element.querySelectorAll('colgroup col')[index]?.getAttribute('style')?.match(/width:\s*([^;]+)/)?.[1]?.trim() ??
            '';

        columns.push({
            id: importId('col'),
            header: decodeHtml(cell.textContent ?? ''),
            mode: 'variable',
            path: '',
            staticValue: '',
            tokens: [],
            align,
            width,
            format,
        });
    });

    const bodyRow = element.querySelector('tbody tr') ?? element.querySelector('tr');

    if (bodyRow && columns.length === 0) {
        bodyRow.querySelectorAll('td, th').forEach((cell, index) => {
            const parsed = parseColumnCell(cell.innerHTML);

            columns.push({
                id: importId('col'),
                header: `Column ${index + 1}`,
                ...parsed,
                align: parseAlign(styleValue(cell.getAttribute('style'), 'text-align')),
                width: '',
                format: cell.classList.contains('tb-num') ? 'currency' : 'text',
            });
        });

        return {
            id: importId('table'),
            type: 'table',
            arrayPath,
            columns,
            variant,
            compact,
            showHeader: false,
        };
    }

    if (bodyRow) {
        bodyRow.querySelectorAll('td').forEach((cell, index) => {
            const parsed = parseColumnCell(cell.innerHTML);
            const column = columns[index] ?? createA4Column('variable');

            columns[index] = {
                ...column,
                ...parsed,
                id: column.id ?? importId('col'),
                header: column.header || `Column ${index + 1}`,
                align: parseAlign(styleValue(cell.getAttribute('style'), 'text-align')) || column.align,
                format: cell.classList.contains('tb-num') ? 'currency' : column.format,
            };
        });
    }

    if (columns.length === 0) {
        return null;
    }

    return {
        id: importId('table'),
        type: 'table',
        arrayPath,
        columns,
        variant,
        compact,
        showHeader,
    };
}

function elementToBlock(element: Element, context: ParseContext): A4Block | null {
    if (element.classList.contains('tb-header-band')) {
        return {
            id: importId('header'),
            type: 'headerBand',
            showBorder: element.classList.contains('tb-header-band--border'),
            left: parseHeaderSlot(element.querySelector('.tb-header-slot--left'), 'left'),
            center: parseHeaderSlot(element.querySelector('.tb-header-slot--center'), 'center'),
            right: parseHeaderSlot(element.querySelector('.tb-header-slot--right'), 'right'),
        };
    }

    if (element.classList.contains('tb-table') || element.tagName === 'TABLE') {
        return parseTableBlock(element, context);
    }

    if (element.classList.contains('tb-summary-panel')) {
        const lines = [...element.querySelectorAll('.tb-summary-row')].map((row) => {
            const spans = row.querySelectorAll('span');
            const label = decodeHtml(spans[0]?.textContent ?? '');
            const pathSpan = spans[1];
            const path = pathSpan ? readEjsExpression(pathSpan.textContent ?? '') : '';

            return {
                ...createSummaryLine(label, row.classList.contains('tb-summary-row--bold')),
                path,
            };
        });

        return {
            id: importId('summary'),
            type: 'summaryPanel',
            leftLabel: decodeHtml(element.querySelector('.tb-summary-left-label')?.textContent ?? 'In words:'),
            leftTokens: parseTokensFromHtml(element.querySelector('.tb-summary-left-value')?.innerHTML ?? ''),
            rightLines: lines,
        };
    }

    if (element.classList.contains('tb-terms')) {
        const body = element.querySelector('.tb-terms-body');

        return {
            id: importId('terms'),
            type: 'terms',
            title: decodeHtml(element.querySelector('.tb-terms-title')?.textContent ?? 'Terms & Conditions'),
            size: body?.classList.contains('tb-terms-body--sm') ? 'sm' : 'xs',
            tokens: parseTokensFromHtml(body?.innerHTML ?? ''),
        };
    }

    if (element.classList.contains('tb-spacer')) {
        const height = Number(styleValue(element.getAttribute('style'), 'height').replace('px', '')) || 16;

        return { id: importId('spacer'), type: 'spacer', height };
    }

    if (element.classList.contains('tb-heading')) {
        const style = element.getAttribute('style') ?? '';
        const fontSize = styleValue(style, 'font-size');

        return {
            id: importId('heading'),
            type: 'heading',
            align: parseAlign(styleValue(style, 'text-align')),
            size: fontSize === '22px' ? 'lg' : fontSize === '15px' ? 'sm' : 'md',
            bold: styleValue(style, 'font-weight') === '700',
            uppercase: styleValue(style, 'text-transform') === 'uppercase',
            tokens: parseTokensFromHtml(element.innerHTML),
        };
    }

    if (element.classList.contains('tb-text')) {
        const style = element.getAttribute('style') ?? '';
        const fontSize = styleValue(style, 'font-size');
        const sizeMap: Record<string, A4TextSize> = {
            '10px': 'xs',
            '12px': 'sm',
            '13px': 'md',
            '15px': 'lg',
        };

        return {
            id: importId('text'),
            type: 'text',
            align: parseAlign(styleValue(style, 'text-align')),
            size: sizeMap[fontSize] ?? 'sm',
            bold: styleValue(style, 'font-weight') === '700',
            uppercase: styleValue(style, 'text-transform') === 'uppercase',
            tokens: parseTokensFromHtml(element.innerHTML),
        };
    }

    if (element.classList.contains('tb-kv')) {
        const label = element.querySelector('.tb-kv-label');
        const value = element.querySelector('.tb-kv-value');
        const path = value ? readEjsExpression(value.textContent ?? '') : '';

        return {
            id: importId('kv'),
            type: 'keyValue',
            label: decodeHtml(label?.textContent ?? 'Label'),
            path,
            boldLabel: styleValue(label?.getAttribute('style') ?? '', 'font-weight') === '700',
            boldValue: styleValue(value?.getAttribute('style') ?? '', 'font-weight') === '700',
        };
    }

    if (element.classList.contains('tb-image-wrap')) {
        const image = element.querySelector('img');
        const style = element.getAttribute('style') ?? '';
        const src = image?.getAttribute('src') ?? '';

        return {
            id: importId('image'),
            type: 'image',
            sourceType: src.startsWith('http') ? 'url' : 'variable',
            path: src.startsWith('http') ? '' : readEjsExpression(src),
            url: src.startsWith('http') ? src : '',
            align: parseAlign(styleValue(style, 'text-align')),
            width: styleValue(image?.getAttribute('style') ?? '', 'width') || '120px',
            maxHeight: styleValue(image?.getAttribute('style') ?? '', 'max-height') || '80px',
            alt: image?.getAttribute('alt') ?? 'Image',
        };
    }

    if (element.matches('hr.tb-divider') || element.tagName === 'HR') {
        const width = styleValue(element.getAttribute('style'), 'border-top-width');

        return {
            id: importId('divider'),
            type: 'divider',
            weight: width === '2px' ? 'bold' : width === '1.5px' ? 'medium' : 'thin',
        };
    }

    if (/^H[1-6]$/.test(element.tagName)) {
        return parseGenericHeading(element);
    }

    if (element.tagName === 'IMG' || element.querySelector('img')) {
        return parseGenericImage(element);
    }

    return parseGenericText(element);
}

function resolveBlockElements(doc: Document): Element[] {
    const direct = findDirectBlockElements(doc);

    if (direct.length > 0) {
        const parsed = direct.filter(
            (element) => isTbBlockElement(element) || element.tagName === 'TABLE' || /^H[1-6]$/.test(element.tagName),
        );

        if (parsed.length > 0) {
            return direct;
        }
    }

    const generic = collectGenericBlockElements(doc.body);

    if (generic.length > 0) {
        return generic;
    }

    const deep = findTbBlocksDeep(doc);

    if (deep.length > 0) {
        return deep;
    }

    return direct;
}

/** Parses EJS/HTML exported by this builder into editable blocks (best effort). */
export function parseA4Ejs(source: string): A4ImportResult {
    importUid = 0;

    const ejsSource = source.trim().replace(/^\uFEFF/, '');

    if (shouldUseEjsSourceMode(ejsSource)) {
        const production = parseProductionEjsBlocks(ejsSource);

        if (production.blocks.length > 0) {
            return {
                mode: 'blocks',
                blocks: production.blocks,
                pageBackground: createDefaultPageBackground(),
                pageSettings: production.pageSettings,
                ejsSource,
                productionSegments: extractProductionSegments(ejsSource, production.blocks),
            };
        }
    }

    const embedded = extractSnapshotFromSource(ejsSource);

    if (embedded && embedded.blocks.length > 0) {
        return {
            mode: 'blocks',
            blocks: embedded.blocks,
            pageBackground: embedded.pageBackground,
            pageSettings: embedded.pageSettings,
            ejsSource,
            productionSegments: [],
        };
    }

    const arrayPaths = extractForEachPaths(ejsSource);
    const html = extractDocumentHtml(ejsSource);
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const pageSettings = parsePageSettings(doc);
    const pageBackground = parsePageBackground(doc);
    const blockElements = resolveBlockElements(doc);
    const context: ParseContext = { arrayPaths, tableIndex: 0 };

    const blocks: A4Block[] = [];

    for (const child of blockElements) {
        const block = elementToBlock(child, context);

        if (block) {
            blocks.push(block);
        }
    }

    if (blocks.length === 0) {
        return {
            mode: 'ejs',
            blocks: [],
            pageBackground: createDefaultPageBackground(),
            pageSettings: createDefaultPageSettings(),
            ejsSource,
            productionSegments: [],
        };
    }

    return {
        mode: 'blocks',
        blocks,
        pageBackground,
        pageSettings,
        ejsSource,
        productionSegments: [],
    };
}
