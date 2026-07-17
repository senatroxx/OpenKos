export function todayISO(): string {
    const d = new Date();

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

export function formatDate(dateStr: string | null): string {
    if (!dateStr) {
        return '—';
    }

    return new Date(dateStr).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
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
