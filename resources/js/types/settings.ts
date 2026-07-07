export type SettingDefinition = {
    key: string;
    label: string;
    type: 'string' | 'bool' | 'int' | 'json' | 'encrypted';
    default: unknown;
    rules: string[];
    page: string | null;
};

export type DynamicSettingsFormProps = {
    page: string;
    definitions: SettingDefinition[];
    values: Record<string, unknown>;
};
