import { Link, router, usePage } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import UnitTypeFormSheet from '@/components/features/properties/unit-type-form-sheet';
import { Badge } from '@/components/ui/badge';
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
import { AmenityIcon } from '@/lib/amenity-icons';
import { formatPrice } from '@/lib/formatters';
import properties from '@/routes/properties';
import type {
    AuthPageProps,
    UnitTypeDetailValueProps,
    UnitTypeOverviewPageProps,
} from '@/types';
import { UnitTypeLayout } from './layout';

export default function Show({
    property,
    unitType,
    listing,
}: UnitTypeOverviewPageProps) {
    const { auth } = usePage<AuthPageProps>().props;
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const canManage = auth.permissions.includes('properties.update');
    const activeRates = unitType.active_rates ?? [];
    const startingPrice =
        listing?.starting_price ??
        (activeRates[0]
            ? {
                  amount: activeRates[0].amount,
                  currency: activeRates[0].currency ?? '',
                  billing_label: `/${activeRates[0].billing_unit}`,
              }
            : null);

    return (
        <UnitTypeLayout
            property={property}
            unitType={unitType}
            activeTab="overview"
            actions={
                canManage ? (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label="Unit Type actions"
                            >
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                onSelect={() => setEditOpen(true)}
                            >
                                Edit
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onSelect={() =>
                                    router.visit(
                                        properties.unitTypes.listing({
                                            property,
                                            unitType,
                                        }),
                                    )
                                }
                            >
                                Manage listing
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onSelect={() =>
                                    router.patch(
                                        properties.unitTypes.status.update({
                                            property,
                                            unitType,
                                        }),
                                        { is_active: !unitType.is_active },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {unitType.is_active ? 'Deactivate' : 'Activate'}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => setDeleteOpen(true)}
                            >
                                Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : undefined
            }
        >
            <div className="grid gap-4 lg:grid-cols-2">
                <section className="space-y-3 rounded-lg border bg-card p-4 sm:p-5 lg:col-span-2">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="font-semibold">Details</h2>
                        {canManage && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setEditOpen(true)}
                            >
                                Edit
                            </Button>
                        )}
                    </div>
                    <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                        {unitType.description || 'No description yet.'}
                    </p>
                    <div className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                        <Detail
                            label="Bedrooms"
                            value={unitType.bedrooms ?? '—'}
                        />
                        <Detail
                            label="Bathrooms"
                            value={unitType.bathrooms ?? '—'}
                        />
                        <Detail
                            label="Size"
                            value={
                                unitType.size_sqm
                                    ? `${unitType.size_sqm} m²`
                                    : '—'
                            }
                        />
                        <Detail
                            label="Furnishing"
                            value={unitType.furnishing || '—'}
                        />
                    </div>
                </section>

                <section className="space-y-3 rounded-lg border bg-card p-4 sm:p-5">
                    <div className="flex items-center justify-between">
                        <h2 className="font-semibold">Inventory</h2>
                        <Link
                            className="text-sm text-primary hover:underline"
                            href={properties.unitTypes.units.url({
                                property,
                                unitType,
                            })}
                        >
                            View units
                        </Link>
                    </div>
                    <div className="grid grid-cols-2 gap-4 text-sm">
                        <Detail
                            label="Total units"
                            value={unitType.units_count ?? 0}
                        />
                        <Detail
                            label="Available"
                            value={unitType.available_units_count ?? 0}
                        />
                    </div>
                </section>

                <section className="space-y-3 rounded-lg border bg-card p-4 sm:p-5">
                    <h2 className="font-semibold">Amenities</h2>
                    <div className="flex flex-wrap gap-2">
                        {(unitType.amenities ?? []).length ? (
                            unitType.amenities?.map((amenity) => (
                                <Badge
                                    key={amenity.id}
                                    variant={
                                        amenity.is_active
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                >
                                    <AmenityIcon
                                        icon={amenity.icon}
                                        className="size-3.5"
                                    />
                                    {amenity.name}
                                </Badge>
                            ))
                        ) : (
                            <span className="text-sm text-muted-foreground">
                                No amenities assigned.
                            </span>
                        )}
                    </div>
                </section>

                <section className="space-y-3 rounded-lg border bg-card p-4 sm:p-5">
                    <div className="flex items-center justify-between">
                        <h2 className="font-semibold">Photos</h2>
                        {canManage && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setEditOpen(true)}
                            >
                                Manage photos
                            </Button>
                        )}
                    </div>
                    <div className="grid grid-cols-3 gap-2">
                        {(unitType.gallery ?? []).slice(0, 3).map((photo) => (
                            <img
                                key={photo.id}
                                src={photo.url}
                                alt={photo.alt ?? ''}
                                className="aspect-[4/3] w-full rounded-md object-cover"
                            />
                        ))}
                        {!(unitType.gallery ?? []).length && (
                            <span className="text-sm text-muted-foreground">
                                No photos yet.
                            </span>
                        )}
                    </div>
                </section>

                <section className="space-y-3 rounded-lg border bg-card p-4 sm:p-5">
                    <div className="flex items-center justify-between">
                        <h2 className="font-semibold">Pricing</h2>
                        <Link
                            className="text-sm text-primary hover:underline"
                            href={properties.unitTypes.rates.index.url({
                                property,
                                unitType,
                            })}
                        >
                            Manage pricing
                        </Link>
                    </div>
                    <div className="flex items-baseline justify-between gap-3">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Starting price
                            </p>
                            <p className="text-lg font-semibold">
                                {startingPrice
                                    ? `${formatPrice(startingPrice.amount, startingPrice.currency)} ${startingPrice.billing_label}`
                                    : 'Pricing missing'}
                            </p>
                        </div>
                        <div className="text-right text-sm text-muted-foreground">
                            {activeRates.length} active options
                        </div>
                    </div>
                </section>

                <section className="space-y-3 rounded-lg border bg-card p-4 sm:p-5">
                    <div className="flex items-center justify-between">
                        <h2 className="font-semibold">Listing</h2>
                        <Link
                            className="text-sm text-primary hover:underline"
                            href={properties.unitTypes.listing.url({
                                property,
                                unitType,
                            })}
                        >
                            Open listing
                        </Link>
                    </div>
                    <div className="flex items-center justify-between gap-3">
                        <Badge
                            variant={
                                listing?.is_included ? 'secondary' : 'outline'
                            }
                        >
                            {listing?.is_included ? 'Listed' : 'Not listed'}
                        </Badge>
                        <span className="text-sm font-medium">
                            {listing?.status === 'ready' ||
                            listing?.status === 'excluded'
                                ? 'Ready'
                                : 'Needs attention'}
                        </span>
                    </div>
                    {listing?.reason && (
                        <p className="text-sm text-amber-700 dark:text-amber-300">
                            {listing.reason}
                        </p>
                    )}
                </section>
            </div>
            <UnitTypeFormSheet
                property={property}
                unitType={unitType}
                amenities={unitType.amenities ?? []}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Unit Type</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete {unitType.name}?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                router.delete(
                                    properties.unitTypes.destroy({
                                        property,
                                        unitType,
                                    }),
                                    {
                                        onSuccess: () =>
                                            router.visit(
                                                properties.unitTypes.index(
                                                    property,
                                                ),
                                            ),
                                    },
                                )
                            }
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </UnitTypeLayout>
    );
}

function Detail({ label, value }: UnitTypeDetailValueProps) {
    return (
        <div>
            <p className="text-muted-foreground">{label}</p>
            <p className="font-medium">{value}</p>
        </div>
    );
}
