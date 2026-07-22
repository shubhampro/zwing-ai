import { show as externalQueryLogShow } from '@/routes/external-query-logs';

export type ExternalQueryPollPayload = {
    id: number;
    job_type: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    context: Record<string, unknown> | null;
    result: Record<string, unknown> | null;
    zwing_query_ms: number | null;
    erp_query_ms: number | null;
    failure_reason: string | null;
    started_at: string | null;
    finished_at: string | null;
};

function sleep(ms: number): Promise<void> {
    return new Promise((resolve) => {
        window.setTimeout(resolve, ms);
    });
}

export async function waitForExternalQuery(
    logId: number,
    options: { intervalMs?: number; timeoutMs?: number } = {},
): Promise<ExternalQueryPollPayload> {
    const intervalMs = options.intervalMs ?? 1000;
    const timeoutMs = options.timeoutMs ?? 5 * 60 * 1000;
    const startedAt = Date.now();

    while (Date.now() - startedAt < timeoutMs) {
        const response = await fetch(externalQueryLogShow.url(logId), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Failed to load external query status.');
        }

        const payload = (await response.json()) as ExternalQueryPollPayload;

        if (payload.status === 'completed') {
            return payload;
        }

        if (payload.status === 'failed') {
            throw new Error(payload.failure_reason ?? 'External query failed.');
        }

        await sleep(intervalMs);
    }

    throw new Error('External query timed out.');
}

export function isExternalQueryPollPayload(
    value: unknown,
): value is ExternalQueryPollPayload {
    return (
        typeof value === 'object' &&
        value !== null &&
        'id' in value &&
        'status' in value &&
        'job_type' in value
    );
}

export async function resolveExternalQueryResponse(
    response: Response,
): Promise<ExternalQueryPollPayload> {
    const payload = (await response.json()) as unknown;

    if (!isExternalQueryPollPayload(payload)) {
        throw new Error('Unexpected external query response.');
    }

    if (payload.status === 'completed') {
        return payload;
    }

    if (payload.status === 'failed') {
        throw new Error(payload.failure_reason ?? 'External query failed.');
    }

    return waitForExternalQuery(payload.id);
}

export function xsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}
