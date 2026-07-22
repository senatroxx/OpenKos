import { Link } from '@inertiajs/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';
import { formatDate } from '@/lib/formatters';
import type { TenantLeaseContext as LeaseContext } from '@/types';

export default function TenantLeaseContext({
    leaseContext,
    hrefForLease,
}: {
    leaseContext: LeaseContext;
    hrefForLease: (leaseId: number) => string;
}) {
    const isMobile = useIsMobile();
    const [open, setOpen] = useState(false);
    const selectedLease = leaseContext.selected;
    const canSwitch = leaseContext.leases.length > 1;

    if (!selectedLease) {
        return null;
    }

    const trigger = (
        <LeaseContextCard lease={selectedLease} canSwitch={canSwitch} />
    );

    if (!canSwitch) {
        return trigger;
    }

    if (isMobile) {
        return (
            <Sheet open={open} onOpenChange={setOpen}>
                <button
                    type="button"
                    className="w-full text-left"
                    onClick={() => setOpen(true)}
                >
                    {trigger}
                </button>
                <SheetContent side="bottom" className="max-h-[80vh]">
                    <SheetHeader>
                        <SheetTitle>Choose a lease</SheetTitle>
                        <SheetDescription>
                            Your billing and records will use this lease.
                        </SheetDescription>
                    </SheetHeader>
                    <LeaseOptions
                        leaseContext={leaseContext}
                        hrefForLease={hrefForLease}
                    />
                </SheetContent>
            </Sheet>
        );
    }

    return (
        <Popover>
            <PopoverTrigger asChild>
                <button type="button" className="w-full text-left">
                    {trigger}
                </button>
            </PopoverTrigger>
            <PopoverContent
                className="w-[min(24rem,var(--radix-popover-trigger-width))] p-2"
                align="start"
            >
                <LeaseOptions
                    leaseContext={leaseContext}
                    hrefForLease={hrefForLease}
                />
            </PopoverContent>
        </Popover>
    );
}

function LeaseContextCard({
    lease,
    canSwitch,
}: {
    lease: NonNullable<LeaseContext['selected']>;
    canSwitch: boolean;
}) {
    return (
        <div className="flex items-center justify-between gap-4 rounded-lg border p-4 transition-colors hover:bg-muted/50">
            <div className="min-w-0">
                <p className="text-sm text-muted-foreground">Current lease</p>
                <p className="mt-1 truncate font-medium">
                    {lease.unit_name ?? 'Unit'}
                    {lease.property_name && ` · ${lease.property_name}`}
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                    {formatDate(lease.start_date)} —{' '}
                    {lease.end_date ? formatDate(lease.end_date) : 'Ongoing'}
                </p>
            </div>
            {canSwitch && (
                <ChevronsUpDown className="size-4 shrink-0 text-muted-foreground" />
            )}
        </div>
    );
}

function LeaseOptions({
    leaseContext,
    hrefForLease,
}: {
    leaseContext: LeaseContext;
    hrefForLease: (leaseId: number) => string;
}) {
    const currentLeases = leaseContext.leases.filter(
        (lease) => lease.status === 'active',
    );
    const historicalLeases = leaseContext.leases.filter(
        (lease) => lease.status !== 'active',
    );

    return (
        <div className="space-y-3">
            <LeaseOptionGroup
                label="Current"
                leases={currentLeases}
                selectedLeaseId={leaseContext.selected?.id}
                hrefForLease={hrefForLease}
            />
            {historicalLeases.length > 0 && (
                <LeaseOptionGroup
                    label="History"
                    leases={historicalLeases}
                    selectedLeaseId={leaseContext.selected?.id}
                    hrefForLease={hrefForLease}
                />
            )}
        </div>
    );
}

function LeaseOptionGroup({
    label,
    leases,
    selectedLeaseId,
    hrefForLease,
}: {
    label: string;
    leases: LeaseContext['leases'];
    selectedLeaseId: number | undefined;
    hrefForLease: (leaseId: number) => string;
}) {
    if (leases.length === 0) {
        return null;
    }

    return (
        <div>
            <p className="px-2 text-xs font-medium text-muted-foreground">
                {label}
            </p>
            <div className="mt-1 grid gap-1">
                {leases.map((lease) => (
                    <Button
                        key={lease.id}
                        variant={
                            lease.id === selectedLeaseId ? 'secondary' : 'ghost'
                        }
                        className="h-auto justify-start px-2 py-2 text-left"
                        asChild
                    >
                        <Link href={hrefForLease(lease.id)}>
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-medium">
                                    {lease.unit_name ?? 'Unit'}
                                    {lease.property_name &&
                                        ` · ${lease.property_name}`}
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    {formatDate(lease.start_date)} —{' '}
                                    {lease.end_date
                                        ? formatDate(lease.end_date)
                                        : 'Ongoing'}
                                </span>
                            </span>
                            {lease.id === selectedLeaseId && (
                                <Check className="size-4 shrink-0" />
                            )}
                        </Link>
                    </Button>
                ))}
            </div>
        </div>
    );
}
