export type ThermalAlign = 'left' | 'center' | 'right';

export type ThermalTextToken =
    | { kind: 'literal'; value: string }
    | { kind: 'variable'; path: string };

export type ThermalColumnMode = 'static' | 'variable' | 'index';

export type ThermalColumn = {
    mode: ThermalColumnMode;
    value: string;
    path: string;
    prefix: string;
    weight: number;
};

type ThermalBase = {
    id: string;
    key: string;
    font: number;
    bold: boolean;
    conditional: string;
};

export type ThermalTextElement = ThermalBase & {
    type: 'text';
    align: ThermalAlign;
    tokens: ThermalTextToken[];
};

export type ThermalVariableElement = ThermalBase & {
    type: 'variable';
    align: ThermalAlign;
    path: string;
};

export type ThermalDividerElement = {
    id: string;
    type: 'divider';
    key: string;
    conditional: string;
};

export type ThermalTableElement = ThermalBase & {
    type: 'table';
    loop: boolean;
    path: string;
    tableType: string;
    subkey: string[];
    columns: ThermalColumn[];
};

export type ThermalElement =
    | ThermalTextElement
    | ThermalVariableElement
    | ThermalDividerElement
    | ThermalTableElement;

export type ThermalElementType = ThermalElement['type'];

let uidCounter = 0;

function uid(prefix: string): string {
    uidCounter += 1;

    return `${prefix}-${Date.now().toString(36)}-${uidCounter}`;
}

export function createColumn(mode: ThermalColumnMode = 'static'): ThermalColumn {
    return { mode, value: '', path: '', prefix: '', weight: 10 };
}

export function createThermalElement(type: ThermalElementType): ThermalElement {
    switch (type) {
        case 'text':
            return {
                id: uid('text'),
                type: 'text',
                key: 'text',
                font: 20,
                bold: false,
                conditional: '',
                align: 'left',
                tokens: [{ kind: 'literal', value: 'Text' }],
            };
        case 'variable':
            return {
                id: uid('variable'),
                type: 'variable',
                key: 'variable',
                font: 20,
                bold: false,
                conditional: '',
                align: 'left',
                path: '',
            };
        case 'divider':
            return {
                id: uid('divider'),
                type: 'divider',
                key: 'separator',
                conditional: '',
            };
        case 'table':
            return {
                id: uid('table'),
                type: 'table',
                key: 'table',
                font: 20,
                bold: false,
                conditional: '',
                loop: false,
                path: '',
                tableType: '',
                subkey: [],
                columns: [createColumn('static'), createColumn('static')],
            };
    }
}

type SerializedColumn = {
    value?: string;
    path?: string;
    prefix?: string;
    isIndex: boolean;
    isStatic: boolean;
};

function serializeColumn(column: ThermalColumn): SerializedColumn {
    if (column.mode === 'index') {
        return { isIndex: true, isStatic: false };
    }

    if (column.mode === 'static') {
        return { value: column.value, isIndex: false, isStatic: true };
    }

    const serialized: SerializedColumn = {
        path: column.path,
        isIndex: false,
        isStatic: false,
    };

    if (column.prefix) {
        serialized.prefix = column.prefix;
    }

    return serialized;
}

/**
 * Converts the builder element list into the print-service JSON array. Sequential
 * `index` values are assigned by list order and optional keys (`bold`, `conditional`,
 * `tableType`, `subkey`) are only emitted when meaningful.
 */
export function serializeThermal(elements: ThermalElement[]): Record<string, unknown>[] {
    return elements.map((element, index) => {
        if (element.type === 'divider') {
            const out: Record<string, unknown> = {
                key: element.key || 'separator',
                type: 'divider',
                index,
            };

            if (element.conditional) {
                out.conditional = element.conditional;
            }

            return out;
        }

        if (element.type === 'text') {
            const out: Record<string, unknown> = { key: element.key, type: 'text' };

            if (element.bold) {
                out.bold = true;
            }

            out.font = element.font;
            out.align = element.align;
            out.index = index;
            out.textData = element.tokens.map((token) =>
                token.kind === 'literal' ? token.value : token.path,
            );

            if (element.conditional) {
                out.conditional = element.conditional;
            }

            return out;
        }

        if (element.type === 'variable') {
            const out: Record<string, unknown> = { key: element.key };

            if (element.bold) {
                out.bold = true;
            }

            out.font = element.font;
            out.path = element.path;
            out.type = 'variable';
            out.align = element.align;
            out.index = index;

            if (element.conditional) {
                out.conditional = element.conditional;
            }

            return out;
        }

        const out: Record<string, unknown> = { key: element.key };

        if (element.bold) {
            out.bold = true;
        }

        out.font = element.font;
        out.loop = element.loop;
        out.path = element.path;
        out.type = 'table';
        out.index = index;

        if (element.subkey.length > 0) {
            out.subkey = element.subkey;
        }

        out.columns = element.columns.map(serializeColumn);

        if (element.tableType) {
            out.tableType = element.tableType;
        }

        out.weightArray = element.columns.map((column) => column.weight);

        if (element.conditional) {
            out.conditional = element.conditional;
        }

        return out;
    });
}

/** Parses an exported thermal JSON array back into editable builder elements. */
export function parseThermal(raw: unknown): ThermalElement[] {
    if (!Array.isArray(raw)) {
        throw new Error('Thermal template must be a JSON array.');
    }

    return raw.map((item): ThermalElement => {
        const entry = item as Record<string, unknown>;
        const type = entry.type as ThermalElementType;
        const key = typeof entry.key === 'string' ? entry.key : 'element';
        const conditional = typeof entry.conditional === 'string' ? entry.conditional : '';
        const bold = entry.bold === true;
        const font = typeof entry.font === 'number' ? entry.font : 20;
        const align = (entry.align as ThermalAlign) ?? 'left';

        if (type === 'divider') {
            return { id: uid('divider'), type: 'divider', key: key || 'separator', conditional };
        }

        if (type === 'text') {
            const textData = Array.isArray(entry.textData) ? entry.textData : [];

            return {
                id: uid('text'),
                type: 'text',
                key,
                font,
                bold,
                conditional,
                align,
                tokens: textData.map((value) => {
                    const stringValue = String(value);

                    return stringValue.includes('.') && !stringValue.includes(' ')
                        ? { kind: 'variable' as const, path: stringValue }
                        : { kind: 'literal' as const, value: stringValue };
                }),
            };
        }

        if (type === 'variable') {
            return {
                id: uid('variable'),
                type: 'variable',
                key,
                font,
                bold,
                conditional,
                align,
                path: typeof entry.path === 'string' ? entry.path : '',
            };
        }

        const columns = Array.isArray(entry.columns) ? entry.columns : [];
        const weightArray = Array.isArray(entry.weightArray) ? entry.weightArray : [];

        return {
            id: uid('table'),
            type: 'table',
            key,
            font,
            bold,
            conditional,
            loop: entry.loop === true,
            path: typeof entry.path === 'string' ? entry.path : '',
            tableType: typeof entry.tableType === 'string' ? entry.tableType : '',
            subkey: Array.isArray(entry.subkey) ? (entry.subkey as string[]) : [],
            columns: columns.map((column, columnIndex) => {
                const col = column as Record<string, unknown>;
                const weight = typeof weightArray[columnIndex] === 'number' ? (weightArray[columnIndex] as number) : 10;

                if (col.isIndex === true) {
                    return { ...createColumn('index'), weight };
                }

                if (col.isStatic === true) {
                    return {
                        ...createColumn('static'),
                        value: typeof col.value === 'string' ? col.value : '',
                        weight,
                    };
                }

                return {
                    ...createColumn('variable'),
                    path: typeof col.path === 'string' ? col.path : '',
                    prefix: typeof col.prefix === 'string' ? col.prefix : '',
                    weight,
                };
            }),
        };
    });
}
