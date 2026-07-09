export const BILLING_STRATEGIES = [
    { value: 'advance', label: 'Advance (due within period)' },
    { value: 'arrears', label: 'Arrears (due after period)' },
];

export const BILLING_UNITS = ['day', 'week', 'month', 'year'] as const;

export const PAYABLE_STATUSES = ['partial', 'overdue', 'due', 'upcoming'];

export const PAYMENT_METHODS = [
    { value: 'cash', label: 'Cash' },
    { value: 'transfer', label: 'Bank Transfer' },
    { value: 'ewallet', label: 'E-Wallet' },
    { value: 'other', label: 'Other' },
];

export const PAYMENT_METHOD_LABELS: Record<string, string> = {
    cash: 'Cash',
    transfer: 'Bank Transfer',
    ewallet: 'E-Wallet',
    other: 'Other',
};
