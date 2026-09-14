import { Head, router, useForm } from '@inertiajs/react';
import { Archive, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { InputError } from '@/components/shared';
import AmenityIconPicker from '@/components/shared/amenity-icon-picker';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { Switch } from '@/components/ui/switch';
import { AmenityIcon, isAmenityIcon } from '@/lib/amenity-icons';
import { t } from '@/lib/i18n';
import amenityRoutes from '@/routes/settings/amenities';
import type { Amenity } from '@/types';

const scopeLabels = {
    property: 'Property',
    unit_type: 'Unit Type',
    both: 'Both',
} as const;

function AmenityFormSheet({
    amenity,
    open,
    onOpenChange,
}: {
    amenity: Amenity | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const isEdit = Boolean(amenity);
    const { data, setData, submit, reset, processing, errors } = useForm({
        name: amenity?.name ?? '',
        icon: isAmenityIcon(amenity?.icon) ? amenity.icon : null,
        scope: amenity?.scope ?? 'both',
        is_active: amenity?.is_active ?? true,
    });

    function close(next: boolean) {
        onOpenChange(next);

        if (!next) {
            reset();
        }
    }

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();
        submit(
            isEdit ? amenityRoutes.update(amenity!) : amenityRoutes.store(),
            { onSuccess: () => close(false) },
        );
    }

    return (
        <Sheet open={open} onOpenChange={close}>
            <SheetContent className="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>
                        {t(isEdit ? 'Edit amenity' : 'New amenity')}
                    </SheetTitle>
                    <SheetDescription>
                        {t(
                            'Amenities are shared catalog records assigned separately to Properties and Unit Types.',
                        )}
                    </SheetDescription>
                </SheetHeader>
                <form
                    onSubmit={handleSubmit}
                    className="flex flex-1 flex-col justify-between gap-6 px-4 pt-4 pb-6"
                >
                    <div className="grid gap-4">
                        <div className="grid grid-cols-[auto_minmax(0,1fr)] items-end gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="amenity-icon">
                                    {t('Icon')}
                                </Label>
                                <AmenityIconPicker
                                    value={data.icon}
                                    onChange={(value) => setData('icon', value)}
                                />
                                <InputError message={errors.icon} />
                            </div>

                            <div className="grid min-w-0 gap-2">
                                <Label htmlFor="amenity-name">
                                    {t('Name')}
                                </Label>
                                <Input
                                    id="amenity-name"
                                    value={data.name}
                                    onChange={(event) =>
                                        setData('name', event.target.value)
                                    }
                                    placeholder={t('e.g. Wi-Fi')}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="amenity-scope">{t('Scope')}</Label>
                            <Select
                                value={data.scope}
                                onValueChange={(value) =>
                                    setData('scope', value as Amenity['scope'])
                                }
                            >
                                <SelectTrigger id="amenity-scope">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(scopeLabels).map(
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
                            <InputError message={errors.scope} />
                        </div>

                        <div className="flex items-center justify-between rounded-md border p-3">
                            <Label htmlFor="amenity-active">
                                {t('Active')}
                            </Label>
                            <Switch
                                id="amenity-active"
                                checked={data.is_active}
                                onCheckedChange={(value) =>
                                    setData('is_active', value)
                                }
                            />
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => close(false)}
                            disabled={processing}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button disabled={processing}>
                            {t(isEdit ? 'Save' : 'Add')}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}

export default function Amenities({ amenities }: { amenities: Amenity[] }) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Amenity | null>(null);
    const [pendingDelete, setPendingDelete] = useState<Amenity | null>(null);

    function openCreate() {
        setEditing(null);
        setFormOpen(true);
    }

    function openEdit(amenity: Amenity) {
        setEditing(amenity);
        setFormOpen(true);
    }

    function toggleActive(amenity: Amenity, isActive: boolean) {
        router.patch(amenityRoutes.update.url(amenity), {
            name: amenity.name,
            icon: isAmenityIcon(amenity.icon) ? amenity.icon : null,
            scope: amenity.scope,
            is_active: isActive,
        });
    }

    function confirmDelete() {
        if (!pendingDelete) {
            return;
        }

        router.delete(amenityRoutes.destroy.url(pendingDelete), {
            onFinish: () => setPendingDelete(null),
        });
    }

    return (
        <>
            <Head title={t('Amenities')} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-medium">
                            {t('Amenities')}
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t(
                                'Manage shared amenities that can be assigned to Properties and Unit Types.',
                            )}
                        </p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="size-4" />
                        {t('Add amenity')}
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Amenity catalog')}</CardTitle>
                        <CardDescription>
                            {t(
                                'Referenced amenities are archived instead of deleted so existing assignments remain intact.',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                        <th className="px-4 py-3 font-medium">
                                            {t('Name')}
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            {t('Scope')}
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            {t('In use')}
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            {t('Active')}
                                        </th>
                                        <th className="px-4 py-3 font-medium" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {amenities.map((amenity) => {
                                        const inUse =
                                            (amenity.properties_count ?? 0) >
                                                0 ||
                                            (amenity.unit_types_count ?? 0) > 0;

                                        return (
                                            <tr
                                                key={amenity.slug}
                                                className="border-b last:border-0"
                                            >
                                                <td className="px-4 py-3 font-medium">
                                                    <span className="flex items-center gap-2">
                                                        <AmenityIcon
                                                            icon={amenity.icon}
                                                            className="size-4 text-muted-foreground"
                                                            aria-hidden="true"
                                                        />
                                                        {amenity.name}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {t(
                                                        scopeLabels[
                                                            amenity.scope
                                                        ],
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground tabular-nums">
                                                    {(amenity.properties_count ??
                                                        0) +
                                                        (amenity.unit_types_count ??
                                                            0)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Switch
                                                        checked={
                                                            amenity.is_active
                                                        }
                                                        onCheckedChange={(
                                                            value,
                                                        ) =>
                                                            toggleActive(
                                                                amenity,
                                                                value,
                                                            )
                                                        }
                                                    />
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8"
                                                            onClick={() =>
                                                                openEdit(
                                                                    amenity,
                                                                )
                                                            }
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8"
                                                            onClick={() =>
                                                                setPendingDelete(
                                                                    amenity,
                                                                )
                                                            }
                                                        >
                                                            {inUse ? (
                                                                <Archive className="size-4" />
                                                            ) : (
                                                                <Trash2 className="size-4" />
                                                            )}
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <AmenityFormSheet
                key={editing?.id ?? 'new'}
                amenity={editing}
                open={formOpen}
                onOpenChange={setFormOpen}
            />

            <Dialog
                open={pendingDelete !== null}
                onOpenChange={(next) => !next && setPendingDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t(
                                (pendingDelete?.properties_count ?? 0) > 0 ||
                                    (pendingDelete?.unit_types_count ?? 0) > 0
                                    ? 'Archive amenity'
                                    : 'Delete amenity',
                            )}
                        </DialogTitle>
                        <DialogDescription>
                            {(pendingDelete?.properties_count ?? 0) > 0 ||
                            (pendingDelete?.unit_types_count ?? 0) > 0
                                ? t(
                                      'This amenity is assigned and will be deactivated. Existing assignments will remain preserved.',
                                  )
                                : t('Delete this unused amenity?')}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setPendingDelete(null)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            {t(
                                (pendingDelete?.properties_count ?? 0) > 0 ||
                                    (pendingDelete?.unit_types_count ?? 0) > 0
                                    ? 'Archive'
                                    : 'Delete',
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Amenities.layout = {
    breadcrumbs: [
        {
            title: 'Amenities',
            href: amenityRoutes.index(),
        },
    ],
};
