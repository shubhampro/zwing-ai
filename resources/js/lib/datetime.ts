const IST = 'Asia/Kolkata';

const IST_PARTS: Intl.DateTimeFormatOptions = {
    timeZone: IST,
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
};

function parseIso(iso: string | null | undefined): Date | null {
    if (!iso) {
        return null;
    }

    const date = new Date(iso);

    return Number.isNaN(date.getTime()) ? null : date;
}

function istParts(date: Date): Record<string, string> {
    return Object.fromEntries(
        new Intl.DateTimeFormat('en-GB', IST_PARTS)
            .formatToParts(date)
            .filter((part) => part.type !== 'literal')
            .map((part) => [part.type, part.value]),
    );
}

function dayPeriod(value: string | undefined): string {
    return (value ?? 'am').replaceAll('.', '').trim().toLowerCase();
}

/**
 * Deterministic India time for SSR + browser.
 * UTC instants render as Asia/Kolkata, e.g. 4 Aug 2026, 03:30 pm IST.
 */
export function formatDateTime(iso: string | null | undefined): string {
    const date = parseIso(iso);

    if (!date) {
        return '—';
    }

    const parts = istParts(date);

    return `${parts.day} ${parts.month} ${parts.year}, ${parts.hour.padStart(2, '0')}:${parts.minute.padStart(2, '0')} ${dayPeriod(parts.dayPeriod)} IST`;
}

export function formatDay(iso: string | null | undefined): string {
    const date = parseIso(iso);

    if (!date) {
        return '—';
    }

    const parts = istParts(date);

    return `${parts.day} ${parts.month} ${parts.year}`;
}

export function parseDateOnly(value: string | null | undefined): Date | null {
    if (!value) {
        return null;
    }

    const match = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(value.trim());

    if (!match) {
        return null;
    }

    const date = new Date(
        Number(match[1]),
        Number(match[2]) - 1,
        Number(match[3]),
    );

    if (
        date.getFullYear() !== Number(match[1]) ||
        date.getMonth() !== Number(match[2]) - 1 ||
        date.getDate() !== Number(match[3])
    ) {
        return null;
    }

    return date;
}

export function toDateOnly(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

export function formatDateOnly(value: string | null | undefined): string {
    const date = parseDateOnly(value);

    if (!date) {
        return '—';
    }

    return date.toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
