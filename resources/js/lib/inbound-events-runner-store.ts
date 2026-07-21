import { retry as retryAction } from '@/actions/App/Http/Controllers/InboundEventsRunnerController';

export type RunStatus =
    | 'pending'
    | 'running'
    | 'success'
    | 'failed'
    | 'skipped';

export type RunItem = {
    logId: string;
    status: RunStatus;
    httpStatus: number | null;
    response: string | null;
    error: string | null;
};

type RunnerState = {
    items: RunItem[];
    fileName: string | null;
    parseError: string | null;
    running: boolean;
    delayMs: number;
};

const STORAGE_KEY = 'inbound-events-runner-state';

const defaultState: RunnerState = {
    items: [],
    fileName: null,
    parseError: null,
    running: false,
    delayMs: 500,
};

let state: RunnerState = loadFromStorage();
let stopRequested = false;
let runGeneration = 0;
const listeners = new Set<() => void>();

function loadFromStorage(): RunnerState {
    if (typeof window === 'undefined') {
        return { ...defaultState };
    }

    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return { ...defaultState };
        }

        const parsed = JSON.parse(raw) as Partial<RunnerState>;

        return {
            ...defaultState,
            ...parsed,
            running: false,
            items: (parsed.items ?? []).map((item) =>
                item.status === 'running'
                    ? { ...item, status: 'pending' as const }
                    : item,
            ),
        };
    } catch {
        return { ...defaultState };
    }
}

function persist(): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch {
        // Ignore quota errors.
    }
}

function emit(): void {
    persist();
    listeners.forEach((listener) => listener());
}

function setState(patch: Partial<RunnerState>): void {
    state = { ...state, ...patch };
    emit();
}

function updateItem(index: number, patch: Partial<RunItem>): void {
    state = {
        ...state,
        items: state.items.map((item, i) =>
            i === index ? { ...item, ...patch } : item,
        ),
    };
    emit();
}

function getXsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

export function parseCsvLogIds(content: string): string[] {
    const lines = content
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean);

    if (lines.length === 0) {
        return [];
    }

    const firstLine = lines[0];
    const delimiter = firstLine.includes('\t')
        ? '\t'
        : firstLine.includes(';')
          ? ';'
          : ',';

    const parseRow = (line: string): string[] => {
        const values: string[] = [];
        let current = '';
        let inQuotes = false;

        for (let i = 0; i < line.length; i++) {
            const char = line[i];

            if (char === '"') {
                if (inQuotes && line[i + 1] === '"') {
                    current += '"';
                    i++;
                } else {
                    inQuotes = !inQuotes;
                }
                continue;
            }

            if (char === delimiter && !inQuotes) {
                values.push(current.trim());
                current = '';
                continue;
            }

            current += char;
        }

        values.push(current.trim());

        return values;
    };

    const header = parseRow(lines[0]).map((col) =>
        col.replace(/^\uFEFF/, '').toLowerCase(),
    );

    const idColumnIndex = header.findIndex((col) =>
        ['_id', 'id', 'log_id', 'logid'].includes(col),
    );

    const dataLines = idColumnIndex >= 0 ? lines.slice(1) : lines;
    const columnIndex = idColumnIndex >= 0 ? idColumnIndex : 0;

    const ids = dataLines
        .map((line) => parseRow(line)[columnIndex]?.trim() ?? '')
        .filter(Boolean);

    return [...new Set(ids)];
}

export function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

export function getSnapshot(): RunnerState {
    return state;
}

export function setDelayMs(delayMs: number): void {
    if (state.running) {
        return;
    }

    setState({ delayMs });
}

export function loadCsv(fileName: string, logIds: string[]): void {
    runGeneration++;
    stopRequested = true;

    setState({
        fileName,
        parseError: null,
        running: false,
        items: logIds.map((logId) => ({
            logId,
            status: 'pending',
            httpStatus: null,
            response: null,
            error: null,
        })),
    });
}

export function setParseError(message: string): void {
    setState({ parseError: message, fileName: null, items: [] });
}

export function resetRunner(): void {
    runGeneration++;
    stopRequested = true;
    setState({
        items: state.items.map((item) => ({
            ...item,
            status: 'pending',
            httpStatus: null,
            response: null,
            error: null,
        })),
        running: false,
    });
}

export function stopRunner(): void {
    stopRequested = true;
}

export function clearRunner(): void {
    runGeneration++;
    stopRequested = true;
    state = { ...defaultState };
    sessionStorage.removeItem(STORAGE_KEY);
    emit();
}

export async function runAll(): Promise<void> {
    if (state.items.length === 0 || state.running) {
        return;
    }

    const generation = ++runGeneration;
    stopRequested = false;
    setState({ running: true });

    const delayMs = state.delayMs;

    for (let i = 0; i < state.items.length; i++) {
        if (generation !== runGeneration) {
            return;
        }

        const currentItem = state.items[i];

        if (
            currentItem.status === 'success' ||
            currentItem.status === 'failed' ||
            currentItem.status === 'skipped'
        ) {
            continue;
        }

        if (stopRequested) {
            setState({
                items: state.items.map((item, j) =>
                    j >= i && item.status === 'pending'
                        ? { ...item, status: 'skipped' }
                        : item,
                ),
            });
            break;
        }

        updateItem(i, {
            status: 'running',
            httpStatus: null,
            response: null,
            error: null,
        });

        try {
            const res = await fetch(retryAction.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ log_id: currentItem.logId }),
            });

            const json = await res.json().catch(() => ({}));

            if (res.ok && json.success) {
                updateItem(i, {
                    status: 'success',
                    httpStatus: json.status ?? res.status,
                    response:
                        typeof json.body === 'string'
                            ? json.body
                            : JSON.stringify(json.body, null, 2),
                });
            } else {
                updateItem(i, {
                    status: 'failed',
                    httpStatus: json.status ?? res.status,
                    error:
                        typeof json.body === 'string'
                            ? json.body
                            : JSON.stringify(json.body ?? json, null, 2),
                });
            }
        } catch (err) {
            updateItem(i, {
                status: 'failed',
                error: err instanceof Error ? err.message : 'Network error',
            });
        }

        const hasMorePending = state.items.some(
            (item, j) => j > i && item.status === 'pending',
        );

        if (hasMorePending && delayMs > 0 && !stopRequested) {
            await new Promise((resolve) => setTimeout(resolve, delayMs));
        }
    }

    if (generation === runGeneration) {
        setState({ running: false });
    }
}
