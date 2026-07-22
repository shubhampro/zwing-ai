const DATE_TIME_OPTIONS: Intl.DateTimeFormatOptions = {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
};

/**
 * Deterministic datetime for SSR + browser (avoids hydration mismatches
 * from locale/hour12 differences with `toLocaleString(undefined, …)`).
 */
export function formatDateTime(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString('en-GB', DATE_TIME_OPTIONS);
}
