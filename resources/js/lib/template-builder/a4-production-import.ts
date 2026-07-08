import {
    createA4Column,
    createDefaultPageSettings,
    createHeaderSlot,
    type A4Align,
    type A4Block,
    type A4Column,
    type A4ColumnFormat,
    type A4PageSettings,
    type A4TableVariant,
    type A4TextToken,
} from '@/lib/template-builder/a4';

let productionUid = 0;

function pid(prefix: string): string {
    productionUid += 1;

    return `prod-${prefix}-${productionUid}`;
}

function decodeHtml(value: string): string {
    return value
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"');
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

function readEjsExpression(value: string): string {
    const match = value.match(/__EJS__(.*?)__/);

    if (match) {
        return normalizeVariablePath(decodeURIComponent(match[1]));
    }

    return normalizeVariablePath(value.replace(/^<%=|%>$/g, '').trim());
}

function normalizeVariablePath(path: string): string {
    return path
        .replace(/\?\./g, '.')
        .replace(/printData\.header\./g, 'printData.header.')
        .replace(/^header\./, 'printData.header.')
        .replace(/\s*\|\|\s*['"`][^'"`]*['"`]/g, '')
        .replace(/\s*\|\|\s*0/g, '')
        .replace(/\s*\|\|\s*\[\]/g, '')
        .trim();
}

function parseTokensFromHtml(html: string): A4TextToken[] {
    const tokens: A4TextToken[] = [];
    const parts = html.split(/(__EJS__.*?__)/g);

    for (const part of parts) {
        if (!part) {
            continue;
        }

        if (part.startsWith('__EJS__')) {
            const path = readEjsExpression(part);

            if (path && !path.includes('new Date') && !path.includes('parseInt') && !path.includes('Math.')) {
                tokens.push({ kind: 'variable', path });
            }

            continue;
        }

        const text = decodeHtml(part.replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]+>/g, ''));

        if (text.length > 0) {
            tokens.push({ kind: 'literal', value: text });
        }
    }

    return tokens;
}

function parseAlignFromStyle(style: string | null): A4Align {
    const value = style?.match(/text-align:\s*(left|center|right)/i)?.[1];

    if (value === 'center' || value === 'right') {
        return value;
    }

    return 'left';
}

function slotFromElement(element: Element | null, fallback: A4Align = 'left') {
    const slot = createHeaderSlot('text');
    const align = parseAlignFromStyle(element?.getAttribute('style') ?? '') || fallback;

    return {
        ...slot,
        align,
        tokens: parseTokensFromHtml(element?.innerHTML ?? ''),
    };
}

function headerBandFromRow(row: Element): A4Block {
    const children = [...row.children].filter((child) => child.tagName === 'DIV');

    return {
        id: pid('header'),
        type: 'headerBand',
        showBorder: false,
        left: slotFromElement(children[0] ?? null, 'left'),
        center: slotFromElement(children[1] ?? null, 'center'),
        right: slotFromElement(children[children.length - 1] ?? null, 'right'),
    };
}

function textBlockFromElement(element: Element, align: A4Align = 'left', bold = false): A4Block {
    return {
        id: pid('text'),
        type: 'text',
        align,
        size: 'sm',
        bold,
        uppercase: false,
        tokens: parseTokensFromHtml(element.innerHTML),
    };
}

function extractForEachPaths(source: string): string[] {
    const paths: string[] = [];
    const pattern = /\(([\s\S]+?)\)\s*\.forEach/g;
    let match = pattern.exec(source);

    while (match) {
        const path = normalizeVariablePath(match[1]);

        if (path && !paths.includes(path)) {
            paths.push(path);
        }

        match = pattern.exec(source);
    }

    return paths;
}

function parseColumnCell(html: string): Pick<A4Column, 'mode' | 'path' | 'staticValue' | 'tokens'> {
    const trimmed = html.trim();

    if (trimmed.includes('index + 1') || trimmed.includes('index%20%2B%201')) {
        return { mode: 'index', path: '', staticValue: '', tokens: [] };
    }

    const tokens = parseTokensFromHtml(trimmed);

    if (tokens.length === 1 && tokens[0].kind === 'variable') {
        const path = tokens[0].path.replace(/^item\./, '').replace(/^item\?\.?/, '');

        return { mode: 'variable', path, staticValue: '', tokens: [] };
    }

    if (tokens.some((token) => token.kind === 'variable')) {
        return {
            mode: 'variable',
            path: '',
            staticValue: '',
            tokens: tokens.map((token) =>
                token.kind === 'variable'
                    ? { kind: 'variable', path: token.path.replace(/^item\./, '').replace(/^item\?\.?/, '') }
                    : token,
            ),
        };
    }

    return { mode: 'static', path: '', staticValue: decodeHtml(trimmed.replace(/<[^>]+>/g, '')), tokens: [] };
}

function parseProductionTable(element: Element, arrayPath: string, variant: A4TableVariant): A4Block | null {
    const headerCells = element.querySelectorAll('thead th');
    const columns: A4Column[] = [];

    headerCells.forEach((cell, index) => {
        const align = parseAlignFromStyle(cell.getAttribute('style'));
        const isNum =
            cell.classList.contains('text-right') ||
            cell.classList.contains('tb-num') ||
            ['qty', 'rate', 'amount', 'disc', 'amt', 'gst'].some((word) =>
                (cell.textContent ?? '').toLowerCase().includes(word),
            );
        const format: A4ColumnFormat = isNum ? 'currency' : 'text';

        columns.push({
            id: pid('col'),
            header: decodeHtml(cell.textContent ?? `Column ${index + 1}`),
            mode: 'variable',
            path: '',
            staticValue: '',
            tokens: [],
            align,
            width: '',
            format,
        });
    });

    const bodyRow = element.querySelector('tbody tr');

    if (bodyRow) {
        bodyRow.querySelectorAll('td').forEach((cell, index) => {
            const parsed = parseColumnCell(cell.innerHTML);
            const column = columns[index] ?? createA4Column('variable');

            columns[index] = {
                ...column,
                ...parsed,
                id: column.id ?? pid('col'),
                header: column.header || `Column ${index + 1}`,
                align: parseAlignFromStyle(cell.getAttribute('style')) || column.align,
            };
        });
    }

    if (columns.length === 0) {
        return null;
    }

    return {
        id: pid('table'),
        type: 'table',
        arrayPath,
        columns,
        variant,
        compact: true,
        showHeader: headerCells.length > 0,
    };
}

function parseProductionPageSettings(source: string): A4PageSettings {
    const settings = createDefaultPageSettings();
    const marginMatch = source.match(/margin-(?:top|left):\s*([0-9.]+)cm/i);

    if (marginMatch) {
        settings.marginMm = Math.round(Number(marginMatch[1]) * 10);
    }

    const fontMatch = source.match(/body\s*\{[^}]*font-family:\s*'([^']+)'/i);

    if (fontMatch?.[1] === 'Tahoma') {
        settings.fontFamily = 'Arial';
    }

    const sizeMatch = source.match(/body\s*\{[^}]*font-size:\s*([0-9.]+)px/i);

    if (sizeMatch) {
        settings.baseFontSize = Number(sizeMatch[1]) || settings.baseFontSize;
    }

    return settings;
}

/** Parses production invoice EJS (HOAD-style) into editable block editor sections. */
export function parseProductionEjsBlocks(source: string): { blocks: A4Block[]; pageSettings: A4PageSettings } {
    productionUid = 0;

    const arrayPaths = extractForEachPaths(source);
    let tableIndex = 0;
    const html = stripEjsForDom(source.includes('<html') ? source : `<html><body>${source}</body></html>`);
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const pageSettings = parseProductionPageSettings(source);
    const blocks: A4Block[] = [];
    const root = doc.querySelector('.container') ?? doc.body;

    const personalMessage = root.querySelector('.personal-message');

    if (personalMessage) {
        blocks.push({
            id: pid('text'),
            type: 'text',
            align: 'center',
            size: 'md',
            bold: false,
            uppercase: false,
            tokens: parseTokensFromHtml(personalMessage.innerHTML),
        });
    }

    const headerSection = root.querySelector('.header-section');

    if (headerSection) {
        for (const row of headerSection.querySelectorAll(':scope > .header-row, :scope .info-row')) {
            blocks.push(headerBandFromRow(row));
        }
    }

    for (const table of root.querySelectorAll('table')) {
        const headers = [...table.querySelectorAll('thead th')].map((cell) => (cell.textContent ?? '').toLowerCase());
        const isTax =
            headers.some((header) => header.includes('cgst') || header.includes('taxable')) ||
            headers.includes('description');
        const variant: A4TableVariant = isTax ? 'tax' : 'invoice';
        const arrayPath = arrayPaths[tableIndex] ?? '';
        const block = parseProductionTable(table, arrayPath, variant);

        if (block) {
            blocks.push(block);
            tableIndex += 1;
        }
    }

    const sellerInfo = root.querySelector('.seller-info');

    if (sellerInfo) {
        const children = [...sellerInfo.children].filter((child) => child.tagName === 'DIV');

        blocks.push({
            id: pid('header'),
            type: 'headerBand',
            showBorder: true,
            left: slotFromElement(children[0] ?? null, 'left'),
            center: createHeaderSlot('text'),
            right: slotFromElement(children[1] ?? children[children.length - 1] ?? null, 'right'),
        });
    }

    const footer = root.querySelector('.footer-section');

    if (footer) {
        blocks.push({
            id: pid('terms'),
            type: 'terms',
            title: 'Terms & Conditions',
            size: 'xs',
            tokens: parseTokensFromHtml(footer.innerHTML),
        });
    }

    if (blocks.length === 0) {
        for (const row of root.querySelectorAll('.header-row, .info-row')) {
            blocks.push(headerBandFromRow(row));
        }

        for (const element of root.querySelectorAll('div, p')) {
            if (element.children.length > 0 || element.closest('table, .seller-info, .footer-section, .personal-message')) {
                continue;
            }

            const tokens = parseTokensFromHtml(element.innerHTML);

            if (tokens.length > 0) {
                blocks.push(textBlockFromElement(element));
            }
        }
    }

    return { blocks, pageSettings };
}
