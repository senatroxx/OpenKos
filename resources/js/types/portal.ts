import type { AccountApplicationSummary } from './applications';
import type { MoneyAggregate } from './dashboard';

export type AccountLease = {
    id: number;
    start_date: string;
    end_date: string | null;
    rent_amount: string;
    currency: string;
    status: string;
    target_type: 'unit' | 'whole_property';
    property: { name: string } | null;
    unit: { name: string } | null;
};

export type AccountPendingPayment = {
    amount: string;
    currency: string;
    payment_date: string;
};

export type AccountNextAction =
    | { type: 'no_active_stay' }
    | { type: 'no_payment_required' }
    | {
          type: 'payment_verification';
          pending_payment: AccountPendingPayment;
      }
    | {
          type: 'payment_required';
          invoice: {
              id: number;
              due_date: string;
              display_status: string;
              amount: string;
              currency: string;
          };
          pending_payment: AccountPendingPayment | null;
      };

export type AccountSummary = {
    outstanding_amounts: MoneyAggregate[];
    payable_invoice_count: number;
    pending_verification_count: number;
    next_due_date: string | null;
};

export type AccountActivity = {
    type:
        | 'payment_submitted'
        | 'payment_confirmed'
        | 'payment_cancelled'
        | 'invoice_issued'
        | 'lease_started';
    date: string;
    amount: string | null;
    currency: string;
    reference: string | null;
};

export type AccountDashboardProps = {
    tenant: { id: number; name: string } | null;
    lease: AccountLease | null;
    nextAction: AccountNextAction;
    accountSummary: AccountSummary;
    recentActivity: AccountActivity[];
    applications?: AccountApplicationSummary[];
};
