import type { Unit } from './models';

export type ApplicationTarget = 'whole_property' | 'unit_type';

export type ApplicationStatus =
    | 'new'
    | 'reviewing'
    | 'accepted'
    | 'rejected'
    | 'withdrawn';

export type ApplicationReservationStatus =
    | 'pending'
    | 'confirmed'
    | 'rejected'
    | 'cancelled'
    | 'expired'
    | 'converted';

export type ReservationUnit = {
    id: number;
    name: string;
    slug: string;
    capacity: number;
    property_id: number;
    unit_type_id: number | null;
    status: string;
    effective_rates: NonNullable<Unit['effective_rates']>;
    leases?: Unit['leases'];
};

export type ApplicationReservation = {
    id: number;
    status: ApplicationReservationStatus;
    move_in_date: string;
    expires_at: string | null;
    is_expired: boolean;
    unit: ReservationUnit | null;
};

export type ReservationLeaseContext = {
    id: number;
    move_in_date: string;
    applicant: { name: string };
    rental: {
        billing_unit: 'day' | 'week' | 'month' | 'year';
        billing_interval: number;
        currency: string;
        amount: string;
    };
};

export type ApplicantApplication = {
    id: number;
    status: ApplicationStatus;
    target_type: ApplicationTarget;
    property: {
        id: number;
        name: string;
        public_slug: string;
        slug?: string;
        active_property_rates?: {
            id: number;
            billing_interval: number;
            billing_unit: 'day' | 'week' | 'month' | 'year';
            amount: string;
            currency: string;
        }[];
    } | null;
    unit_type: { id: number; name: string; public_slug: string } | null;
    intended_move_in_date: string | null;
    intended_move_in_timeframe: string | null;
    rental_billing_unit?: string | null;
    rental_billing_interval?: number | null;
    rental_currency?: string | null;
    rental_amount?: string | null;
    applicant_message: string | null;
    applicant_feedback: string | null;
    reservation?: ApplicationReservation | null;
    available_units?: ReservationUnit[];
};

export type OperatorApplication = ApplicantApplication & {
    applicant: { name: string; email: string; phone: string | null };
    operator_notes: string | null;
    reviewed_at: string | null;
};

export type ApplicationTargetDetails = {
    target_type: ApplicationTarget;
    property_slug: string;
    property_name: string;
    unit_type_slug: string | null;
    unit_type_name: string | null;
};

export type ApplicationsIndexProps = {
    applications: ApplicantApplication[];
    operator: boolean;
};

export type ApplicationShowApplication = ApplicantApplication & {
    can_request_reservation: boolean;
};

export type ApplicationCreateProps = {
    target: ApplicationTargetDetails;
};

export type ApplicationShowProps = {
    application: ApplicationShowApplication | (OperatorApplication & { can_request_reservation: boolean });
    operator: boolean;
};

export type ApplicationIndexPageProps = {
    flash?: { status?: string };
};

export type ApplicationTransitionFormData = {
    status: ApplicationStatus;
    operator_notes: string;
    applicant_feedback: string;
};

export type ApplicationWithdrawalFormData = {
    status: 'withdrawn';
};

export type ApplicationActionErrors = {
    application?: string;
};

export type ReservationFormData = {
    move_in_date: string;
};

export type ReservationConfirmationFormData = {
    unit_id: string;
};

export type AccountApplicationSummary = {
    id: number;
    status: ApplicationStatus;
    property_name: string | null;
    unit_type_name: string | null;
};
