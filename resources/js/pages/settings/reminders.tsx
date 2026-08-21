import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import {
    edit as editReminders,
    update as updateReminders,
} from '@/routes/settings/reminders';

const channelOptions = [
    { value: 'log', label: 'Log only' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'mail', label: 'Email' },
] as const;

const templateOptions = [
    { value: 'upcoming', label: 'Upcoming reminder' },
    { value: 'due_today', label: 'Due today reminder' },
    { value: 'overdue', label: 'Overdue reminder' },
] as const;

function renderTemplate(
    template: string | null,
    data: Record<string, string | number>,
): string | null {
    if (!template) {
        return null;
    }

    return Object.entries(data).reduce(
        (str, [key, val]) => str.split(`:${key}`).join(String(val)),
        template,
    );
}

export default function Reminders({
    settings,
    defaultTemplates,
    previewInvoiceContext,
    previewAmount,
    previewInvoiceLink,
}: {
    settings: {
        reminder_enabled: boolean;
        reminder_days_before: number;
        reminder_overdue_intervals: number[];
        reminder_message_templates: Record<string, string | null>;
        reminder_channels: string[];
    };
    defaultTemplates: Record<string, string>;
    previewInvoiceContext: string;
    previewAmount: string;
    previewInvoiceLink: string;
}) {
    const {
        data,
        setData,
        submit,
        transform,
        setDefaults,
        isDirty,
        processing,
        errors,
    } = useForm({
        reminder_enabled: settings.reminder_enabled,
        reminder_days_before: String(settings.reminder_days_before),
        reminder_overdue_intervals:
            settings.reminder_overdue_intervals.join(', '),
        reminder_channels: settings.reminder_channels ?? ['log'],
        reminder_message_templates: {
            upcoming: settings.reminder_message_templates.upcoming ?? '',
            due_today: settings.reminder_message_templates.due_today ?? '',
            overdue: settings.reminder_message_templates.overdue ?? '',
        },
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        transform((d) => ({
            ...d,
            reminder_enabled: d.reminder_enabled ? '1' : '0',
        }));
        submit(updateReminders(), {
            onSuccess: () => setDefaults(),
        });
    }

    const preview = {
        name: 'John',
        unit: 'Unit A-02',
        days: settings.reminder_days_before,
        amount: previewAmount,
        date: '01 Jul 2026',
        overdueDays: 3,
    };

    const previewInvoiceData = {
        name: preview.name,
        unit: preview.unit,
        amount: preview.amount,
        date: preview.date,
        invoice_context: previewInvoiceContext,
        invoice_link: previewInvoiceLink,
    };

    const previewData = {
        upcoming: { ...previewInvoiceData, days: preview.days },
        due_today: { ...previewInvoiceData, days: 0 },
        overdue: { ...previewInvoiceData, days: preview.overdueDays },
    };

    return (
        <div className="space-y-6">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <h2 className="text-lg font-medium">
                        Default reminder settings
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Configure when and how rent reminders are sent to
                        tenants.
                    </p>
                </div>
                <Button
                    type="submit"
                    form="reminder-settings-form"
                    disabled={!isDirty || processing}
                >
                    Save
                </Button>
            </div>

            <form id="reminder-settings-form" onSubmit={handleSubmit}>
                <div className="grid gap-6 lg:grid-cols-2 lg:items-start">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Enable Reminders</CardTitle>
                                <CardDescription>
                                    Automatically send rent reminders to tenants
                                    via WhatsApp.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center gap-3">
                                    <Switch
                                        checked={data.reminder_enabled}
                                        onCheckedChange={(checked) =>
                                            setData('reminder_enabled', checked)
                                        }
                                    />
                                    <span className="text-sm text-muted-foreground">
                                        {data.reminder_enabled
                                            ? 'Reminders are active'
                                            : 'Reminders are disabled'}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Notification Channels</CardTitle>
                                <CardDescription>
                                    Choose how reminders are delivered.
                                    Reminders are always logged regardless of
                                    channel selection.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-wrap gap-6">
                                    {channelOptions.map(({ value, label }) => (
                                        <label
                                            key={value}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <Checkbox
                                                name={`reminder_channels[]`}
                                                value={value}
                                                checked={data.reminder_channels.includes(
                                                    value,
                                                )}
                                                onCheckedChange={(checked) => {
                                                    setData(
                                                        'reminder_channels',
                                                        checked
                                                            ? [
                                                                  ...data.reminder_channels,
                                                                  value,
                                                              ]
                                                            : data.reminder_channels.filter(
                                                                  (c) =>
                                                                      c !==
                                                                      value,
                                                              ),
                                                    );
                                                }}
                                            />
                                            {label}
                                        </label>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Message Templates</CardTitle>
                                <CardDescription>
                                    Customize each reminder message. Available
                                    placeholders: <code>:name</code>,{' '}
                                    <code>:unit</code>, <code>:days</code>,{' '}
                                    <code>:amount</code>, <code>:date</code>,{' '}
                                    <code>:invoice_context</code>, and{' '}
                                    <code>:invoice_link</code>. The invoice
                                    placeholders are optional.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-6">
                                {templateOptions.map(({ value, label }) => (
                                    <div key={value} className="grid gap-2">
                                        <Label
                                            htmlFor={`reminder_${value}_template`}
                                        >
                                            {label}
                                        </Label>
                                        <Textarea
                                            id={`reminder_${value}_template`}
                                            name={`reminder_message_templates[${value}]`}
                                            className="min-h-[120px]"
                                            placeholder={
                                                defaultTemplates[value]
                                            }
                                            value={
                                                data.reminder_message_templates[
                                                    value
                                                ]
                                            }
                                            onChange={(e) =>
                                                setData(
                                                    'reminder_message_templates',
                                                    {
                                                        ...data.reminder_message_templates,
                                                        [value]: e.target.value,
                                                    },
                                                )
                                            }
                                        />
                                        {errors[
                                            `reminder_message_templates.${value}`
                                        ] && (
                                            <p className="text-sm text-red-600">
                                                {
                                                    errors[
                                                        `reminder_message_templates.${value}`
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Overdue Follow-ups</CardTitle>
                                <CardDescription>
                                    Send follow-up reminders at these intervals
                                    (in days) after the due date passes.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid max-w-xs gap-2">
                                    <Label htmlFor="reminder_overdue_intervals">
                                        Intervals (days)
                                    </Label>
                                    <Input
                                        id="reminder_overdue_intervals"
                                        name="reminder_overdue_intervals"
                                        value={data.reminder_overdue_intervals}
                                        onChange={(e) =>
                                            setData(
                                                'reminder_overdue_intervals',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="1, 3, 7"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Comma-separated list of days after due
                                        date to send reminders.
                                    </p>
                                    {errors.reminder_overdue_intervals && (
                                        <p className="text-sm text-red-600">
                                            {errors.reminder_overdue_intervals}
                                        </p>
                                    )}
                                </div>
                                {data.reminder_enabled && (
                                    <div className="mt-6 rounded-lg border bg-muted/50 p-4">
                                        <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                            Preview (overdue)
                                        </p>
                                        <pre className="text-sm whitespace-pre-wrap">
                                            {renderTemplate(
                                                data.reminder_message_templates
                                                    .overdue ||
                                                    defaultTemplates.overdue,
                                                previewData.overdue,
                                            )}
                                        </pre>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Upcoming Reminder</CardTitle>
                                <CardDescription>
                                    Send a reminder this many days before rent
                                    is due.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid max-w-xs gap-2">
                                    <Label htmlFor="reminder_days_before">
                                        Days before due
                                    </Label>
                                    <Input
                                        id="reminder_days_before"
                                        name="reminder_days_before"
                                        type="number"
                                        min={0}
                                        max={30}
                                        value={data.reminder_days_before}
                                        onChange={(e) =>
                                            setData(
                                                'reminder_days_before',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    {errors.reminder_days_before && (
                                        <p className="text-sm text-red-600">
                                            {errors.reminder_days_before}
                                        </p>
                                    )}
                                </div>
                                {data.reminder_enabled && (
                                    <div className="mt-6 rounded-lg border bg-muted/50 p-4">
                                        <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                            Preview (upcoming)
                                        </p>
                                        <pre className="text-sm whitespace-pre-wrap">
                                            {renderTemplate(
                                                data.reminder_message_templates
                                                    .upcoming ||
                                                    defaultTemplates.upcoming,
                                                previewData.upcoming,
                                            )}
                                        </pre>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Due Today</CardTitle>
                                <CardDescription>
                                    Preview the reminder sent on the rent due
                                    date.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {data.reminder_enabled && (
                                    <div className="rounded-lg border bg-muted/50 p-4">
                                        <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                            Preview (due today)
                                        </p>
                                        <pre className="text-sm whitespace-pre-wrap">
                                            {renderTemplate(
                                                data.reminder_message_templates
                                                    .due_today ||
                                                    defaultTemplates.due_today,
                                                previewData.due_today,
                                            )}
                                        </pre>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>
        </div>
    );
}

Reminders.layout = {
    breadcrumbs: [{ title: 'Reminder settings', href: editReminders() }],
};
