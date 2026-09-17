import { EllipsisVertical, Trash2 } from 'lucide-react';
import { StatusBadge } from '@/components/shared/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { AmenityIcon } from '@/lib/amenity-icons';
import { formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import type { ListingUnitType, UnitType } from '@/types';

export default function UnitTypeDetailSheet({
    unitType,
    option,
    open,
    canManage,
    publishing,
    onOpenChange,
    onEdit,
    onTogglePublication,
    onDelete,
}: {
    unitType?: UnitType | null;
    option?: ListingUnitType | null;
    open: boolean;
    canManage: boolean;
    publishing: boolean;
    onOpenChange: (open: boolean) => void;
    onEdit: () => void;
    onTogglePublication: () => void;
    onDelete: () => void;
}) {
    const gallery = unitType?.gallery ?? [];
    const amenities = unitType?.amenities ?? [];
    const canPublish = Boolean(
        option && (option.is_included || option.is_viable_if_included),
    );

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-xl">
                <SheetHeader>
                    <SheetTitle>{unitType?.name}</SheetTitle>
                    <SheetDescription>
                        {t('Unit Type details')}
                    </SheetDescription>
                </SheetHeader>

                {unitType && option && (
                    <div className="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto px-4 pb-6">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex flex-wrap items-center gap-2">
                                <StatusBadge
                                    domain="property"
                                    value={
                                        unitType.is_active
                                            ? 'active'
                                            : 'inactive'
                                    }
                                />
                                <Badge
                                    variant={
                                        option.is_included
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                >
                                    {t(
                                        option.is_included
                                            ? 'Listed'
                                            : 'Not listed',
                                    )}
                                </Badge>
                            </div>
                            {canManage && (
                                <div className="flex items-center gap-2">
                                    <Button onClick={onEdit}>
                                        {t('Edit')}
                                    </Button>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                variant="outline"
                                                size="icon"
                                            >
                                                <span className="sr-only">
                                                    {t('Actions')}
                                                </span>
                                                <EllipsisVertical
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                className="text-destructive"
                                                onSelect={onDelete}
                                            >
                                                <Trash2 className="size-4" />
                                                {t('Delete')}
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            )}
                        </div>

                        <section className="space-y-3">
                            <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                {t('Overview')}
                            </h3>
                            <div className="space-y-3 rounded-lg border p-4 text-sm">
                                <p className="whitespace-pre-wrap">
                                    {unitType.description ||
                                        t('No description yet.')}
                                </p>
                                <div className="grid grid-cols-2 gap-x-4 gap-y-2">
                                    <DetailValue
                                        label="Bedrooms"
                                        value={unitType.bedrooms ?? '—'}
                                    />
                                    <DetailValue
                                        label="Bathrooms"
                                        value={unitType.bathrooms ?? '—'}
                                    />
                                    <DetailValue
                                        label="Size"
                                        value={
                                            unitType.size_sqm
                                                ? `${unitType.size_sqm} m²`
                                                : '—'
                                        }
                                    />
                                    <DetailValue
                                        label="Furnishing"
                                        value={unitType.furnishing || '—'}
                                    />
                                </div>
                            </div>
                        </section>

                        <section className="space-y-3">
                            <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                {t('Inventory')}
                            </h3>
                            <div className="grid grid-cols-2 gap-4 rounded-lg border p-4">
                                <DetailValue
                                    label="Total Units"
                                    value={option.physical_units}
                                />
                                <DetailValue
                                    label="Available Units"
                                    value={option.available_units}
                                />
                            </div>
                        </section>

                        <section className="space-y-3">
                            <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                {t('Pricing')}
                            </h3>
                            <div className="rounded-lg border p-4 text-sm">
                                {option.starting_price ? (
                                    <p>
                                        {formatPrice(
                                            option.starting_price.amount,
                                            option.starting_price.currency,
                                        )}{' '}
                                        {option.starting_price.billing_label}
                                    </p>
                                ) : (
                                    <p className="text-muted-foreground">
                                        {t('Pricing missing')}
                                    </p>
                                )}
                            </div>
                        </section>

                        <section className="space-y-3">
                            <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                {t('Listing')}
                            </h3>
                            <div className="space-y-3 rounded-lg border p-4 text-sm">
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-muted-foreground">
                                        {t('Listing state')}
                                    </span>
                                    <Badge
                                        variant={
                                            option.is_included
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {t(
                                            option.is_included
                                                ? 'Listed'
                                                : 'Not listed',
                                        )}
                                    </Badge>
                                </div>
                                <div>
                                    <p className="font-medium">
                                        {t(
                                            option.status === 'ready' ||
                                                option.status === 'excluded'
                                                ? 'Ready'
                                                : 'Needs attention',
                                        )}
                                    </p>
                                    {option.reason && (
                                        <p className="mt-1 text-amber-700 dark:text-amber-300">
                                            {t(option.reason)}
                                        </p>
                                    )}
                                </div>
                                {canManage && canPublish && (
                                    <Button
                                        variant={
                                            option.is_included
                                                ? 'outline'
                                                : 'default'
                                        }
                                        disabled={publishing}
                                        onClick={onTogglePublication}
                                    >
                                        {t(
                                            option.is_included
                                                ? 'Remove from listing'
                                                : 'Publish to listing',
                                        )}
                                    </Button>
                                )}
                            </div>
                        </section>

                        <section className="space-y-3">
                            <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                {t('Amenities')}
                            </h3>
                            {amenities.length > 0 ? (
                                <div className="flex flex-wrap gap-2">
                                    {amenities.map((amenity) => (
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
                                                aria-hidden="true"
                                            />
                                            {amenity.name}
                                        </Badge>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    {t('No amenities assigned.')}
                                </p>
                            )}
                        </section>

                        <section className="space-y-3">
                            <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                {t('Photos')}
                            </h3>
                            {gallery.length > 0 ? (
                                <div className="grid grid-cols-3 gap-2">
                                    {gallery.slice(0, 3).map((photo) => (
                                        <img
                                            key={photo.id}
                                            src={photo.url}
                                            alt={photo.alt ?? unitType.name}
                                            className="aspect-video w-full rounded-md object-cover"
                                        />
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    {t('No photos yet.')}
                                </p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                {gallery.length}{' '}
                                {t(gallery.length === 1 ? 'photo' : 'photos')}
                            </p>
                        </section>

                        <div className="flex-1" />
                        <Button
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            {t('Close')}
                        </Button>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}

function DetailValue({
    label,
    value,
}: {
    label: string;
    value: string | number;
}) {
    return (
        <div className="flex flex-col gap-1">
            <span className="text-xs text-muted-foreground">{t(label)}</span>
            <span>{value}</span>
        </div>
    );
}
