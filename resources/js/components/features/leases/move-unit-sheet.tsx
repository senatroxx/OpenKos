import { useForm } from '@inertiajs/react';
import { InputError, SearchableSelect } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import properties from '@/routes/properties';
export default function MoveUnitSheet({
    property,
    currentUnit,
    availableUnits,
    lease,
    open,
    onOpenChange,
}: {
    property: { id: number; slug: string; name: string };
    currentUnit: { id: number; slug: string; name: string; capacity: number };
    availableUnits: {
        id: number;
        name: string;
        capacity: number;
        occupied_count?: number;
    }[];
    lease: { id: number };
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { data, setData, submit, reset, processing, errors } = useForm({
        target_unit_id: null as number | null,
    });

    function handleOpenChange(next: boolean) {
        onOpenChange(next);

        if (!next) {
            reset();
        }
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        submit(
            properties.units.leases.move({
                property: property.slug,
                unit: currentUnit.slug,
                lease: lease.id,
            }),
            { onSuccess: () => handleOpenChange(false) },
        );
    }

    const unitOptions = availableUnits.map((r) => {
        const occupiedCount = r.occupied_count ?? 0;
        const spotsLeft = r.capacity - occupiedCount;
        const suffix =
            occupiedCount > 0
                ? ` (${occupiedCount}/${r.capacity} occupied, ${spotsLeft} spot${spotsLeft === 1 ? '' : 's'} left)`
                : r.capacity > 1
                  ? ` (capacity ${r.capacity})`
                  : '';

        return {
            value: r.id,
            label: `${r.name}${suffix}`,
        };
    });

    return (
        <Sheet key="move-unit" open={open} onOpenChange={handleOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Move Tenant</SheetTitle>
                    <SheetDescription>
                        Move tenant from {currentUnit.name} to another unit
                    </SheetDescription>
                </SheetHeader>

                <form
                    onSubmit={handleSubmit}
                    className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6"
                >
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label>Target Unit</Label>
                            <SearchableSelect
                                options={unitOptions}
                                value={data.target_unit_id}
                                onChange={(val) =>
                                    setData(
                                        'target_unit_id',
                                        val as number | null,
                                    )
                                }
                                placeholder="Select target unit..."
                                searchPlaceholder="Search unit..."
                                emptyText="No available units."
                            />
                            <InputError message={errors.target_unit_id} />
                        </div>

                        <p className="text-sm text-muted-foreground">
                            The current lease will be terminated, and a new
                            lease will be created in the selected unit. The
                            deposit will be transferred.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center justify-end gap-4">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => handleOpenChange(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button disabled={processing}>Move Tenant</Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
