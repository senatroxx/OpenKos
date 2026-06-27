import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { LeaseDetailSheet, MoveOutSheet } from '@/components/features';
import { Heading } from '@/components/shared';
import { Badge } from '@/components/ui/badge';
import properties from '@/routes/properties';

type TenantInfo = {
    id: number;
    name: string;
    phone: string | null;
    pivot?: {
        is_primary: boolean;
    };
};

type Lease = {
    id: number;
    start_date: string;
    end_date: string | null;
    rent_amount: string | null;
    billing_interval: number;
    billing_unit: string;
    monthly_equivalent: string;
    billing_label: string;
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
    tenants: TenantInfo[];
    primary_tenant: TenantInfo | null;
    room: {
        id: number;
        name: string;
        property_id: number;
        property: {
            id: number;
            name: string;
            city: { name: string } | null;
        } | null;
    } | null;
    payments?: Array<{
        id: number;
        amount: string;
        payment_date: string;
        period_start: string;
        period_end: string;
        payment_method: string;
        notes: string | null;
        status: string;
        confirmed_by: number | null;
        confirmed_by_user?: { id: number; name: string } | null;
        recorded_by: number | null;
        proof_path: string | null;
    }>;
};

type Property = {
    id: number;
    name: string;
    slug: string;
    city: string | null;
};

type RoomRate = {
    id: number;
    billing_interval: number;
    billing_unit: 'day' | 'week' | 'month' | 'year';
    amount: string;
};

type Room = {
    id: number;
    name: string;
    floor: string | null;
    active_rates: RoomRate[];
};

type AvailableRoom = {
    id: number;
    name: string;
    property_id: number;
    capacity: number;
    occupied_count: number;
    property: {
        id: number;
        name: string;
        city: { name: string } | null;
    } | null;
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

export default function Index({
    property,
    room,
    leases,
    availableRooms: _availableRooms,
}: PageProps) {
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
                            room.floor ? `Floor ${room.floor}` : undefined
                        }
                    />
                </div>

                {leases.length === 0 ? (
                    <div className="flex flex-1 items-center justify-center rounded-lg border py-16">
                        <p className="text-muted-foreground">
                            No lease history for this room.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                    <th className="px-4 py-3 font-medium">
                                        Tenant
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Start
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        End
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Rent
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Deposit
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Due Day
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Termination
                                    </th>
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
                                            {(lease.tenants ?? []).length > 0
                                                ? lease.tenants.map((t) => (
                                                      <div key={t.id}>
                                                          <p className="font-medium">
                                                              {t.name}
                                                              {t.pivot
                                                                  ?.is_primary && (
                                                                  <span className="ml-1 text-[10px] font-medium text-blue-600 uppercase">
                                                                      Primary
                                                                  </span>
                                                              )}
                                                          </p>
                                                          {t.phone && (
                                                              <p className="text-xs text-muted-foreground">
                                                                  {t.phone}
                                                              </p>
                                                          )}
                                                      </div>
                                                  ))
                                                : lease.primary_tenant && (
                                                      <div>
                                                          <p className="font-medium">
                                                              {
                                                                  lease
                                                                      .primary_tenant
                                                                      .name
                                                              }
                                                          </p>
                                                          {lease
                                                              .primary_tenant
                                                              .phone && (
                                                              <p className="text-xs text-muted-foreground">
                                                                  {
                                                                      lease
                                                                          .primary_tenant
                                                                          .phone
                                                                  }
                                                              </p>
                                                          )}
                                                      </div>
                                                  )}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {formatDate(lease.start_date)}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground tabular-nums">
                                            {formatDate(lease.end_date)}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {formatPrice(lease.rent_amount)}
                                            {lease.billing_label}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            <div>
                                                {formatPrice(
                                                    lease.deposit_amount,
                                                )}
                                            </div>
                                            {lease.deposit_refund_amount && (
                                                <div className="text-xs text-muted-foreground">
                                                    Refund:{' '}
                                                    {formatPrice(
                                                        lease.deposit_refund_amount,
                                                    )}
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
                                                {STATUS_LABELS[lease.status] ??
                                                    lease.status}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-sm">
                                            {lease.termination_date ? (
                                                <div>
                                                    <p className="tabular-nums">
                                                        {formatDate(
                                                            lease.termination_date,
                                                        )}
                                                    </p>
                                                    {lease.termination_reason && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {
                                                                lease.termination_reason
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
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
                onMoveOut={
                    detailLease?.status === 'active'
                        ? openMoveOutFromDetail
                        : undefined
                }
                onEdit={detailLease ? openEditFromDetail : undefined}
            />

            <MoveOutSheet
                lease={
                    detailLease
                        ? {
                              id: detailLease.id,
                              tenants: detailLease.tenants,
                              primary_tenant: detailLease.primary_tenant,
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
