export const MOVE_OUT_REASONS = [
    { value: 'end_of_contract', label: 'End of Contract' },
    { value: 'voluntary_move', label: 'Voluntary Move' },
    { value: 'policy_violation', label: 'Policy Violation' },
    { value: 'eviction', label: 'Eviction' },
    { value: 'other', label: 'Other' },
];

export const DUE_DAY_OPTIONS = [
    { value: '1', label: '1st' },
    { value: '5', label: '5th' },
    { value: '10', label: '10th' },
    { value: '15', label: '15th' },
    { value: '20', label: '20th' },
    { value: '25', label: '25th' },
    { value: '31', label: 'Last day of month' },
];

export const DUE_DAY_LABELS: Record<number, string> = {
    1: '1st',
    5: '5th',
    10: '10th',
    15: '15th',
    20: '20th',
    25: '25th',
    31: 'Last day of month',
};

export const DEPOSIT_HANDLING_OPTIONS = [
    { value: 'carry_forward', label: 'Carry forward to new lease' },
    { value: 'refund_and_collect_new', label: 'Refund and collect new deposit' },
    { value: 'forfeit', label: 'Forfeit deposit' },
];
