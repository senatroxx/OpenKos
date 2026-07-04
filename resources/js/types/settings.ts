export type DriverSchemaField = {
    label: string;
    type: string;
    required: boolean;
    placeholder?: string;
};

export type Driver = {
    name: string;
    label: string;
    configuration_schema: Record<string, DriverSchemaField>;
    supports_pairing: boolean;
};
