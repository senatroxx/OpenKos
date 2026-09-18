import { useForm } from '@inertiajs/react';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { t } from '@/lib/i18n';
import properties from '@/routes/properties';
import type {
    BulkAssignUnitTypeDialogProps,
    BulkAssignUnitTypeFormData,
} from '@/types';

export default function BulkAssignUnitTypeDialog({
    property,
    units,
    unitTypes,
    open,
    onOpenChange,
}: BulkAssignUnitTypeDialogProps) {
    const { data, setData, submit, reset, processing, errors } =
        useForm<BulkAssignUnitTypeFormData>({
            unit_ids: units.map((unit) => unit.id),
            unit_type_id: '',
        });

    function handleOpenChange(next: boolean) {
        onOpenChange(next);

        if (!next) {
            reset();
        }
    }

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();
        submit(properties.units.bulkAssignUnitType(property), {
            onSuccess: () => handleOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('Assign Unit Type')}</DialogTitle>
                    <DialogDescription>
                        {t('Assign one Unit Type to :count selected Units.', {
                            count: units.length,
                        })}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="space-y-6">
                        <div className="rounded-md border p-3">
                            <p className="mb-2 text-sm font-medium">
                                {t('Selected Units')}
                            </p>
                            <ul className="space-y-1 text-sm text-muted-foreground">
                                {units.map((unit) => (
                                    <li key={unit.id}>{unit.name}</li>
                                ))}
                            </ul>
                        </div>

                        <div className="grid gap-2">
                            <label
                                htmlFor="bulk-unit-type"
                                className="text-sm font-medium"
                            >
                                {t('Target Unit Type')}
                            </label>
                            <Select
                                value={data.unit_type_id}
                                onValueChange={(value) =>
                                    setData('unit_type_id', value)
                                }
                            >
                                <SelectTrigger id="bulk-unit-type">
                                    <SelectValue
                                        placeholder={t('Select a Unit Type')}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {unitTypes
                                        .filter((unitType) => unitType.is_active)
                                        .map((unitType) => (
                                            <SelectItem
                                                key={unitType.id}
                                                value={String(unitType.id)}
                                            >
                                                {unitType.name}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.unit_type_id} />
                        </div>

                        <p className="text-sm text-muted-foreground">
                            {t('Confirming will update all selected Units.')}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => handleOpenChange(false)}
                            disabled={processing}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button disabled={processing || !data.unit_type_id}>
                            {t('Confirm assignment')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
