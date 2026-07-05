import { Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import properties from '@/routes/properties';

import type { Property } from '@/types';

export default function PropertyDetailSheet({
    property,
    open,
    onOpenChange,
    onEdit,
}: {
    property?: Property | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onEdit: () => void;
}) {
    function archive() {
        if (!property) {
            return;
        }

        if (confirm('Are you sure you want to archive this property?')) {
            router.delete(properties.destroy.url(property), {
                onSuccess: () => onOpenChange(false),
            });
        }
    }

    const city =
        property?.city && typeof property.city !== 'string'
            ? property.city
            : null;
    const locationLabel = [
        city?.name,
        property?.region?.name,
        property?.postal_code,
    ]
        .filter(Boolean)
        .join(', ');

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                className="sm:max-w-lg"
                expandTo={property ? properties.show.url(property) : undefined}
            >
                <SheetHeader>
                    <SheetTitle>{property?.name}</SheetTitle>
                    <SheetDescription>
                        {city?.name ?? 'Property details'}
                    </SheetDescription>
                </SheetHeader>

                {property && (
                    <div className="flex flex-1 flex-col gap-6 overflow-y-auto px-4 pt-4 pb-6">
                        <div className="space-y-5">
                            <div className="flex items-center gap-2">
                                <span>Status:</span>
                                {property.is_active ? (
                                    <Badge
                                        variant="default"
                                        className="bg-green-600"
                                    >
                                        Active
                                    </Badge>
                                ) : (
                                    <Badge variant="secondary">Archived</Badge>
                                )}
                                {property.type && (
                                    <Badge variant="outline">
                                        {property.type_label ?? property.type}
                                    </Badge>
                                )}
                            </div>

                            {property.address && (
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        Address
                                    </p>
                                    <p className="mt-1 text-sm">
                                        {property.address}
                                    </p>
                                    {locationLabel && (
                                        <p className="text-sm text-muted-foreground">
                                            {locationLabel}
                                        </p>
                                    )}
                                </div>
                            )}

                            {!property.address && city && (
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        City
                                    </p>
                                    <p className="mt-1 text-sm">{city.name}</p>
                                </div>
                            )}

                            {property.phone && (
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        Phone
                                    </p>
                                    <p className="mt-1 text-sm">
                                        {property.phone}
                                    </p>
                                </div>
                            )}

                            <div>
                                <p className="text-xs font-medium text-muted-foreground uppercase">
                                    Statistics
                                </p>
                                <div className="mt-1 grid grid-cols-3 gap-4">
                                    <div>
                                        <p className="text-2xl font-semibold tabular-nums">
                                            {property.units_count}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Total Rooms
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-2xl font-semibold tabular-nums">
                                            {property.occupied_units_count}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Occupied
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-2xl font-semibold tabular-nums">
                                            {property.tenants_count}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Tenants
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <Link
                                href={properties.units.index.url(property)}
                                className="flex w-full items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium shadow-sm hover:bg-accent hover:text-accent-foreground"
                                onClick={() => onOpenChange(false)}
                            >
                                Manage Rooms ({property.units_count})
                            </Link>
                        </div>

                        <div className="flex-1"></div>

                        <div className="flex items-center justify-end gap-4">
                            <Button
                                variant="outline"
                                onClick={() => onOpenChange(false)}
                            >
                                Close
                            </Button>
                            <Button variant="destructive" onClick={archive}>
                                Archive
                            </Button>
                            <Button
                                onClick={() => {
                                    onOpenChange(false);
                                    onEdit();
                                }}
                            >
                                Edit
                            </Button>
                        </div>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
