export type SettingDefinition = {
    key: string;
    label: string;
    type: 'string' | 'bool' | 'int' | 'json' | 'encrypted';
    default: unknown;
    rules: string[];
    page: string | null;
};

export interface MailConfig {
    driver?: string;
    host?: string;
    port?: number;
    username?: string;
    encryption?: string;
    from_address?: string;
    from_name?: string;
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
