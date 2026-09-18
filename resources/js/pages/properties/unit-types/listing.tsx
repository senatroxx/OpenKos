import { router, usePage } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { useState } from 'react';
import UnitTypeFormSheet from '@/components/features/properties/unit-type-form-sheet';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import properties from '@/routes/properties';
import publicRoutes from '@/routes/public';
import type { AuthPageProps, UnitTypeListingPageProps } from '@/types';
import { UnitTypeLayout } from './layout';

export default function Listing({ property, unitType, listing }: UnitTypeListingPageProps) {
    const { auth } = usePage<AuthPageProps>().props;
    const [editOpen, setEditOpen] = useState(false);
    const canManage = auth.permissions.includes('properties.update');
    const toggle = () => router.patch(properties.unitTypes.publication.update({ property, unitType }), { is_published: !listing?.is_included }, { preserveScroll: true });
    const ready = listing?.status === 'ready' || listing?.status === 'excluded';

    return <UnitTypeLayout property={property} unitType={unitType} activeTab="listing">
        <div className="max-w-3xl space-y-4">
            <section className="space-y-4 rounded-lg border bg-card p-4 sm:p-5"><div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="font-semibold">Public listing</h2><p className="text-sm text-muted-foreground">Publication and readiness use the existing Unit Type listing rules.</p></div><Badge variant={listing?.is_included ? 'secondary' : 'outline'}>{listing?.is_included ? 'Listed' : 'Not listed'}</Badge></div><div className="rounded-md border p-4"><p className="font-medium">{ready ? 'Ready' : 'Needs attention'}</p>{listing?.reason && <p className="mt-1 text-sm text-amber-700 dark:text-amber-300">{listing.reason}</p>}</div>{canManage && <Button disabled={!ready && !listing?.is_included} onClick={toggle}>{listing?.is_included ? 'Remove from listing' : 'Publish to listing'}</Button>}{listing?.is_included && property.public_slug && unitType.public_slug && <Button asChild variant="outline"><a href={publicRoutes.portal.unitTypes.show.url({ property, unitType })} target="_blank" rel="noreferrer">Preview <ExternalLink className="size-4" /></a></Button>}</section>
            <section className="space-y-3 rounded-lg border bg-card p-4 sm:p-5"><h2 className="font-semibold">Presentation</h2><p className="text-sm text-muted-foreground">Manage Unit Type details, amenities, and photos from the edit flow.</p>{canManage && <Button variant="outline" onClick={() => setEditOpen(true)}>Manage presentation</Button>}</section>
        </div>
        <UnitTypeFormSheet property={property} unitType={unitType} amenities={unitType.amenities ?? []} open={editOpen} onOpenChange={setEditOpen} />
    </UnitTypeLayout>;
}
