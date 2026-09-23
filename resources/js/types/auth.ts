export type User = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    id_card_number: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    avatar?: string;
    email_verified_at: string | null;
    is_active: boolean;
    last_login_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    tenant: { id: number; name: string } | null;
    role: string | null;
    roles: string[];
    permissions: string[];
};

export type LoginPageProps = {
    status?: string;
    canResetPassword: boolean;
    redirect?: string;
};

export type RegisterPageProps = {
    redirect?: string;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
