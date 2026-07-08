import type { A4Block, A4Column, A4HeaderSlot, A4TextToken } from '@/lib/template-builder/a4';

export type ProductionSegment = {
    blockId: string;
    source: string;
};

function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function pathToEjs(path: string): string {
    const trimmed = path.trim();

    if (!trimmed) {
        return "''";
    }

    if (trimmed.includes('new Date') || trimmed.includes('parseInt') || trimmed.includes('Math.')) {
        return trimmed;
    }

    if (trimmed.startsWith('item.')) {
        return trimmed.replace(/\./g, '?.');
    }

    if (trimmed.startsWith('printData.')) {
        const optional = `printData${trimmed.slice('printData'.length).replace(/\./g, '?.')}`;

        return `${optional} || ''`;
    }

    return `${trimmed} || ''`;
}

function renderTokens(tokens: A4TextToken[]): string {
    return tokens
        .map((token) => (token.kind === 'literal' ? escapeHtml(token.value) : `<%= ${pathToEjs(token.path)} %>`))
        .join('');
}

function renderSlotHtml(
    slot: A4HeaderSlot,
    align: 'left' | 'center' | 'right',
    options?: { memoCol?: boolean },
): string {
    if (slot.tokens.length === 0) {
        return '';
    }

    const alignStyle = align === 'right' ? 'right' : align === 'center' ? 'center' : 'left';
    const classAttr = options?.memoCol ? ' class="memo-col"' : '';
    const labelStyle = align === 'left' ? ' style="text-align: left;"' : '';

    let labelEnd = 0;

    while (labelEnd < slot.tokens.length && slot.tokens[labelEnd].kind === 'literal') {
        labelEnd += 1;
    }

    const hasVariables = slot.tokens.some((token) => token.kind === 'variable');

    if (!hasVariables) {
        labelEnd = slot.tokens.length;
    }

    const labelHtml = slot.tokens
        .slice(0, labelEnd)
        .map((token) => (token.kind === 'literal' ? escapeHtml(token.value) : ''))
        .join('');
    const valueTokens = slot.tokens.slice(labelEnd);
    const valueSpans: string[] = [];
    let current = '';

    for (const token of valueTokens) {
        if (token.kind === 'literal' && token.value.startsWith('\n')) {
            if (current) {
                valueSpans.push(current);
                current = '';
            }

            current = `<br>${token.value.trim() ? (token.kind === 'literal' ? escapeHtml(token.value.trim()) : '') : ''}`;
            continue;
        }

        const chunk =
            token.kind === 'literal' ? escapeHtml(token.value) : `<%= ${pathToEjs(token.path)} %>`;

        current += chunk;
    }

    if (current) {
        valueSpans.push(current);
    }

    const parts: string[] = [];

    if (labelHtml.trim()) {
        parts.push(`<span class="header-label"${labelStyle}>${labelHtml}</span>`);
    }

    for (const span of valueSpans) {
        parts.push(`<span style="font-weight:600;">${span}</span>`);
    }

    if (parts.length === 0) {
        return '';
    }

    return `<div${classAttr} style="text-align:${alignStyle};">\n      ${parts.join('\n      ')}\n    </div>`;
}

function renderHeaderBandBlock(block: Extract<A4Block, { type: 'headerBand' }>, originalSegment: string): string {
    const rowClass = originalSegment.includes('info-row') ? 'info-row' : 'header-row';
    const hasCenter = block.center.tokens.length > 0;
    const memoCol = originalSegment.includes('memo-col');

    const left = renderSlotHtml(block.left, 'left');
    const center = hasCenter ? renderSlotHtml(block.center, 'center', { memoCol }) : '';
    const right = renderSlotHtml(block.right, 'right');

    if (hasCenter) {
        return ['  <div class="' + rowClass + '">', `    ${left}`, '', `    ${center}`, '', `    ${right}`, '  </div>'].join(
            '\n',
        );
    }

    return ['  <div class="' + rowClass + '">', `    ${left}`, '', `    ${right}`, '  </div>'].join('\n');
}

function renderTextBlock(block: Extract<A4Block, { type: 'text' }>, wrapperClass?: string): string {
    const lines = block.tokens
        .reduce<string[]>((lines, token) => {
            const chunk = token.kind === 'literal' ? token.value : `<%= ${pathToEjs(token.path)} %>`;
            const parts = chunk.split('\n');
            const last = lines.pop() ?? '';

            if (parts.length === 1) {
                lines.push(last + parts[0]);
            } else {
                lines.push(last + parts[0]);
                lines.push(...parts.slice(1, -1));
                lines.push(parts[parts.length - 1]);
            }

            return lines;
        }, [''])
        .filter((line) => line.length > 0);

    const inner = lines.map((line) => `    <p>${line}</p>`).join('\n');

    if (wrapperClass) {
        return `<div class="${wrapperClass}">\n${inner}\n  </div>`;
    }

    const align = block.align === 'center' ? 'center' : block.align === 'right' ? 'right' : 'left';

    return `<div class="tb-text" style="text-align:${align};">${renderTokens(block.tokens)}</div>`;
}

function columnCellEjs(column: A4Column): string {
    if (column.mode === 'index') {
        return '<%= index + 1 %>';
    }

    if (column.mode === 'static') {
        return escapeHtml(column.staticValue);
    }

    if (column.tokens.length > 0) {
        return renderTokens(column.tokens);
    }

    return `<%= item?.${column.path} || '' %>`;
}

function renderTableBlock(block: Extract<A4Block, { type: 'table' }>, originalSegment: string): string {
    const headerCells = block.columns
        .map((column) => {
            const alignClass =
                column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : 'text-left';

            return `<th class="${alignClass}">${escapeHtml(column.header)}</th>`;
        })
        .join('\n            ');

    const bodyCells = block.columns
        .map((column) => {
            const alignClass =
                column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : 'text-left';

            return `<td class="${alignClass}">${columnCellEjs(column)}</td>`;
        })
        .join('\n        ');

    const loopMatch = originalSegment.match(/<%\s*\(([\s\S]+?)\)\s*\.forEach\s*\(/);
    const loopOpen = loopMatch
        ? `<% (${loopMatch[1]}).forEach(item => { %>`
        : `<% (${block.arrayPath.replace(/\./g, '?.')} || []).forEach(item => { %>`;
    const loopClose = '<% }) %>';

    const tfootMatch = originalSegment.match(/<tfoot>[\s\S]*<\/tfoot>/i);
    const tfoot = tfootMatch?.[0] ?? '';

    return [
        '  <table>',
        '    <thead>',
        '          <tr>',
        `            ${headerCells}`,
        '          </tr>',
        '        </thead>',
        '',
        '    <tbody>',
        `      ${loopOpen}`,
        '      <tr>',
        `        ${bodyCells}`,
        '      </tr>',
        `      ${loopClose}`,
        '    </tbody>',
        tfoot ? `        ${tfoot}` : '',
        '      </table>',
    ]
        .filter(Boolean)
        .join('\n');
}

function renderTermsBlock(block: Extract<A4Block, { type: 'terms' }>): string {
    const paragraphs = block.tokens
        .reduce<string[]>((parts, token) => {
            const chunk = token.kind === 'literal' ? escapeHtml(token.value) : `<%= ${pathToEjs(token.path)} %>`;
            const split = chunk.split('\n\n');
            const last = parts.pop() ?? '';

            if (split.length === 1) {
                parts.push(last + split[0]);
            } else {
                parts.push(last + split[0]);
                parts.push(...split.slice(1));
            }

            return parts;
        }, [''])
        .filter(Boolean);

    const inner = paragraphs.map((paragraph) => `    <p>${paragraph}</p>`).join('\n');

    return `<div class="footer-section">\n\n${inner}\n\n</div>`;
}

function renderSellerBlock(block: Extract<A4Block, { type: 'headerBand' }>, originalSegment: string): string {
    if (originalSegment.trim()) {
        return originalSegment;
    }

    return [
        '  <div class="seller-info">',
        `        <div style="float: left; text-align:left;">`,
        `          ${renderTokens(block.left.tokens)}`,
        '        </div>',
        `        <div style="float: right; text-align: right">`,
        `          ${renderTokens(block.right.tokens)}`,
        '        </div>',
        '      </div>',
    ].join('\n');
}

/** Renders a block back into production-style HTML for EJS templates. */
export function renderProductionBlockHtml(block: A4Block, originalSegment: string): string {
    switch (block.type) {
        case 'text':
            if (originalSegment.includes('personal-message')) {
                return renderTextBlock(block, 'personal-message');
            }

            return renderTextBlock(block);
        case 'headerBand':
            if (originalSegment.includes('seller-info')) {
                return renderSellerBlock(block, originalSegment);
            }

            return renderHeaderBandBlock(block, originalSegment);
        case 'table':
            return renderTableBlock(block, originalSegment);
        case 'terms':
            return renderTermsBlock(block);
        default:
            return originalSegment;
    }
}

/** Replaces production template segments with updated block HTML. */
export function syncBlocksToProductionEjs(
    ejsSource: string,
    blocks: A4Block[],
    segments: ProductionSegment[],
    changedBlockIds?: Set<string>,
): string {
    let result = ejsSource;

    for (const block of blocks) {
        if (changedBlockIds && !changedBlockIds.has(block.id)) {
            continue;
        }

        const segment = segments.find((item) => item.blockId === block.id);

        if (!segment?.source) {
            continue;
        }

        const nextHtml = renderProductionBlockHtml(block, segment.source);

        if (result.includes(segment.source)) {
            result = result.replace(segment.source, nextHtml);
        }
    }

    return result;
}

function extractBalancedTag(source: string, startIndex: number): string {
    const tagMatch = source.slice(startIndex).match(/^<([a-zA-Z][\w-]*)\b[^>]*>/);

    if (!tagMatch) {
        return '';
    }

    const tagName = tagMatch[1];
    let depth = 0;
    const tagPattern = new RegExp(`<\\/?${tagName}\\b[^>]*>`, 'g');
    tagPattern.lastIndex = startIndex;

    let match = tagPattern.exec(source);

    while (match) {
        if (match[0].startsWith(`</${tagName}`)) {
            depth -= 1;

            if (depth === 0) {
                return source.slice(startIndex, match.index + match[0].length);
            }
        } else if (!match[0].endsWith('/>')) {
            depth += 1;
        }

        match = tagPattern.exec(source);
    }

    return '';
}

function extractByClass(source: string, className: string): string {
    const pattern = new RegExp(`<div\\s+class="${className}"`, 'g');
    const match = pattern.exec(source);

    if (!match) {
        return '';
    }

    return extractBalancedTag(source, match.index);
}

function extractTable(source: string, index: number): string {
    const pattern = /<table\b/gi;
    let match = pattern.exec(source);
    let current = 0;

    while (match) {
        if (current === index) {
            const start = match.index;
            const end = source.indexOf('</table>', start);

            if (end === -1) {
                return '';
            }

            return source.slice(start, end + '</table>'.length);
        }

        current += 1;
        match = pattern.exec(source);
    }

    return '';
}

function isSellerHeaderBand(block: A4Block): boolean {
    return block.type === 'headerBand' && block.showBorder && block.center.tokens.length === 0;
}

/** Extracts original source segments aligned with parsed blocks. */
export function extractProductionSegments(source: string, blocks: A4Block[]): ProductionSegment[] {
    const segments: ProductionSegment[] = [];
    let tableIndex = 0;
    let headerRowIndex = 0;

    for (const block of blocks) {
        let segment = '';

        if (block.type === 'text') {
            segment = extractByClass(source, 'personal-message');
        } else if (block.type === 'headerBand') {
            if (isSellerHeaderBand(block)) {
                segment = extractByClass(source, 'seller-info');
            } else {
                const rowPattern = /<div\s+class="(?:header-row|info-row)"/g;
                const matches = [...source.matchAll(rowPattern)];

                if (matches[headerRowIndex]) {
                    segment = extractBalancedTag(source, matches[headerRowIndex].index ?? 0);
                }

                headerRowIndex += 1;
            }
        } else if (block.type === 'table') {
            segment = extractTable(source, tableIndex);
            tableIndex += 1;
        } else if (block.type === 'terms') {
            segment = extractByClass(source, 'footer-section');
        }

        if (segment) {
            segments.push({ blockId: block.id, source: segment });
        }
    }

    return segments;
}
