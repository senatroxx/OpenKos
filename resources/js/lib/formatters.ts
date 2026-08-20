let displayTimezone = 'UTC';

export function setDisplayTimezone(timezone: string | null | undefined): void {
    displayTimezone = timezone || 'UTC';
}

function dateTimeFormatter(
    locale: string,
    options: Intl.DateTimeFormatOptions,
    timeZone = displayTimezone,
): Intl.DateTimeFormat {
    try {
        return new Intl.DateTimeFormat(locale, { ...options, timeZone });
    } catch {
        return new Intl.DateTimeFormat(locale, { ...options, timeZone: 'UTC' });
    }
}

function isDateOnly(value: string): boolean {
    return /^\d{4}-\d{2}-\d{2}$/.test(value);
}

export function todayISO(): string {
    const parts = dateTimeFormatter('en-CA', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    })
        .formatToParts(new Date())
        .reduce<Record<string, string>>((result, part) => {
            result[part.type] = part.value;

            return result;
        }, {});

    return `${parts.year}-${parts.month}-${parts.day}`;
}

export function formatDate(dateStr: string | null): string {
    if (!dateStr) {
        return '—';
    }

    return dateTimeFormatter(
        'id-ID',
        {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        },
        isDateOnly(dateStr) ? 'UTC' : displayTimezone,
    ).format(new Date(dateStr));
}

export function formatDateTime(
    dateStr: string | null,
    locale = 'id-ID',
): string {
    if (!dateStr) {
        return '—';
    }

    return dateTimeFormatter(locale, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(dateStr));
}

export function formatPrice(cents: string | null): string {
    if (!cents) {
        return '—';
    }

    const num = Number.parseFloat(cents);

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(num);
}

export function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return bytes + ' B';
    }

    if (bytes < 1024 * 1024) {
        return (bytes / 1024).toFixed(0) + ' KB';
    }

    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

export function formatRupiah(value: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
}

export function formatPeriod(periodStart: string, locale = 'id-ID'): string {
    const [y, m] = periodStart.split('-');
    const date = new Date(Date.UTC(Number(y), Number(m) - 1, 1));

    return date.toLocaleDateString(locale, {
        year: 'numeric',
        month: 'long',
        timeZone: 'UTC',
    });
}

export function computeMonthlyEquivalent(
    amount: string | null,
    interval: number | null,
    unit: string | null,
): string {
    if (!amount || !interval || !unit) {
        return '';
    }

    const num = Number.parseFloat(amount);

    if (isNaN(num)) {
        return '';
    }

    const int = interval;
    let monthly: number;

    switch (unit) {
        case 'day':
            monthly = (num * 365) / 12 / int;
            break;
        case 'week':
            monthly = (num * 52) / 12 / int;
            break;
        case 'month':
            monthly = num / int;
            break;
        case 'year':
            monthly = num / 12 / int;
            break;
        default:
            return '';
    }

    return `≈ ${formatRupiah(Math.round(monthly))}/month`;
}

export function formatRelativeTime(iso: string): string {
    const then = new Date(iso).getTime();
    const now = Date.now();
    const diff = Math.floor((now - then) / 1000);

    if (diff < 60) {
        return 'just now';
    }

    if (diff < 3600) {
        return `${Math.floor(diff / 60)}m ago`;
    }

    if (diff < 86400) {
        return `${Math.floor(diff / 3600)}h ago`;
    }

    if (diff < 2592000) {
        return `${Math.floor(diff / 86400)}d ago`;
    }

    return `${Math.floor(diff / 2592000)}mo ago`;
}
