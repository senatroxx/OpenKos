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
    configuration_schema?: Record<string, {
        label: string;
        required?: boolean;
        type?: string;
        placeholder?: string;
    }>;
};

export type DynamicSettingsFormProps = {
    definitions: SettingDefinition[];
    values: Record<string, unknown>;
};
