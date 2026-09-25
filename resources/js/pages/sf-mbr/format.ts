export function formatNumber(value: number): string {
    return new Intl.NumberFormat('en-IN').format(value);
}

export function formatPct(value: number | null, signed = false): string {
    if (value === null) {
        return '—';
    }

    const pct = `${(Math.abs(value) * 100).toFixed(1)}%`;

    if (!signed) {
        return pct;
    }

    if (value > 0) {
        return `+${pct}`;
    }

    if (value < 0) {
        return `−${pct}`;
    }

    return pct;
}

export function formatDays(value: number | null): string {
    return value === null ? '—' : value.toFixed(1);
}

export function formatHours(value: number | null): string {
    return value === null ? '—' : `${value.toFixed(1)}h`;
}

export function shareOf(part: number, whole: number): string {
    return whole > 0 ? formatPct(part / whole) : '—';
}
