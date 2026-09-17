import { router, usePage } from '@inertiajs/react';
import { EllipsisVertical, Pencil, RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { PropertyFormSheet, PropertyOverview } from '@/components/features';
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
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { t } from '@/lib/i18n';
import properties from '@/routes/properties';
import type { Auth, Property } from '@/types';
import { PropertyLayout } from './layout';

export default function Overview({ property }: { property: Property }) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const canUpdate = auth.permissions.includes('properties.update');
    const canDelete = auth.permissions.includes('properties.delete');
    const [editOpen, setEditOpen] = useState(false);
    const [archiveConfirm, setArchiveConfirm] = useState(false);

    function archive(): void {
        router.delete(properties.destroy.url(property), {
            onSuccess: () => setArchiveConfirm(false),
        });
    }

    function restore(): void {
        router.post(properties.restore.url(property));
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
                        aria-label={t('Property actions')}
                    >
                        <EllipsisVertical className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    {property.is_active && canDelete ? (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => setArchiveConfirm(true)}
                        >
                            <Trash2 className="size-4" />
                            {t('Archive')}
                        </DropdownMenuItem>
                    ) : (
                        canUpdate && (
                            <DropdownMenuItem onSelect={restore}>
                                <RotateCcw className="size-4" />
                                {t('Restore')}
                            </DropdownMenuItem>
                        )
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        </>
    );

    return (
        <PropertyLayout
            property={property}
            activeTab="overview"
            actions={actions}
        >
            <PropertyOverview property={property} />
            <PropertyFormSheet
                property={property}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <Dialog open={archiveConfirm} onOpenChange={setArchiveConfirm}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Archive property')}</DialogTitle>
                        <DialogDescription>
                            {t('Are you sure you want to archive')}{' '}
                            <span className="font-medium">{property.name}</span>
                            ?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setArchiveConfirm(false)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button variant="destructive" onClick={archive}>
                            {t('Archive')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </PropertyLayout>
    );
}
