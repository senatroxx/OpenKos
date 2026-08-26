export type SettingDefinition = {
    key: string;
    label: string;
    type: 'string' | 'bool' | 'int' | 'json' | 'encrypted';
    default: unknown;
    rules: string[];
    page: string | null;
};

export interface MailConfig {
    driver?: string | null;
    host?: string | null;
    port?: number | null;
    username?: string | null;
    encryption?: string | null;
    from_address?: string | null;
    from_name?: string | null;
}

export type Driver = {
    name: string;
    label: string;
    configuration_schema?: Record<
        string,
        {
            label: string;
            required?: boolean;
            type?: string;
            placeholder?: string;
            options?: Array<{ value: string; label: string }>;
        }
    >;
};

export type DynamicSettingsFormProps = {
    definitions: SettingDefinition[];
    values: Record<string, unknown>;
};

export type PaymentGatewayField = {
    label: string;
    type?: string;
    required?: boolean;
    placeholder?: string;
    description?: string;
    instructions?: string[];
    link?: {
        label: string;
        url: string;
    };
    url?: string;
    options?: Array<{ value: string; label: string }>;
    presentation?: string;
    default?: string | number | boolean;
    visible_when?: {
        field: string;
        value: string;
    };
    secret?: boolean;
};

export type PaymentGateway = {
    key: string;
    label: string;
    configuration_schema: Record<string, PaymentGatewayField>;
    configuration: Record<string, string | number | boolean>;
    secret_fields: string[];
    status: 'configured' | 'incomplete' | 'unavailable';
    missing_fields: string[];
    supported_currencies: string[] | null;
    error: string | null;
};

export type PaymentGatewaySettingsProps = {
    gateways: PaymentGateway[];
    active_key: string | null;
    active_status: 'none' | 'active' | 'incomplete' | 'unavailable';
    active_payment_attempt_count: number;
};
