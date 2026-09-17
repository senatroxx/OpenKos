import { router, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import leases from '@/actions/App/Http/Controllers/LeaseController';
import type { TableColumn } from '@/components/data-table';
import { InputError } from '@/components/shared';
import { PluginRegion } from '@/components/shared/plugin-region';
import { StatusBadge } from '@/components/shared/status-badge';
import { WorkspaceTable } from '@/components/shared/workspace-table';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatPrice, todayISO } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import { supportsPropertyPricing } from '@/lib/property-rental-mode';
import type {
    Lease,
    PaginatedData,
    Property,
    PropertyRate,
    TableMeta,
    Tenant,
} from '@/types';
import { PropertyLayout } from './layout';

const columns: TableColumn<Lease>[] = [
    {
        key: 'reference',
        label: 'Reference',
        sortable: true,
        className: 'font-mono text-xs',
        render: (l) => l.reference ?? `#${l.id}`,
    },
    {
        key: '_target',
        label: 'Target',
        className: 'font-medium',
        render: (l) =>
            l.target_type === 'whole_property'
                ? 'Entire property'
                : (l.unit?.name ?? '—'),
    },
    {
        key: '_tenants',
        label: 'Tenants',
        render: (l) => {
            const occupants = l.tenants.length
                ? l.tenants
                : l.primary_tenant
                  ? [l.primary_tenant]
                  : [];

            return occupants.length ? (
                <div className="text-sm">
                    {occupants.map((t) => (
                        <div key={t.id}>{t.name}</div>
                    ))}
                </div>
            ) : (
                '—'
            );
        },
    },
    {
        key: 'start_date',
        label: 'Start',
        sortable: true,
        className: 'text-muted-foreground tabular-nums',
        render: (l) => formatDate(l.start_date),
    },
    {
        key: 'end_date',
        label: 'End',
        sortable: true,
        className: 'text-muted-foreground tabular-nums',
        render: (l) => (l.end_date ? formatDate(l.end_date) : 'ongoing'),
    },
    {
        key: 'rent_amount',
        label: 'Rent',
        sortable: true,
        className: 'tabular-nums',
        render: (l) => formatPrice(l.rent_amount, l.currency),
    },
    {
        key: 'status',
        label: 'Status',
        sortable: true,
        render: (l) => <StatusBadge domain="lease" value={l.status} />,
    },
];

export default function PropertyLeases({
    property,
    leases,
    sort = '-start_date',
    search = '',
    status = '',
    per_page = 15,
    table,
}: {
    property: Property;
    leases: PaginatedData<Lease>;
    sort?: string;
    search?: string;
    status?: string;
    per_page?: number;
    table: TableMeta;
}) {
    const [createOpen, setCreateOpen] = useState(false);
    const { tenants } = usePage<{ tenants: Tenant[] }>().props;

    return (
        <PropertyLayout property={property} activeTab="leases">
            <PluginRegion name="workspace-tab-leases">
                <div className="space-y-4">
                    {supportsPropertyPricing(property.rental_mode) && (
                        <div className="flex justify-end">
                            <Button onClick={() => setCreateOpen(true)}>
                                <Plus className="size-4" />
                                {t('New whole-property lease')}
                            </Button>
                        </div>
                    )}
                    <WorkspaceTable
                        url={`/properties/${property.slug}/leases`}
                        noun="leases"
                        rows={leases}
                        columns={columns}
                        tableMeta={table}
                        sort={sort}
                        search={search}
                        perPage={per_page}
                        filterValues={{ status }}
                        defaultSort="-start_date"
                        searchPlaceholder="Search by reference, tenant, or unit..."
                        emptyMessage="No leases for this property yet."
                        onRowClick={(l) => router.get(`/leases/${l.id}`)}
                    />
                </div>
            </PluginRegion>
            {supportsPropertyPricing(property.rental_mode) && (
                <WholePropertyLeaseSheet
                    property={property}
                    tenants={tenants}
                    open={createOpen}
                    onOpenChange={setCreateOpen}
                />
            )}
        </PropertyLayout>
    );
}

type WholePropertyLeaseForm = {
    tenant_ids: number[];
    property_rate_id: number | null;
    start_date: string;
    end_date: string;
    rent_amount: string;
    deposit_amount: string;
    rent_due_day: string;
    notes: string;
};

function WholePropertyLeaseSheet({
    property,
    tenants,
    open,
    onOpenChange,
}: {
    property: Property;
    tenants: Tenant[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const rates = property.active_property_rates ?? [];
    const firstRate = rates[0];
    const { data, setData, submit, reset, processing, errors } =
        useForm<WholePropertyLeaseForm>({
            tenant_ids: [],
            property_rate_id: firstRate?.id ?? null,
            start_date: todayISO(),
            end_date: '',
            rent_amount: firstRate?.amount ?? '',
            deposit_amount: '0',
            rent_due_day: '1',
            notes: '',
        });
    const selectedRate = rates.find(
        (rate) => rate.id === data.property_rate_id,
    );

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();
        submit(leases.storeForProperty({ property: property.slug }), {
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        });
    }

    function toggleTenant(tenantId: number, checked: boolean) {
        setData(
            'tenant_ids',
            checked
                ? [...data.tenant_ids, tenantId]
                : data.tenant_ids.filter((id) => id !== tenantId),
        );
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{t('New whole-property lease')}</SheetTitle>
                    <SheetDescription>
                        {t('Lease the full property without assigning a unit.')}
                    </SheetDescription>
                </SheetHeader>
                <form
                    onSubmit={handleSubmit}
                    className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6"
                >
                    <div className="space-y-5">
                        <div className="grid gap-2">
                            <Label>{t('Tenants')}</Label>
                            <div className="max-h-40 space-y-2 overflow-y-auto rounded-md border p-3">
                                {tenants.map((tenant) => (
                                    <label
                                        key={tenant.id}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={data.tenant_ids.includes(
                                                tenant.id,
                                            )}
                                            onCheckedChange={(checked) =>
                                                toggleTenant(
                                                    tenant.id,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        {tenant.name}
                                    </label>
                                ))}
                            </div>
                            <InputError message={errors.tenant_ids} />
                        </div>
                        <div className="grid gap-2">
                            <Label>{t('Property rate')}</Label>
                            <Select
                                value={data.property_rate_id?.toString() ?? ''}
                                onValueChange={(value) => {
                                    const rate = rates.find(
                                        (item) => item.id === Number(value),
                                    );
                                    setData('property_rate_id', Number(value));

                                    if (rate) {
                                        setData('rent_amount', rate.amount);
                                    }
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue
                                        placeholder={t('Select a rate')}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {rates.map((rate: PropertyRate) => (
                                        <SelectItem
                                            key={rate.id}
                                            value={rate.id!.toString()}
                                        >
                                            {formatPrice(
                                                rate.amount,
                                                rate.currency,
                                            )}{' '}
                                            / {rate.billing_unit}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.property_rate_id} />
                        </div>
                        <div className="grid gap-2 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="whole-start-date">
                                    {t('Start date')}
                                </Label>
                                <Input
                                    id="whole-start-date"
                                    type="date"
                                    value={data.start_date}
                                    onChange={(event) =>
                                        setData(
                                            'start_date',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError message={errors.start_date} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="whole-end-date">
                                    {t('End date')}
                                </Label>
                                <Input
                                    id="whole-end-date"
                                    type="date"
                                    value={data.end_date}
                                    onChange={(event) =>
                                        setData('end_date', event.target.value)
                                    }
                                />
                                <InputError message={errors.end_date} />
                            </div>
                        </div>
                        <div className="grid gap-2 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="whole-rent-amount">
                                    {t('Rent amount')} (
                                    {selectedRate?.currency ?? '—'})
                                </Label>
                                <Input
                                    id="whole-rent-amount"
                                    inputMode="decimal"
                                    value={data.rent_amount}
                                    onChange={(event) =>
                                        setData(
                                            'rent_amount',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={errors.rent_amount} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="whole-due-day">
                                    {t('Rent due day')}
                                </Label>
                                <Input
                                    id="whole-due-day"
                                    type="number"
                                    min="1"
                                    max="31"
                                    value={data.rent_due_day}
                                    onChange={(event) =>
                                        setData(
                                            'rent_due_day',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError message={errors.rent_due_day} />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="whole-deposit">
                                {t('Deposit amount')}
                            </Label>
                            <Input
                                id="whole-deposit"
                                inputMode="decimal"
                                value={data.deposit_amount}
                                onChange={(event) =>
                                    setData(
                                        'deposit_amount',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={errors.deposit_amount} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="whole-notes">{t('Notes')}</Label>
                            <Textarea
                                id="whole-notes"
                                value={data.notes}
                                onChange={(event) =>
                                    setData('notes', event.target.value)
                                }
                            />
                            <InputError message={errors.notes} />
                        </div>
                    </div>
                    <SheetFooter>
                        <Button
                            type="submit"
                            disabled={processing || !firstRate}
                        >
                            {t('Create lease')}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
