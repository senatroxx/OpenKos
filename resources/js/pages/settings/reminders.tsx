import { useForm } from '@inertiajs/react';
import { update } from '@/actions/App/Http/Controllers/Settings/ReminderController';
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
import { edit as editReminders } from '@/routes/settings/reminders';

const channelOptions = [
    { value: 'log', label: 'Log only' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'mail', label: 'Email' },
] as const;

function renderTemplate(
    template: string | null,
    data: Record<string, string | number>,
): string | null {
    if (!template) {
        return null;
    }

    return Object.entries(data).reduce(
        (str, [key, val]) => str.replace(`:${key}`, String(val)),
        template,
    );
}

export default function Reminders({
    settings,
}: {
    settings: {
        reminder_enabled: boolean;
        reminder_days_before: number;
        reminder_overdue_intervals: number[];
        reminder_message_template: string | null;
        reminder_channels: string[];
    };
}) {
    const { data, setData, processing, errors, submit } = useForm({
        reminder_enabled: settings.reminder_enabled,
        reminder_days_before: settings.reminder_days_before,
        reminder_overdue_intervals: settings.reminder_overdue_intervals.join(', '),
        reminder_message_template: settings.reminder_message_template ?? '',
        reminder_channels: settings.reminder_channels ?? ['log'],
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        submit(update());
    }

    const preview = {
        name: 'John',
        unit: 'Unit A-02',
        days: settings.reminder_days_before,
        amount: '1,500,000',
        date: '01 Jul 2026',
        overdueDays: 3,
    };

    const renderedUpcoming = renderTemplate(data.reminder_message_template, {
        name: preview.name,
        unit: preview.unit,
        days: String(data.reminder_days_before ?? settings.reminder_days_before),
        amount: preview.amount,
        date: preview.date,
    });
    const renderedOverdue = renderTemplate(data.reminder_message_template, {
        name: preview.name,
        unit: preview.unit,
        days: String(preview.overdueDays),
        amount: preview.amount,
        date: preview.date,
    });

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-medium">
                    Default reminder settings
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Configure when and how rent reminders are sent to tenants.
                </p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
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
                                onCheckedChange={(v) => setData('reminder_enabled', v)}
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
                                type="number"
                                min={0}
                                max={30}
                                value={data.reminder_days_before}
                                 onChange={e => {
                                    const val = e.target.value;
                                    setData('reminder_days_before', val === '' ? 0 : Number(val));
                                }}
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
                                    {renderedUpcoming ??
                                        `Hi ${preview.name},

Rent for ${preview.unit} is due in ${preview.days} days.

Amount: ${preview.amount}
Due date: ${preview.date}`}
                                </pre>
                            </div>
                        )}
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
                                value={data.reminder_overdue_intervals}
                                onChange={e => setData('reminder_overdue_intervals', e.target.value)}
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
                                    {renderedOverdue ??
                                        `Hi ${preview.name},

Rent for ${preview.unit} is overdue by ${preview.overdueDays} day(s).

Amount: ${preview.amount}`}
                                </pre>
                            </div>
                        )}
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
                                        value={value}
                                        checked={(data.reminder_channels as string[]).includes(
                                            value,
                                        )}
                                        onCheckedChange={(checked) => {
                                            const channels = data.reminder_channels as string[];
                                            setData('reminder_channels',
                                                checked
                                                    ? [
                                                          ...channels,
                                                          value,
                                                      ]
                                                    : channels.filter(
                                                          (c: string) =>
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
                        <CardTitle>Message Template</CardTitle>
                        <CardDescription>
                            Customize the reminder message. Available
                            placeholders: <code>:name</code>,{' '}
                            <code>:unit</code>, <code>:days</code>,{' '}
                            <code>:amount</code>, <code>:date</code>.
                            Leave empty to use defaults.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-2">
                            <Label htmlFor="reminder_message_template">
                                Template
                            </Label>
                            <textarea
                                id="reminder_message_template"
                                className="flex min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder={`Hi :name,

Rent for :unit is due in :days days.

Amount: :amount
Due date: :date`}
                                value={data.reminder_message_template}
                                onChange={(e) =>
                                    setData('reminder_message_template', e.target.value)
                                }
                            />
                            {errors.reminder_message_template && (
                                <p className="text-sm text-red-600">
                                    {errors.reminder_message_template}
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <div className="flex justify-end">
                    <Button disabled={processing}>Save</Button>
                </div>
            </form>
        </div>
    );
}

Reminders.layout = {
    breadcrumbs: [{ title: 'Reminder settings', href: editReminders() }],
};
