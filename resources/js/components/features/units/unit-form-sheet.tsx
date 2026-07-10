import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { store, update } from '@/actions/App/Http/Controllers/UnitController';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
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
import { BILLING_UNITS } from '@/lib/constants/billing';
import type { Property, Unit, UnitRate } from '@/types';

const emptyRate: UnitRate = {
    billing_interval: 1,
    billing_unit: 'month',
    amount: '',
};

export default function UnitFormSheet({
    unit,
    property,
    open,
    onOpenChange,
}: {
    unit?: Unit | null;
    property: Property;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const isEdit = Boolean(unit);

    const { data, setData, processing, errors, submit } = useForm({
        name: unit?.name ?? '',
        floor: unit?.floor ?? '',
        capacity: unit?.capacity ?? 1,
        size_sqm: unit?.size_sqm ?? '',
        description: unit?.description ?? '',
        notes: unit?.notes ?? '',
        status: unit?.status ?? 'available',
        rates: unit?.active_rates?.length ? unit.active_rates : [emptyRate],
    });

    function updateRate(
        index: number,
        field: keyof UnitRate,
        value: string | number,
    ) {
        setData(
            'rates',
            data.rates.map((rate, i) =>
                i === index ? { ...rate, [field]: value } : rate,
            ),
        );
    }

    function addRate() {
        setData('rates', [...data.rates, { ...emptyRate }]);
    }

    function removeRate(index: number) {
        setData(
            'rates',
            data.rates.filter((_, i) => i !== index),
        );
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        submit(isEdit
            ? update({ property: property.slug, unit: unit!.slug })
            : store({ property: property.slug }), {
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{isEdit ? 'Edit Unit' : 'New Unit'}</SheetTitle>
                    <SheetDescription>
                        {isEdit
                            ? 'Update unit details'
                            : `Add a unit to ${property.name}`}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    <form onSubmit={handleSubmit}>
                        <div className="space-y-6 pt-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    required
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="e.g. Unit 101"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="floor">Floor</Label>
                                    <Input
                                        id="floor"
                                        value={data.floor}
                                        onChange={(e) =>
                                            setData('floor', e.target.value)
                                        }
                                        placeholder="e.g. 1"
                                    />
                                    <InputError message={errors.floor} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="capacity">Capacity</Label>
                                    <Input
                                        id="capacity"
                                        type="number"
                                        min={1}
                                        value={data.capacity}
                                        onChange={(e) =>
                                            setData(
                                                'capacity',
                                                Number.parseInt(e.target.value) ||
                                                    1,
                                            )
                                        }
                                    />
                                    <InputError message={errors.capacity} />
                                </div>
                            </div>

                            {/* Pricing Rates */}
                            <section>
                                <div className="mb-3 flex items-center justify-between">
                                    <h3 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Pricing Rates
                                    </h3>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={addRate}
                                    >
                                        <Plus className="mr-1 size-3" />
                                        Add Rate
                                    </Button>
                                </div>

                                <div className="space-y-3">
                                    {data.rates.map((rate, index) => (
                                        <div
                                            key={index}
                                            className="flex items-end gap-2 rounded-lg border p-3"
                                        >
                                            <div className="grid flex-1 gap-1">
                                                <Label className="text-xs">
                                                    Amount (IDR)
                                                </Label>
                                                <input
                                                    type="hidden"
                                                    value={rate.id ?? ''}
                                                />
                                                <input
                                                    type="number"
                                                    min={0}
                                                    step="0.01"
                                                    required
                                                    value={rate.amount}
                                                    onChange={(e) =>
                                                        updateRate(
                                                            index,
                                                            'amount',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="e.g. 1000000"
                                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                                />
                                            </div>
                                            <div className="grid w-20 gap-1">
                                                <Label className="text-xs">
                                                    Every
                                                </Label>
                                                <input
                                                    type="number"
                                                    min={1}
                                                    value={rate.billing_interval}
                                                    onChange={(e) =>
                                                        updateRate(
                                                            index,
                                                            'billing_interval',
                                                            Number.parseInt(
                                                                e.target.value,
                                                            ) || 1,
                                                        )
                                                    }
                                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                                />
                                            </div>
                                            <div className="grid w-28 gap-1">
                                                <Label className="text-xs">
                                                    Unit
                                                </Label>
                                                <Select
                                                    value={rate.billing_unit}
                                                    onValueChange={(val) =>
                                                        updateRate(
                                                            index,
                                                            'billing_unit',
                                                            val,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {BILLING_UNITS.map(
                                                            (unit) => (
                                                                <SelectItem
                                                                    key={unit}
                                                                    value={unit}
                                                                >
                                                                    {unit}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            {data.rates.length > 1 && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-9 shrink-0 text-destructive"
                                                    onClick={() =>
                                                        removeRate(index)
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                                <InputError message={errors.rates} />
                            </section>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="size_sqm">Size (m²)</Label>
                                    <Input
                                        id="size_sqm"
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={data.size_sqm}
                                        onChange={(e) =>
                                            setData('size_sqm', e.target.value)
                                        }
                                        placeholder="e.g. 20"
                                    />
                                    <InputError message={errors.size_sqm} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="status">Status</Label>
                                    <Select
                                        value={data.status}
                                        onValueChange={(val) =>
                                            setData('status', val)
                                        }
                                    >
                                        <SelectTrigger
                                            id="status"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {[
                                                {
                                                    value: 'available',
                                                    label: 'Available',
                                                },
                                                {
                                                    value: 'occupied',
                                                    label: 'Occupied',
                                                },
                                                {
                                                    value: 'maintenance',
                                                    label: 'Maintenance',
                                                },
                                                {
                                                    value: 'unavailable',
                                                    label: 'Unavailable',
                                                },
                                            ].map((opt) => (
                                                <SelectItem
                                                    key={opt.value}
                                                    value={opt.value}
                                                >
                                                    {opt.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.status} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                    placeholder="Unit description"
                                    className="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="notes">Notes</Label>
                                <textarea
                                    id="notes"
                                    value={data.notes}
                                    onChange={(e) =>
                                        setData('notes', e.target.value)
                                    }
                                    placeholder="Additional notes"
                                    className="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                />
                                <InputError message={errors.notes} />
                            </div>

                            <div className="flex items-center justify-end gap-4 pt-2">
                                <Button
                                    variant="outline"
                                    type="button"
                                    onClick={() => onOpenChange(false)}
                                    disabled={processing}
                                >
                                    Cancel
                                </Button>
                                <Button disabled={processing}>
                                    {isEdit ? 'Save' : 'Create'}
                                </Button>
                            </div>
                        </div>
                    </form>
                </div>
            </SheetContent>
        </Sheet>
    );
}
