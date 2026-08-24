import { useForm } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SegmentedToggle } from '@/components/ui/segmented-toggle';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { update as updatePaymentGateway } from '@/routes/settings/payment-gateway';
import type {
    PaymentGateway,
    PaymentGatewayField,
    PaymentGatewaySettingsProps,
} from '@/types/settings';

const NONE = '__none__';

export default function PaymentGateway({
    gateways,
    active_key: activeKey,
    active_status: activeStatus,
    active_payment_attempt_count: activePaymentAttemptCount,
}: PaymentGatewaySettingsProps) {
    const hasActivePaymentAttempts = activePaymentAttemptCount > 0;
    const initialKey = gateways.some((gateway) => gateway.key === activeKey)
        ? activeKey!
        : NONE;
    const [configs, setConfigs] = useState<
        Record<string, Record<string, string>>
    >(
        Object.fromEntries(
            gateways.map((gateway) => [
                gateway.key,
                Object.fromEntries(
                    Object.entries(gateway.configuration).map(
                        ([key, value]) => [key, String(value)],
                    ),
                ),
            ]),
        ),
    );
    const { data, setData, transform, submit, processing, errors } = useForm({
        gateway: initialKey,
        configuration: configs[initialKey] ?? {},
    });

    const selectedGateway = gateways.find(
        (gateway) => gateway.key === data.gateway,
    );
    const selectedConfig = data.configuration;
    const selectedFieldState = selectedGateway
        ? getVisibleGatewayFields(selectedGateway, selectedConfig)
        : null;
    const selectedInformationFields =
        selectedFieldState?.visibleFields.filter(
            ([, field]) => field.type === 'info',
        ) ?? [];

    function selectGateway(key: string) {
        const configuration = configs[key] ?? {};

        setData({ gateway: key, configuration });
    }

    function setConfiguration(key: string, value: string) {
        const configuration = { ...selectedConfig, [key]: value };

        setConfigs((current) => ({
            ...current,
            [data.gateway]: configuration,
        }));
        setData('configuration', configuration);
    }

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();

        transform((form) => ({
            ...form,
            gateway: form.gateway === NONE ? null : form.gateway,
        }));
        submit(updatePaymentGateway());
    }

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-medium">Payment Gateway</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Configure the payment gateway used for online invoice
                    payments.
                </p>
            </div>

            {activeStatus === 'unavailable' && (
                <Alert variant="destructive">
                    <AlertTitle>Payment gateway unavailable</AlertTitle>
                    <AlertDescription>
                        The configured gateway ({activeKey}) is not currently
                        installed or could not be loaded. Select another gateway
                        to recover.
                    </AlertDescription>
                </Alert>
            )}

            {activeStatus === 'incomplete' && (
                <Alert>
                    <AlertTitle>Payment gateway needs configuration</AlertTitle>
                    <AlertDescription>
                        Complete the required fields before online payments can
                        use the active gateway.
                    </AlertDescription>
                </Alert>
            )}

            {hasActivePaymentAttempts && (
                <Alert>
                    <Info />
                    <AlertTitle>
                        Gateway changes are temporarily unavailable
                    </AlertTitle>
                    <AlertDescription>
                        {activePaymentAttemptCount} active online payment{' '}
                        {activePaymentAttemptCount === 1
                            ? 'attempt is'
                            : 'attempts are'}{' '}
                        in progress. Wait until{' '}
                        {activePaymentAttemptCount === 1
                            ? 'it completes'
                            : 'they complete'}{' '}
                        or expire before switching or deactivating the gateway.
                    </AlertDescription>
                </Alert>
            )}

            {gateways.length === 0 ? (
                <Alert>
                    <AlertTitle>No payment gateways installed</AlertTitle>
                    <AlertDescription>
                        Install a payment gateway plugin before activating
                        online invoice payments.
                    </AlertDescription>
                </Alert>
            ) : (
                <form onSubmit={handleSubmit}>
                    <div
                        className={
                            selectedInformationFields.length > 0
                                ? 'grid items-start gap-6 lg:grid-cols-2'
                                : undefined
                        }
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle>Payment gateway</CardTitle>
                                <CardDescription>
                                    Only one installed and fully configured
                                    gateway can be active at a time.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid max-w-md gap-2">
                                    <Label htmlFor="payment_gateway">
                                        Active gateway
                                    </Label>
                                    <Select
                                        value={data.gateway}
                                        onValueChange={selectGateway}
                                    >
                                        <SelectTrigger
                                            id="payment_gateway"
                                            disabled={hasActivePaymentAttempts}
                                        >
                                            <SelectValue placeholder="Select a gateway" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>
                                                No active gateway
                                            </SelectItem>
                                            {gateways.map((gateway) => (
                                                <SelectItem
                                                    key={gateway.key}
                                                    value={gateway.key}
                                                    disabled={
                                                        gateway.status ===
                                                            'unavailable' &&
                                                        gateway.key !==
                                                            activeKey
                                                    }
                                                >
                                                    {gateway.label}
                                                    {gateway.status !==
                                                    'configured'
                                                        ? ` (${gateway.status})`
                                                        : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.gateway && (
                                        <p className="text-sm text-red-600">
                                            {errors.gateway}
                                        </p>
                                    )}
                                    {selectedGateway &&
                                        Array.isArray(
                                            selectedGateway.supported_currencies,
                                        ) && (
                                            <p className="text-xs text-muted-foreground">
                                                Supported currencies:{' '}
                                                {selectedGateway.supported_currencies.join(
                                                    ', ',
                                                ) || 'None'}
                                            </p>
                                        )}
                                </div>

                                {selectedGateway?.status === 'unavailable' && (
                                    <Alert variant="destructive">
                                        <AlertTitle>
                                            {selectedGateway.label}
                                        </AlertTitle>
                                        <AlertDescription>
                                            {selectedGateway.error}
                                        </AlertDescription>
                                    </Alert>
                                )}

                                {selectedGateway &&
                                    selectedGateway.status !==
                                        'unavailable' && (
                                        <GatewayConfiguration
                                            gateway={selectedGateway}
                                            configuration={selectedConfig}
                                            errors={errors}
                                            onChange={setConfiguration}
                                        />
                                    )}
                            </CardContent>
                            <CardFooter>
                                <Button disabled={processing}>Save</Button>
                            </CardFooter>
                        </Card>

                        {selectedGateway && selectedFieldState && (
                            <div className="space-y-4">
                                {selectedInformationFields.map(
                                    ([key, field]) => (
                                        <GatewayField
                                            key={key}
                                            fieldKey={key}
                                            field={field}
                                            value={
                                                selectedFieldState
                                                    .resolvedConfiguration[
                                                    key
                                                ] ?? ''
                                            }
                                            hasSavedSecret={selectedGateway.secret_fields.includes(
                                                key,
                                            )}
                                            error={
                                                errors[`configuration.${key}`]
                                            }
                                            onChange={setConfiguration}
                                        />
                                    ),
                                )}
                            </div>
                        )}
                    </div>
                </form>
            )}
        </div>
    );
}

function GatewayConfiguration({
    gateway,
    configuration,
    errors,
    onChange,
}: {
    gateway: PaymentGateway;
    configuration: Record<string, string>;
    errors: Record<string, string>;
    onChange: (key: string, value: string) => void;
}) {
    const { fields, resolvedConfiguration, visibleFields } =
        getVisibleGatewayFields(gateway, configuration);
    const configurationFields = visibleFields.filter(
        ([, field]) => field.type !== 'info',
    );

    const renderField = ([key, field]: [string, PaymentGatewayField]) => (
        <GatewayField
            key={key}
            fieldKey={key}
            field={field}
            value={resolvedConfiguration[key] ?? ''}
            hasSavedSecret={gateway.secret_fields.includes(key)}
            error={errors[`configuration.${key}`]}
            onChange={onChange}
        />
    );

    if (fields.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                This gateway does not require additional configuration.
            </p>
        );
    }

    return (
        <div className="max-w-md space-y-4">
            {configurationFields.map(renderField)}
        </div>
    );
}

function getVisibleGatewayFields(
    gateway: PaymentGateway,
    configuration: Record<string, string>,
) {
    const fields = Object.entries(gateway.configuration_schema);
    const resolvedConfiguration = Object.fromEntries(
        fields.map(([key, field]) => [
            key,
            configuration[key] ??
                (field.default === undefined ? '' : String(field.default)),
        ]),
    );
    const visibleFields = fields.filter(([, field]) => {
        const condition = field.visible_when;

        return (
            condition === undefined ||
            resolvedConfiguration[condition.field] === condition.value
        );
    });

    return { fields, resolvedConfiguration, visibleFields };
}

function GatewayField({
    fieldKey,
    field,
    value,
    hasSavedSecret,
    error,
    onChange,
}: {
    fieldKey: string;
    field: PaymentGatewayField;
    value: string;
    hasSavedSecret: boolean;
    error?: string;
    onChange: (key: string, value: string) => void;
}) {
    if (field.type === 'info') {
        const resolvedUrl = field.url
            ? typeof window === 'undefined'
                ? field.url
                : new URL(field.url, window.location.origin).toString()
            : null;

        return (
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">{field.label}</CardTitle>
                    {field.description && (
                        <CardDescription>{field.description}</CardDescription>
                    )}
                </CardHeader>
                {(field.instructions?.length || resolvedUrl || field.link) && (
                    <CardContent className="space-y-4">
                        {field.instructions?.length ? (
                            <ol className="list-decimal space-y-2 pl-5 text-sm">
                                {field.instructions.map(
                                    (instruction, index) => (
                                        <li key={index}>{instruction}</li>
                                    ),
                                )}
                            </ol>
                        ) : null}
                        {resolvedUrl && (
                            <div className="space-y-1">
                                <p className="text-sm font-medium">
                                    Webhook URL
                                </p>
                                <code className="block rounded-md border bg-muted px-3 py-2 text-xs break-all">
                                    {resolvedUrl}
                                </code>
                            </div>
                        )}
                        {field.link && (
                            <a
                                href={field.link.url}
                                target="_blank"
                                rel="noreferrer"
                                className="text-sm font-medium text-primary underline underline-offset-4"
                            >
                                {field.link.label}
                            </a>
                        )}
                    </CardContent>
                )}
            </Card>
        );
    }

    const label = (
        <>
            {field.label}
            {field.required && <span className="text-destructive"> *</span>}
        </>
    );

    return (
        <div className="grid gap-2">
            <Label
                htmlFor={
                    field.presentation === 'segmented'
                        ? undefined
                        : `payment_gateway_${fieldKey}`
                }
            >
                {label}
            </Label>
            {field.presentation === 'segmented' && field.options ? (
                <SegmentedToggle
                    ariaLabel={field.label}
                    className="max-w-md"
                    options={field.options}
                    value={value}
                    onChange={(next) => onChange(fieldKey, next)}
                />
            ) : field.type === 'select' && field.options ? (
                <Select
                    value={value}
                    onValueChange={(next) => onChange(fieldKey, next)}
                >
                    <SelectTrigger id={`payment_gateway_${fieldKey}`}>
                        <SelectValue
                            placeholder={field.placeholder ?? 'Select...'}
                        />
                    </SelectTrigger>
                    <SelectContent>
                        {field.options.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            ) : (
                <Input
                    id={`payment_gateway_${fieldKey}`}
                    type={
                        field.type === 'password' || field.secret
                            ? 'password'
                            : field.type === 'number'
                              ? 'number'
                              : 'text'
                    }
                    value={value}
                    onChange={(event) => onChange(fieldKey, event.target.value)}
                    placeholder={
                        hasSavedSecret ? '••••••••••••' : field.placeholder
                    }
                />
            )}
            {field.description && (
                <p className="text-sm text-muted-foreground">
                    {field.description}
                </p>
            )}
            {error && <p className="text-sm text-red-600">{error}</p>}
        </div>
    );
}
