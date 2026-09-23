export type ApplicationTarget = 'whole_property' | 'unit_type';

export type ApplicationStatus =
    | 'new'
    | 'reviewing'
    | 'accepted'
    | 'rejected'
    | 'withdrawn';

export type ApplicantApplication = {
    id: number;
    status: ApplicationStatus;
    target_type: ApplicationTarget;
    property: { id: number; name: string; public_slug: string } | null;
    unit_type: { id: number; name: string; public_slug: string } | null;
    intended_move_in_date: string | null;
    intended_move_in_timeframe: string | null;
    applicant_message: string | null;
    applicant_feedback: string | null;
    converted_at: string | null;
};

export type OperatorApplication = ApplicantApplication & {
    applicant: { name: string; email: string; phone: string | null };
    operator_notes: string | null;
    reviewed_at: string | null;
    converted_tenant_id: number | null;
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

export type ApplicationCreateProps = {
    target: ApplicationTargetDetails;
};

export type ApplicationShowProps = {
    application: ApplicantApplication | OperatorApplication;
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

export type AccountApplicationSummary = {
    id: number;
    status: ApplicationStatus;
    property_name: string | null;
    unit_type_name: string | null;
};
