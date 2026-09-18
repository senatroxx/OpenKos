import { Head, useForm, usePage } from '@inertiajs/react';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { BILLING_UNITS } from '@/lib/constants';
import properties from '@/routes/properties';
import type {
    UnitTypeRatesFormData,
    UnitTypeRatesPageProps,
    UnitTypeRate,
} from '@/types';
import { PropertyLayout } from '../layout';

export default function UnitTypeRates({
    property,
    unitType,
}: UnitTypeRatesPageProps) {
    const { setting } = usePage().props;
    const form = useForm<UnitTypeRatesFormData>({
        rates: unitType.rates,
        updated_at: unitType.updated_at ?? null,
    });

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.submit(properties.unitTypes.rates.update({ property, unitType }), {
            preserveScroll: true,
        });
    }

    function addRate() {
        form.setData('rates', [
            ...form.data.rates,
            {
                amount: '',
                currency: setting.currency,
                billing_interval: 1,
                billing_unit: 'month' as const,
                is_active: true,
            } as UnitTypeRate,
        ]);
    }

    return (
        <PropertyLayout property={property} activeTab="unit-types">
            <Head title={`${unitType.name} pricing`} />
            <form onSubmit={submit} className="space-y-6 p-6">
                <div>
                    <h1 className="text-xl font-semibold">{unitType.name} pricing</h1>
                    <p className="text-sm text-muted-foreground">
                        These rates are inherited by units unless an exact unit-rate identity overrides them.
                    </p>
                </div>
                <div className="space-y-4">
                    {form.data.rates.map((rate, index) => (
                        <div key={rate.id ?? `new-${index}`} className="grid gap-3 rounded-lg border p-4 md:grid-cols-5">
                            <div>
                                <Label>Amount</Label>
                                <Input value={rate.amount} onChange={(event) => form.setData('rates', form.data.rates.map((item, i) => i === index ? { ...item, amount: event.target.value } : item))} />
                            </div>
                            <div>
                                <Label>Currency</Label>
                                <Input value={rate.currency} disabled={rate.id != null} onChange={(event) => form.setData('rates', form.data.rates.map((item, i) => i === index ? { ...item, currency: event.target.value } : item))} />
                            </div>
                            <div>
                                <Label>Interval</Label>
                                <Input type="number" min={1} value={rate.billing_interval} disabled={rate.id != null} onChange={(event) => form.setData('rates', form.data.rates.map((item, i) => i === index ? { ...item, billing_interval: Number(event.target.value) } : item))} />
                            </div>
                            <div>
                                <Label>Unit</Label>
                                <select className="h-9 w-full rounded-md border bg-background px-3 text-sm" value={rate.billing_unit} disabled={rate.id != null} onChange={(event) => form.setData('rates', form.data.rates.map((item, i) => i === index ? { ...item, billing_unit: event.target.value as UnitTypeRate['billing_unit'] } : item))}>
                                    {BILLING_UNITS.map((unit) => <option key={unit} value={unit}>{unit}</option>)}
                                </select>
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={rate.is_active !== false} onChange={(event) => form.setData('rates', form.data.rates.map((item, i) => i === index ? { ...item, is_active: event.target.checked } : item))} />
                                Active
                            </label>
                        </div>
                    ))}
                </div>
                {Object.values(form.errors).map((error) => <InputError key={error} message={error} />)}
                <div className="flex gap-2">
                    <Button type="button" variant="outline" onClick={addRate}>Add rate</Button>
                    <Button type="submit" disabled={form.processing}>Save pricing</Button>
                </div>
            </form>
        </PropertyLayout>
    );
}
