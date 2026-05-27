import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import LeaseDetailSheet from '@/components/lease-detail-sheet';
import MoveOutSheet from '@/components/move-out-sheet';
import { Badge } from '@/components/ui/badge';
import properties from '@/routes/properties';

type TenantInfo = {
    id: number;
    name: string;
    phone: string | null;
};

type Lease = {
    id: number;
    start_date: string;
    end_date: string | null;
    monthly_rent: string | null;
    deposit_amount: string;
    deposit_paid_at: string | null;
    deposit_refund_amount: string | null;
    deposit_refunded_at: string | null;
    rent_due_day: number;
    status: string;
    termination_date: string | null;
    termination_reason: string | null;
    notes: string | null;
    created_at: string;
    tenant: TenantInfo | null;
    room: {
        id: number;
        name: string;
        property_id: number;
        property: { id: number; name: string; city: { name: string } | null } | null;
    } | null;
};

type Property = {
    id: number;
    name: string;
    slug: string;
    city: string | null;
};

type Room = {
    id: number;
    name: string;
    floor: string | null;
    base_price: string;
};

type AvailableRoom = {
    id: number;
    name: string;
    property_id: number;
    property: { id: number; name: string; city: { name: string } | null } | null;
};

type PageProps = {
    property: Property;
    room: Room;
    leases: Lease[];
    availableRooms: AvailableRoom[];
};

function formatPrice(cents: string | null): string {
    if (!cents) {
        return '—';
    }

    const num = Number.parseFloat(cents);

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(num);
}

function formatDate(date: string | null): string {
    if (!date) {
        return '—';
    }

    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

const STATUS_COLORS: Record<string, string> = {
    active: 'bg-blue-600',
    terminated: 'bg-gray-400',
};

const STATUS_LABELS: Record<string, string> = {
    active: 'Active',
    terminated: 'Terminated',
};

export default function Index({ property, room, leases, availableRooms: _availableRooms }: PageProps) {
    const backUrl = properties.rooms.index.url(property);

    const [detailLease, setDetailLease] = useState<Lease | null>(null);
    const [detailOpen, setDetailOpen] = useState(false);

    const [moveOutOpen, setMoveOutOpen] = useState(false);

    function openDetail(lease: Lease) {
        setDetailLease(lease);
        setDetailOpen(true);
    }

    function openMoveOutFromDetail() {
        setDetailOpen(false);
        setMoveOutOpen(true);
    }

    function openEditFromDetail() {
        if (!detailLease) {
            return;
        }

        setDetailOpen(false);
        router.get(backUrl);
    }

    return (
        <>
            <Head title={`Lease History - ${room.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div>
                    <div className="mb-1 inline-block text-xs text-muted-foreground">
                        <Link href={backUrl} className="hover:text-foreground">
                            &larr; Back to {property.name} rooms
                        </Link>
                    </div>
                    <Heading
                        title={`${room.name} — Lease History`}
                        description={
                            room.floor
                                ? `Floor ${room.floor} · ${formatPrice(room.base_price)}/mo`
                                : `${formatPrice(room.base_price)}/mo`
                        }
                    />
                </div>

                {leases.length === 0 ? (
                    <div className="flex flex-1 items-center justify-center rounded-lg border py-16">
                        <p className="text-muted-foreground">No lease history for this room.</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                    <th className="px-4 py-3 font-medium">Tenant</th>
                                    <th className="px-4 py-3 font-medium">Start</th>
                                    <th className="px-4 py-3 font-medium">End</th>
                                    <th className="px-4 py-3 font-medium">Rent</th>
                                    <th className="px-4 py-3 font-medium">Deposit</th>
                                    <th className="px-4 py-3 font-medium">Due Day</th>
                                    <th className="px-4 py-3 font-medium">Status</th>
                                    <th className="px-4 py-3 font-medium">Termination</th>
                                </tr>
                            </thead>
                            <tbody>
                                {leases.map((lease) => (
                                    <tr
                                        key={lease.id}
                                        className="cursor-pointer border-b last:border-0 hover:bg-muted/30"
                                        onClick={() => openDetail(lease)}
                                    >
                                        <td className="px-4 py-3">
                                            <p className="font-medium">
                                                {lease.tenant?.name ?? 'Unknown'}
                                            </p>
                                            {lease.tenant?.phone && (
                                                <p className="text-xs text-muted-foreground">
                                                    {lease.tenant.phone}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {formatDate(lease.start_date)}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums text-muted-foreground">
                                            {formatDate(lease.end_date)}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {formatPrice(lease.monthly_rent)}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            <div>{formatPrice(lease.deposit_amount)}</div>
                                            {lease.deposit_refund_amount && (
                                                <div className="text-xs text-muted-foreground">
                                                    Refund: {formatPrice(lease.deposit_refund_amount)}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {lease.rent_due_day}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                className={`${STATUS_COLORS[lease.status] ?? 'bg-gray-400'} text-white`}
                                            >
                                                {STATUS_LABELS[lease.status] ?? lease.status}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-sm">
                                            {lease.termination_date ? (
                                                <div>
                                                    <p className="tabular-nums">
                                                        {formatDate(lease.termination_date)}
                                                    </p>
                                                    {lease.termination_reason && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {lease.termination_reason}
                                                        </p>
                                                    )}
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <LeaseDetailSheet
                lease={detailLease}
                open={detailOpen}
                onOpenChange={setDetailOpen}
                onMoveOut={detailLease?.status === 'active' ? openMoveOutFromDetail : undefined}
                onEdit={detailLease ? openEditFromDetail : undefined}
            />

            <MoveOutSheet
                lease={
                    detailLease
                        ? {
                            id: detailLease.id,
                            tenant: detailLease.tenant,
                            room: detailLease.room,
                        }
                        : null
                }
                availableRooms={_availableRooms}
                open={moveOutOpen}
                onOpenChange={setMoveOutOpen}
            />
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        {
            title: 'Properties',
            href: properties.index(),
        },
        {
            title: 'Rooms',
        },
    ],
};
