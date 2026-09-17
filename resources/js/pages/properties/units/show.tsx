import { router, usePage } from '@inertiajs/react';
import { DoorOpen, EllipsisVertical, Move, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    AssignTenantSheet,
    MoveUnitSheet,
    UnitFormSheet,
    UnitOverview,
} from '@/components/features';
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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { t } from '@/lib/i18n';
import properties from '@/routes/properties';
import type { Auth, Property, Unit, UnitType } from '@/types';
import { UnitLayout } from './layout';

export default function UnitWorkspace({
    property,
    unit,
    availableUnits,
    unitTypes,
}: {
    property: Property;
    unit: Unit;
    availableUnits: {
        id: number;
        name: string;
        capacity: number;
        occupied_count?: number;
    }[];
    unitTypes: Pick<UnitType, 'id' | 'property_id' | 'name' | 'is_active'>[];
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const canUpdate = auth.permissions.includes('units.update');
    const canDelete = auth.permissions.includes('units.delete');
    const [editOpen, setEditOpen] = useState(false);
    const [assignOpen, setAssignOpen] = useState(false);
    const [moveOpen, setMoveOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const activeLease = unit.leases?.[0] ?? null;
    const occupantCount =
        (unit.active_leases ?? 0) > 0
            ? (unit.leases ?? []).flatMap(
                  (lease) =>
                      lease.tenants ??
                      (lease.primary_tenant ? [lease.primary_tenant] : []),
              ).length
            : 0;
    const hasCapacity = unit.capacity > occupantCount;

    function deleteUnit(): void {
        router.delete(
            properties.units.destroy.url({
                property: property.slug,
                unit: unit.slug,
            }),
            { onSuccess: () => setDeleteOpen(false) },
        );
    }

    const actions = (canUpdate || canDelete) && (
        <>
            {canUpdate && (
                <Button variant="outline" onClick={() => setEditOpen(true)}>
                    <Pencil className="size-4" />
                    {t('Edit')}
                </Button>
            )}
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label={t('Unit actions')}
                    >
                        <EllipsisVertical className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    {canUpdate && hasCapacity && (
                        <DropdownMenuItem onSelect={() => setAssignOpen(true)}>
                            <DoorOpen className="size-4" />
                            {t('Assign Tenant')}
                        </DropdownMenuItem>
                    )}
                    {canUpdate && activeLease && (
                        <DropdownMenuItem onSelect={() => setMoveOpen(true)}>
                            <Move className="size-4" />
                            {t('Move Unit')}
                        </DropdownMenuItem>
                    )}
                    {canDelete && (hasCapacity || activeLease) && (
                        <DropdownMenuSeparator />
                    )}
                    {canDelete && (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => setDeleteOpen(true)}
                        >
                            <Trash2 className="size-4" />
                            {t('Delete')}
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        </>
    );

    return (
        <UnitLayout
            property={property}
            unit={unit}
            activeTab="overview"
            actions={actions}
        >
            <UnitOverview unit={unit} />
            <UnitFormSheet
                unit={unit}
                property={property}
                unitTypes={unitTypes}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <AssignTenantSheet
                unit={unit}
                property={property}
                open={assignOpen}
                onOpenChange={setAssignOpen}
            />
            {activeLease && (
                <MoveUnitSheet
                    property={property}
                    currentUnit={unit}
                    availableUnits={availableUnits}
                    lease={activeLease}
                    open={moveOpen}
                    onOpenChange={setMoveOpen}
                />
            )}
            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Delete unit')}</DialogTitle>
                        <DialogDescription>
                            {t('Are you sure you want to delete')}{' '}
                            <span className="font-medium">{unit.name}</span>?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteOpen(false)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button variant="destructive" onClick={deleteUnit}>
                            {t('Delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </UnitLayout>
    );
}
