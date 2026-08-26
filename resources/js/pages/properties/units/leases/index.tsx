import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { LeaseDetailSheet, MoveOutSheet } from '@/components/features';
import { Heading } from '@/components/shared';
import { StatusBadge } from '@/components/shared/status-badge';
import { formatDate, formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import properties from '@/routes/properties';
import type { AvailableUnit, Lease, Property, Unit } from '@/types';

type PageProps = {
    property: Property;
    unit: Unit;
    leases: Lease[];
    availableUnits: AvailableUnit[];
};

export default function Index({
    property,
    unit,
    leases,
    availableUnits: _availableUnits,
}: PageProps) {
    const backUrl = properties.units.index.url(property);

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

    return (
        <>
            <Head title={`Lease History - ${unit.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div>
                    <div className="mb-1 inline-block text-xs text-muted-foreground">
                        <Link href={backUrl} className="hover:text-foreground">
                            &larr; Back to {property.name} units
                        </Link>
                    </div>
                    <Heading
                        title={`${unit.name} — Lease History`}
                        description={
                            unit.floor ? `Floor ${unit.floor}` : undefined
                        }
                    />
                </div>

                {leases.length === 0 ? (
                    <div className="flex flex-1 items-center justify-center rounded-lg border py-16">
                        <p className="text-muted-foreground">
                            {t('No lease history for this unit.')}
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border bg-card text-card-foreground shadow-xs">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                    <th className="px-4 py-3 font-medium">
                                        {t('Reference')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('Tenant')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('Start')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('End')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('Rent')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('Deposit')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('Due Day')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('Status')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('Termination')}
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
                                        <td className="px-4 py-3 font-mono text-xs">
                                            {lease.reference ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {(lease.tenants ?? []).length > 0
                                                ? lease.tenants.map(
                                                      (tenant) => (
                                                          <div key={tenant.id}>
                                                              <p className="font-medium">
                                                                  {tenant.name}
                                                                  {tenant.pivot
                                                                      ?.is_primary && (
                                                                      <span className="ml-1 text-xs font-medium text-primary uppercase">
                                                                          {t(
                                                                              'Primary',
                                                                          )}
                                                                      </span>
                                                                  )}
                                                              </p>
                                                              {tenant.phone && (
                                                                  <p className="text-xs text-muted-foreground">
                                                                      {
                                                                          tenant.phone
                                                                      }
                                                                  </p>
                                                              )}
                                                          </div>
                                                      ),
                                                  )
                                                : lease.primary_tenant && (
                                                      <div>
                                                          <p className="font-medium">
                                                              {
                                                                  lease
                                                                      .primary_tenant
                                                                      .name
                                                              }
                                                          </p>
                                                          {lease.primary_tenant
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
                                            {formatPrice(
                                                lease.rent_amount,
                                                lease.currency,
                                            )}
                                            {lease.billing_label}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            <div>
                                                {formatPrice(
                                                    lease.deposit_amount,
                                                    lease.currency,
                                                )}
                                            </div>
                                            {lease.deposit_refund_amount && (
                                                <div className="text-xs text-muted-foreground">
                                                    Refund:{' '}
                                                    {formatPrice(
                                                        lease.deposit_refund_amount,
                                                        lease.currency,
                                                    )}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {lease.rent_due_day}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge
                                                domain="lease"
                                                value={lease.status}
                                            />
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
            />

            <MoveOutSheet
                lease={
                    detailLease
                        ? {
                              id: detailLease.id,
                              tenants: detailLease.tenants,
                              primary_tenant: detailLease.primary_tenant,
                              unit: detailLease.unit,
                          }
                        : null
                }
                availableUnits={_availableUnits}
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
            title: 'Units',
        },
    ],
};
