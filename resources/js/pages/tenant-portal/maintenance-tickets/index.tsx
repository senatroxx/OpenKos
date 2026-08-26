import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { InputError } from '@/components/shared';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/formatters';
import { t } from '@/lib/i18n';

type Ticket = {
    id: number;
    reference: string;
    title: string;
    status: string;
    priority: string;
    created_at: string;
    property_name?: string;
    unit_name?: string;
    location?: string;
};

type ActiveLease = {
    id: number;
    property_id: number;
    property_name: string;
    unit_id: number;
    unit_name: string;
};

type Props = {
    tickets: {
        data: Ticket[];
        links: any;
    };
    activeLease: ActiveLease | null;
};

export default function MaintenanceTickets({ tickets, activeLease }: Props) {
    const [sheetOpen, setSheetOpen] = useState(false);

    return (
        <>
            <Head title={t('Maintenance')} />
            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-5 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {t('Maintenance')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('Report issues and track their progress.')}
                        </p>
                    </div>
                    {activeLease && (
                        <Button onClick={() => setSheetOpen(true)}>
                            {t('Report Issue')}
                        </Button>
                    )}
                </div>

                <div className="space-y-4">
                    {tickets.data.length === 0 ? (
                        <Card>
                            <CardContent className="flex min-h-40 flex-col items-center justify-center p-6 text-center">
                                <p className="text-sm text-muted-foreground">
                                    {t('No maintenance tickets submitted.')}
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {tickets.data.map((ticket) => (
                                <Link
                                    key={ticket.id}
                                    href={`/portal/maintenance-tickets/${ticket.id}`}
                                >
                                    <Card className="transition-colors hover:bg-accent/40">
                                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                            <CardTitle className="text-sm font-medium">
                                                {ticket.reference}
                                            </CardTitle>
                                            <StatusBadge
                                                domain="maintenance"
                                                value={ticket.status}
                                            />
                                        </CardHeader>
                                        <CardContent className="space-y-2">
                                            <p className="truncate text-base font-semibold">
                                                {ticket.title}
                                            </p>
                                            <div className="flex items-center justify-between text-xs text-muted-foreground">
                                                <span>
                                                    {ticket.unit_name
                                                        ? `${ticket.property_name} · ${ticket.unit_name}`
                                                        : ticket.location
                                                          ? `${ticket.property_name} · ${ticket.location}`
                                                          : (ticket.property_name ??
                                                            '—')}
                                                </span>
                                                <span>
                                                    {formatDate(
                                                        ticket.created_at,
                                                    )}
                                                </span>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {activeLease && (
                <PortalTicketFormSheet
                    open={sheetOpen}
                    onOpenChange={setSheetOpen}
                    activeLease={activeLease}
                />
            )}
        </>
    );
}

function PortalTicketFormSheet({
    open,
    onOpenChange,
    activeLease,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    activeLease: ActiveLease;
}) {
    const [locationType, setLocationType] = useState<'unit' | 'area'>('unit');

    const { data, setData, post, reset, processing, errors } = useForm({
        title: '',
        description: '',
        location_type: 'unit' as 'unit' | 'area',
        location: '',
    });

    function handleOpenChange(next: boolean) {
        onOpenChange(next);

        if (!next) {
            reset();
            setLocationType('unit');
        }
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/portal/maintenance-tickets', {
            onSuccess: () => handleOpenChange(false),
        });
    }

    return (
        <Sheet open={open} onOpenChange={handleOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{t('Report Maintenance Issue')}</SheetTitle>
                    <SheetDescription>
                        {t('Report a maintenance issue at your property.')}
                    </SheetDescription>
                </SheetHeader>

                <form
                    onSubmit={handleSubmit}
                    className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6"
                >
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label>{t('Property')}</Label>
                            <Input value={activeLease.property_name} disabled />
                        </div>

                        <div className="grid gap-2">
                            <Label>{t('Location')}</Label>
                            <div className="flex gap-2">
                                <Select
                                    value={locationType}
                                    onValueChange={(v: 'unit' | 'area') => {
                                        setLocationType(v);
                                        setData('location_type', v);
                                    }}
                                >
                                    <SelectTrigger className="w-36 shrink-0">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="unit">
                                            {t('Unit')}
                                        </SelectItem>
                                        <SelectItem value="area">
                                            {t('Common Area')}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {locationType === 'unit' ? (
                                    <Input
                                        value={activeLease.unit_name}
                                        disabled
                                    />
                                ) : (
                                    <Input
                                        value={data.location}
                                        onChange={(e) =>
                                            setData('location', e.target.value)
                                        }
                                        placeholder={t(
                                            'e.g. Lobby, 3rd Floor Hallway',
                                        )}
                                    />
                                )}
                            </div>
                            <InputError message={errors.location} />
                        </div>

                        <div className="grid gap-2">
                            <Label>{t('Title')}</Label>
                            <Input
                                required
                                value={data.title}
                                onChange={(e) =>
                                    setData('title', e.target.value)
                                }
                                placeholder={t('e.g. Leaking faucet')}
                            />
                            <InputError message={errors.title} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="description">
                                {t('Description')}
                            </Label>
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                placeholder={t('Describe the issue in detail')}
                            />
                            <InputError message={errors.description} />
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center justify-end gap-4">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => handleOpenChange(false)}
                            disabled={processing}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button disabled={processing} type="submit">
                            {t('Submit')}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
