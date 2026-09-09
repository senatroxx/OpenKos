import { router, useForm, usePage } from '@inertiajs/react';
import { Ellipsis, Pencil, Plus, RotateCcw, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { InputError } from '@/components/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate, formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import properties from '@/routes/properties';
import type { Property, Unit, UtilityMeter, UtilityReading } from '@/types';
import { UnitLayout } from './layout';

type MeterFormData = {
    utility_type: UtilityMeter['utility_type'];
    identifier: string;
    measurement_unit: string;
    rate: string;
    currency: string;
    is_active: boolean;
};

type ReadingFormData = {
    reading_date: string;
    period_start: string;
    period_end: string;
    previous_reading: string;
    current_reading: string;
    reference: string;
    reading?: string;
};

type CorrectionFormData = {
    current_reading: string;
    reference: string;
    reading?: string;
};

const utilityLabels: Record<UtilityMeter['utility_type'], string> = {
    electricity: 'Electricity',
    water: 'Water',
    custom: 'Custom',
};

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

function periodEnd(periodStart: string): string {
    if (!periodStart) {
        return '';
    }

    const date = new Date(`${periodStart}T00:00:00`);
    date.setMonth(date.getMonth() + 1, 0);

    return date.toISOString().slice(0, 10);
}

function previewCharge(
    previous: string,
    current: string,
    rate: string,
    currency: string,
): string {
    const consumption = Number(current) - Number(previous);

    if (!Number.isFinite(consumption) || consumption < 0) {
        return '—';
    }

    return formatPrice(String(consumption * Number(rate)), currency);
}

export default function UnitUtilities({
    property,
    unit,
    meters,
}: {
    property: Property;
    unit: Unit;
    meters: UtilityMeter[];
}) {
    const { setting } = usePage<{
        setting: { currency: string; supported_currencies: string[] };
    }>().props;
    const defaultCurrency = setting.currency.toUpperCase();
    const supportedCurrencies = Array.from(
        new Set([defaultCurrency, ...setting.supported_currencies]),
    ).sort();
    const [meterDialogOpen, setMeterDialogOpen] = useState(false);
    const [readingDialogOpen, setReadingDialogOpen] = useState(false);
    const [correctionDialogOpen, setCorrectionDialogOpen] = useState(false);
    const [editingMeter, setEditingMeter] = useState<UtilityMeter | null>(null);
    const [selectedMeter, setSelectedMeter] = useState<UtilityMeter | null>(
        null,
    );
    const [editingReading, setEditingReading] = useState<UtilityReading | null>(
        null,
    );
    const [correctionReading, setCorrectionReading] =
        useState<UtilityReading | null>(null);
    const meterForm = useForm<MeterFormData>({
        utility_type: 'electricity',
        identifier: '',
        measurement_unit: 'kWh',
        rate: '',
        currency: defaultCurrency,
        is_active: true,
    });
    const readingForm = useForm<ReadingFormData>({
        reading_date: today(),
        period_start: today().slice(0, 7) + '-01',
        period_end: periodEnd(today().slice(0, 7) + '-01'),
        previous_reading: '',
        current_reading: '',
        reference: '',
    });
    const correctionForm = useForm<CorrectionFormData>({
        current_reading: '',
        reference: '',
    });

    function openMeterDialog(meter?: UtilityMeter) {
        setEditingMeter(meter ?? null);
        meterForm.setData(
            meter
                ? {
                      utility_type: meter.utility_type,
                      identifier: meter.identifier,
                      measurement_unit: meter.measurement_unit,
                      rate: meter.rate,
                      currency: meter.currency,
                      is_active: meter.is_active,
                  }
                : {
                      utility_type: 'electricity',
                      identifier: '',
                      measurement_unit: 'kWh',
                      rate: '',
                      currency: defaultCurrency,
                      is_active: true,
                  },
        );
        setMeterDialogOpen(true);
    }

    function closeMeterDialog(open: boolean) {
        setMeterDialogOpen(open);

        if (!open) {
            meterForm.reset();
            setEditingMeter(null);
        }
    }

    function submitMeter(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const route = editingMeter
            ? properties.units.utilities.meters.update({
                  property: property.slug,
                  unit: unit.slug,
                  meter: editingMeter.id,
              })
            : properties.units.utilities.meters.store({
                  property: property.slug,
                  unit: unit.slug,
              });

        meterForm.submit(editingMeter ? 'put' : 'post', route.url, {
            onSuccess: () => closeMeterDialog(false),
        });
    }

    function openReadingDialog(meter: UtilityMeter, reading?: UtilityReading) {
        const previous =
            reading?.previous_reading ??
            meter.readings.find((item) => item.reading_kind === 'reading')
                ?.current_reading ??
            '';
        const start = reading?.period_start ?? today().slice(0, 7) + '-01';

        setSelectedMeter(meter);
        setEditingReading(reading ?? null);
        readingForm.setData({
            reading_date: reading?.reading_date ?? today(),
            period_start: start,
            period_end: reading?.period_end ?? periodEnd(start),
            previous_reading: previous,
            current_reading: reading?.current_reading ?? '',
            reference: reading?.reference ?? '',
        });
        setReadingDialogOpen(true);
    }

    function closeReadingDialog(open: boolean) {
        setReadingDialogOpen(open);

        if (!open) {
            readingForm.reset();
            setSelectedMeter(null);
            setEditingReading(null);
        }
    }

    function submitReading(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!selectedMeter) {
            return;
        }

        const route = editingReading
            ? properties.units.utilities.readings.update({
                  property: property.slug,
                  unit: unit.slug,
                  meter: selectedMeter.id,
                  reading: editingReading.id,
              })
            : properties.units.utilities.readings.store({
                  property: property.slug,
                  unit: unit.slug,
                  meter: selectedMeter.id,
              });

        readingForm.submit(editingReading ? 'put' : 'post', route.url, {
            onSuccess: () => closeReadingDialog(false),
        });
    }

    function openCorrectionDialog(
        meter: UtilityMeter,
        reading: UtilityReading,
    ) {
        setSelectedMeter(meter);
        setCorrectionReading(reading);
        correctionForm.setData({
            current_reading: reading.current_reading,
            reference: '',
        });
        setCorrectionDialogOpen(true);
    }

    function closeCorrectionDialog(open: boolean) {
        setCorrectionDialogOpen(open);

        if (!open) {
            correctionForm.reset();
            setSelectedMeter(null);
            setCorrectionReading(null);
        }
    }

    function submitCorrection(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!selectedMeter || !correctionReading) {
            return;
        }

        correctionForm.post(
            properties.units.utilities.readings.corrections.store.url({
                property: property.slug,
                unit: unit.slug,
                meter: selectedMeter.id,
                reading: correctionReading.id,
            }),
            { onSuccess: () => closeCorrectionDialog(false) },
        );
    }

    function deleteReading(meter: UtilityMeter, reading: UtilityReading) {
        if (!reading.can_delete || !window.confirm(t('Delete this reading?'))) {
            return;
        }

        router.delete(
            properties.units.utilities.readings.destroy.url({
                property: property.slug,
                unit: unit.slug,
                meter: meter.id,
                reading: reading.id,
            }),
        );
    }

    return (
        <UnitLayout property={property} unit={unit} activeTab="utilities">
            <div className="w-full space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-semibold">
                            {t('Utility meters')}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Record unit utility usage for the next invoice.',
                            )}
                        </p>
                    </div>
                    <Button type="button" onClick={() => openMeterDialog()}>
                        <Plus />
                        {t('Add meter')}
                    </Button>
                </div>

                {meters.length === 0 ? (
                    <div className="rounded-lg border border-dashed px-6 py-12 text-center">
                        <h3 className="font-semibold">
                            {t('No utility meters')}
                        </h3>
                        <p className="mx-auto mt-1 max-w-md text-sm text-muted-foreground">
                            {t(
                                'Add a unit meter to start recording readings and billing usage.',
                            )}
                        </p>
                        <Button
                            className="mt-4"
                            type="button"
                            onClick={() => openMeterDialog()}
                        >
                            <Plus />
                            {t('Add first meter')}
                        </Button>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {meters.map((meter) => (
                            <section
                                key={meter.id}
                                className="overflow-hidden rounded-lg border bg-card"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-4 border-b px-4 py-4">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="font-semibold">
                                                {meter.identifier}
                                            </h3>
                                            <Badge variant="outline">
                                                {t(
                                                    utilityLabels[
                                                        meter.utility_type
                                                    ],
                                                )}
                                            </Badge>
                                            <Badge
                                                variant="outline"
                                                className={
                                                    meter.is_active
                                                        ? 'border-surface-green-border/80 bg-surface-green/70 text-surface-green-foreground'
                                                        : 'text-muted-foreground'
                                                }
                                            >
                                                {t(
                                                    meter.is_active
                                                        ? 'Active'
                                                        : 'Inactive',
                                                )}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {meter.measurement_unit} ·{' '}
                                            {formatPrice(
                                                meter.rate,
                                                meter.currency,
                                            )}{' '}
                                            {t('per unit')}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={() =>
                                                openReadingDialog(meter)
                                            }
                                            disabled={!meter.is_active}
                                        >
                                            <Plus />
                                            {t('Record reading')}
                                        </Button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    aria-label={`${t('Actions for')} ${meter.identifier}`}
                                                >
                                                    <Ellipsis />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        openMeterDialog(meter)
                                                    }
                                                >
                                                    <Pencil />
                                                    {t('Edit meter')}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() => {
                                                        router.put(
                                                            properties.units.utilities.meters.update.url(
                                                                {
                                                                    property:
                                                                        property.slug,
                                                                    unit: unit.slug,
                                                                    meter: meter.id,
                                                                },
                                                            ),
                                                            {
                                                                utility_type:
                                                                    meter.utility_type,
                                                                identifier:
                                                                    meter.identifier,
                                                                measurement_unit:
                                                                    meter.measurement_unit,
                                                                rate: meter.rate,
                                                                currency:
                                                                    meter.currency,
                                                                is_active:
                                                                    !meter.is_active,
                                                            },
                                                        );
                                                    }}
                                                >
                                                    {meter.is_active ? (
                                                        <X />
                                                    ) : (
                                                        <RotateCcw />
                                                    )}
                                                    {t(
                                                        meter.is_active
                                                            ? 'Deactivate'
                                                            : 'Reactivate',
                                                    )}
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>

                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[48rem] text-sm">
                                        <thead className="border-b bg-muted/40 text-left text-muted-foreground">
                                            <tr>
                                                <th className="px-4 py-3 font-medium">
                                                    {t('Period')}
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    {t('Reading')}
                                                </th>
                                                <th className="px-4 py-3 text-right font-medium">
                                                    {t('Consumption')}
                                                </th>
                                                <th className="px-4 py-3 text-right font-medium">
                                                    {t('Charge')}
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    {t('Status')}
                                                </th>
                                                <th className="px-4 py-3 text-right font-medium">
                                                    <span className="sr-only">
                                                        {t('Actions')}
                                                    </span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {meter.readings.length > 0 ? (
                                                meter.readings.map(
                                                    (reading) => (
                                                        <tr
                                                            key={reading.id}
                                                            className="border-b last:border-b-0"
                                                        >
                                                            <td className="px-4 py-3">
                                                                <div>
                                                                    {formatDate(
                                                                        reading.period_start,
                                                                    )}{' '}
                                                                    —{' '}
                                                                    {formatDate(
                                                                        reading.period_end,
                                                                    )}
                                                                </div>
                                                                <div className="text-xs text-muted-foreground">
                                                                    {formatDate(
                                                                        reading.reading_date,
                                                                    )}
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3 tabular-nums">
                                                                {
                                                                    reading.previous_reading
                                                                }{' '}
                                                                →{' '}
                                                                {
                                                                    reading.current_reading
                                                                }
                                                            </td>
                                                            <td className="px-4 py-3 text-right tabular-nums">
                                                                {reading.reading_kind ===
                                                                    'correction' &&
                                                                reading.adjustment_consumption !==
                                                                    null
                                                                    ? `${reading.consumption} (${reading.adjustment_consumption})`
                                                                    : reading.consumption}{' '}
                                                                {
                                                                    meter.measurement_unit
                                                                }
                                                            </td>
                                                            <td className="px-4 py-3 text-right font-medium tabular-nums">
                                                                {formatPrice(
                                                                    reading.charge_preview,
                                                                    reading.currency,
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <Badge
                                                                    variant="outline"
                                                                    className={
                                                                        reading.billed
                                                                            ? 'border-surface-blue-border/80 bg-surface-blue/70 text-surface-blue-foreground'
                                                                            : 'border-surface-amber-border/80 bg-surface-amber/30 text-surface-amber-foreground'
                                                                    }
                                                                >
                                                                    {t(
                                                                        reading.billed
                                                                            ? 'Billed'
                                                                            : 'Unbilled',
                                                                    )}
                                                                </Badge>
                                                                {reading.invoice_reference && (
                                                                    <div className="mt-1 text-xs text-muted-foreground">
                                                                        {
                                                                            reading.invoice_reference
                                                                        }
                                                                    </div>
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3 text-right">
                                                                <DropdownMenu>
                                                                    <DropdownMenuTrigger
                                                                        asChild
                                                                    >
                                                                        <Button
                                                                            type="button"
                                                                            variant="ghost"
                                                                            size="icon-sm"
                                                                            aria-label={`${t('Actions for')} ${formatDate(reading.period_start)}`}
                                                                        >
                                                                            <Ellipsis />
                                                                        </Button>
                                                                    </DropdownMenuTrigger>
                                                                    <DropdownMenuContent align="end">
                                                                        {reading.can_edit && (
                                                                            <DropdownMenuItem
                                                                                onSelect={() =>
                                                                                    openReadingDialog(
                                                                                        meter,
                                                                                        reading,
                                                                                    )
                                                                                }
                                                                            >
                                                                                <Pencil />
                                                                                {t(
                                                                                    'Edit reading',
                                                                                )}
                                                                            </DropdownMenuItem>
                                                                        )}
                                                                        {reading.can_delete && (
                                                                            <DropdownMenuItem
                                                                                onSelect={() =>
                                                                                    deleteReading(
                                                                                        meter,
                                                                                        reading,
                                                                                    )
                                                                                }
                                                                            >
                                                                                <Trash2 />
                                                                                {t(
                                                                                    'Delete reading',
                                                                                )}
                                                                            </DropdownMenuItem>
                                                                        )}
                                                                        {reading.billed &&
                                                                            reading.reading_kind ===
                                                                                'reading' &&
                                                                            !reading.correction_exists && (
                                                                                <DropdownMenuItem
                                                                                    onSelect={() =>
                                                                                        openCorrectionDialog(
                                                                                            meter,
                                                                                            reading,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    <Pencil />
                                                                                    {t(
                                                                                        'Create correction',
                                                                                    )}
                                                                                </DropdownMenuItem>
                                                                            )}
                                                                    </DropdownMenuContent>
                                                                </DropdownMenu>
                                                            </td>
                                                        </tr>
                                                    ),
                                                )
                                            ) : (
                                                <tr>
                                                    <td
                                                        colSpan={6}
                                                        className="px-4 py-8 text-center text-muted-foreground"
                                                    >
                                                        {t(
                                                            'No readings recorded yet.',
                                                        )}
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={meterDialogOpen} onOpenChange={closeMeterDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t(
                                editingMeter
                                    ? 'Edit utility meter'
                                    : 'Add utility meter',
                            )}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                'The rate is snapshotted on each reading and will not change billed history.',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitMeter} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="utility-type">
                                {t('Utility type')}
                            </Label>
                            <Select
                                value={meterForm.data.utility_type}
                                onValueChange={(value) =>
                                    meterForm.setData(
                                        'utility_type',
                                        value as UtilityMeter['utility_type'],
                                    )
                                }
                            >
                                <SelectTrigger id="utility-type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(utilityLabels).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {t(label)}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <InputError
                                message={meterForm.errors.utility_type}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="meter-identifier">
                                {t('Meter identifier')}
                            </Label>
                            <Input
                                id="meter-identifier"
                                required
                                value={meterForm.data.identifier}
                                onChange={(event) =>
                                    meterForm.setData(
                                        'identifier',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={meterForm.errors.identifier} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="measurement-unit">
                                {t('Unit of measure')}
                            </Label>
                            <Input
                                id="measurement-unit"
                                required
                                value={meterForm.data.measurement_unit}
                                onChange={(event) =>
                                    meterForm.setData(
                                        'measurement_unit',
                                        event.target.value,
                                    )
                                }
                                placeholder="kWh"
                            />
                            <InputError
                                message={meterForm.errors.measurement_unit}
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="meter-rate">{t('Rate')}</Label>
                                <Input
                                    id="meter-rate"
                                    type="number"
                                    min={0}
                                    step="any"
                                    inputMode="decimal"
                                    required
                                    value={meterForm.data.rate}
                                    onChange={(event) =>
                                        meterForm.setData(
                                            'rate',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={meterForm.errors.rate} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="meter-currency">
                                    {t('Currency')}
                                </Label>
                                <Select
                                    value={meterForm.data.currency}
                                    onValueChange={(value) =>
                                        meterForm.setData('currency', value)
                                    }
                                >
                                    <SelectTrigger id="meter-currency">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {supportedCurrencies.map((currency) => (
                                            <SelectItem
                                                key={currency}
                                                value={currency}
                                            >
                                                {currency}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={meterForm.errors.currency}
                                />
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="meter-active"
                                checked={meterForm.data.is_active}
                                onCheckedChange={(checked) =>
                                    meterForm.setData(
                                        'is_active',
                                        checked === true,
                                    )
                                }
                            />
                            <Label htmlFor="meter-active">{t('Active')}</Label>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => closeMeterDialog(false)}
                            >
                                {t('Cancel')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={meterForm.processing}
                            >
                                {meterForm.processing
                                    ? t('Saving...')
                                    : t('Save meter')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={readingDialogOpen} onOpenChange={closeReadingDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t(
                                editingReading
                                    ? 'Edit utility reading'
                                    : 'Record utility reading',
                            )}
                        </DialogTitle>
                        <DialogDescription>
                            {selectedMeter &&
                                `${selectedMeter.identifier} · ${selectedMeter.measurement_unit} · ${formatPrice(selectedMeter.rate, selectedMeter.currency)} ${t('per unit')}`}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitReading} className="grid gap-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="reading-period-start">
                                    {t('Period start')}
                                </Label>
                                <Input
                                    id="reading-period-start"
                                    type="date"
                                    required
                                    value={readingForm.data.period_start}
                                    onChange={(event) => {
                                        readingForm.setData(
                                            'period_start',
                                            event.target.value,
                                        );
                                        readingForm.setData(
                                            'period_end',
                                            periodEnd(event.target.value),
                                        );
                                    }}
                                />
                                <InputError
                                    message={readingForm.errors.period_start}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="reading-period-end">
                                    {t('Period end')}
                                </Label>
                                <Input
                                    id="reading-period-end"
                                    type="date"
                                    required
                                    value={readingForm.data.period_end}
                                    onChange={(event) =>
                                        readingForm.setData(
                                            'period_end',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={readingForm.errors.period_end}
                                />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="reading-date">
                                {t('Reading date')}
                            </Label>
                            <Input
                                id="reading-date"
                                type="date"
                                required
                                value={readingForm.data.reading_date}
                                onChange={(event) =>
                                    readingForm.setData(
                                        'reading_date',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={readingForm.errors.reading_date}
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="previous-reading">
                                    {t('Previous reading')}
                                </Label>
                                <Input
                                    id="previous-reading"
                                    type="number"
                                    min={0}
                                    step="any"
                                    required
                                    value={readingForm.data.previous_reading}
                                    onChange={(event) =>
                                        readingForm.setData(
                                            'previous_reading',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        readingForm.errors.previous_reading
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="current-reading">
                                    {t('Current reading')}
                                </Label>
                                <Input
                                    id="current-reading"
                                    type="number"
                                    min={0}
                                    step="any"
                                    required
                                    value={readingForm.data.current_reading}
                                    onChange={(event) =>
                                        readingForm.setData(
                                            'current_reading',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={readingForm.errors.current_reading}
                                />
                            </div>
                        </div>
                        {selectedMeter && (
                            <div className="rounded-md border bg-muted/30 px-3 py-2 text-sm">
                                <div className="flex justify-between gap-4">
                                    <span className="text-muted-foreground">
                                        {t('Consumption')}
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {readingForm.data.current_reading &&
                                        readingForm.data.previous_reading
                                            ? `${Math.max(0, Number(readingForm.data.current_reading) - Number(readingForm.data.previous_reading))} ${selectedMeter.measurement_unit}`
                                            : '—'}
                                    </span>
                                </div>
                                <div className="mt-1 flex justify-between gap-4">
                                    <span className="text-muted-foreground">
                                        {t('Charge preview')}
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {previewCharge(
                                            readingForm.data.previous_reading,
                                            readingForm.data.current_reading,
                                            selectedMeter.rate,
                                            selectedMeter.currency,
                                        )}
                                    </span>
                                </div>
                            </div>
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="reading-reference">
                                {t('Reference')}
                            </Label>
                            <Input
                                id="reading-reference"
                                value={readingForm.data.reference}
                                onChange={(event) =>
                                    readingForm.setData(
                                        'reference',
                                        event.target.value,
                                    )
                                }
                                placeholder={t(
                                    'Optional note or source reference',
                                )}
                            />
                            <InputError
                                message={readingForm.errors.reference}
                            />
                        </div>
                        <InputError message={readingForm.errors.reading} />
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => closeReadingDialog(false)}
                            >
                                {t('Cancel')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={readingForm.processing}
                            >
                                {readingForm.processing
                                    ? t('Saving...')
                                    : t(
                                          editingReading
                                              ? 'Save reading'
                                              : 'Record reading',
                                      )}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={correctionDialogOpen}
                onOpenChange={closeCorrectionDialog}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('Create reading correction')}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                'This creates a signed adjustment against the billed reading; the original remains unchanged.',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitCorrection} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="correction-current-reading">
                                {t('Corrected current reading')}
                            </Label>
                            <Input
                                id="correction-current-reading"
                                type="number"
                                min={0}
                                step="any"
                                required
                                value={correctionForm.data.current_reading}
                                onChange={(event) =>
                                    correctionForm.setData(
                                        'current_reading',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={correctionForm.errors.current_reading}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="correction-reference">
                                {t('Correction reference')}
                            </Label>
                            <Input
                                id="correction-reference"
                                required
                                value={correctionForm.data.reference}
                                onChange={(event) =>
                                    correctionForm.setData(
                                        'reference',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={correctionForm.errors.reference}
                            />
                        </div>
                        <InputError message={correctionForm.errors.reading} />
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => closeCorrectionDialog(false)}
                            >
                                {t('Cancel')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={correctionForm.processing}
                            >
                                {correctionForm.processing
                                    ? t('Saving...')
                                    : t('Save correction')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </UnitLayout>
    );
}
