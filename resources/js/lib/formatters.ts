let displayTimezone = 'UTC';
let displayCurrency = 'IDR';
let displayLocale = 'id-ID';
let currencyScales: Record<string, number> = { IDR: 0 };

export function setDisplayTimezone(timezone: string | null | undefined): void {
    displayTimezone = timezone || 'UTC';
}

export function setDisplayCurrency(currency: string | null | undefined): void {
    displayCurrency = currency?.toUpperCase() || 'IDR';
}

export function setDisplayLocale(locale: string | null | undefined): void {
    displayLocale = locale || 'id-ID';
}

export function setCurrencyScales(scales: Record<string, number> | null | undefined): void {
    currencyScales = Object.fromEntries(
        Object.entries(scales || { IDR: 0 }).map(([currency, scale]) => [
            currency.toUpperCase(),
            scale,
        ]),
    );
}

function scaleForCurrency(currency: string): number {
    const scale = currencyScales[currency.toUpperCase()];

    if (scale === undefined) {
        throw new Error('Unsupported currency: ' + currency);
    }

    return scale;
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

export function formatPrice(
    amount: string | number | null,
    currency?: string,
): string {
    if (amount === null || amount === '') {
        return '—';
    }

    const resolvedCurrency = currency?.toUpperCase() || displayCurrency;
    const scale = scaleForCurrency(resolvedCurrency);
    const minor = decimalToMinorRounded(String(amount), scale);

    if (minor === null) {
        return '—';
    }

    const formatter = new Intl.NumberFormat(displayLocale, {
        style: 'currency',
        currency: resolvedCurrency,
        minimumFractionDigits: scale,
        maximumFractionDigits: scale,
    });
    const absoluteMinor = minor < 0n ? -minor : minor;
    const factor = 10n ** BigInt(scale);
    const whole = absoluteMinor / factor;
    const fraction = scale > 0 ? (absoluteMinor % factor).toString().padStart(scale, '0') : '';
    const groupedWhole = new Intl.NumberFormat(displayLocale, {
        useGrouping: true,
        maximumFractionDigits: 0,
    }).format(whole);
    const localizedFraction = scale > 0
        ? new Intl.NumberFormat(displayLocale, {
              useGrouping: false,
              minimumIntegerDigits: scale,
              maximumFractionDigits: 0,
          }).format(Number(fraction))
        : '';
    let integerPartReplaced = false;

    return formatter
        .formatToParts(minor < 0n ? -1 : 1)
        .map((part) => {
            if (part.type === 'integer' && !integerPartReplaced) {
                integerPartReplaced = true;

                return groupedWhole;
            }

            return part.type === 'fraction' ? localizedFraction : part.value;
        })
        .join('');
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

export function formatRupiah(value: number | string | null): string {
    return formatPrice(value, displayCurrency);
}

function decimalToMinor(amount: string, scale: number): bigint | null {
    if (!/^\d+(?:\.\d+)?$/.test(amount)) {
        return null;
    }

    const [whole, fraction = ''] = amount.split('.');
    if (fraction.length > scale && /[1-9]/.test(fraction.slice(scale))) {
        return null;
    }

    const padded = fraction.slice(0, scale).padEnd(scale, '0');

    return BigInt(`${whole}${padded}` || '0');
}

function decimalToMinorRounded(amount: string, scale: number): bigint | null {
    if (!/^-?\d+(?:\.\d+)?$/.test(amount)) {
        return null;
    }

    const negative = amount.startsWith('-');
    const unsigned = negative ? amount.slice(1) : amount;
    const [whole, fraction = ''] = unsigned.split('.');
    const retained = fraction.slice(0, scale).padEnd(scale, '0');
    let minor = BigInt(`${whole}${retained}` || '0');

    if (fraction.length > scale && Number(fraction[scale]) >= 5) {
        minor += 1n;
    }

    return negative ? -minor : minor;
}

function divideRounded(numerator: bigint, denominator: bigint): bigint {
    const quotient = numerator / denominator;
    const remainder = numerator % denominator;

    return remainder * 2n >= denominator ? quotient + 1n : quotient;
}

function minorToMajor(minor: bigint, scale: number): string {
    if (scale === 0) {
        return minor.toString();
    }

    const value = minor.toString().padStart(scale + 1, '0');

    return `${value.slice(0, -scale)}.${value.slice(-scale)}`;
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
    currency = displayCurrency,
): string {
    if (!amount || !interval || !unit) {
        return '';
    }

    const scale = scaleForCurrency(currency);
    const minor = decimalToMinor(amount, scale);

    if (minor === null) {
        return '';
    }

    const int = interval;
    let numerator: bigint;
    let denominator: bigint;

    switch (unit) {
        case 'day':
            numerator = 365n;
            denominator = BigInt(12 * int);
            break;
        case 'week':
            numerator = 52n;
            denominator = BigInt(12 * int);
            break;
        case 'month':
            numerator = 1n;
            denominator = BigInt(int);
            break;
        case 'year':
            numerator = 1n;
            denominator = BigInt(12 * int);
            break;
        default:
            return '';
    }

    const monthlyMinor = divideRounded(minor * numerator, denominator);

    return `≈ ${formatPrice(minorToMajor(monthlyMinor, scale), currency)}/month`;
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
