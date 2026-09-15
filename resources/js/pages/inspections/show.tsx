import { router, useForm, Link } from '@inertiajs/react';
import { Check, Save } from 'lucide-react';
import { useMemo } from 'react';
import { InspectionPhotoUploader } from '@/components/features/inspections';
import { InputError } from '@/components/shared';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatDateTime } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import inspectionRoutes from '@/routes/inspections';
import leases from '@/routes/leases';
import properties from '@/routes/properties';
import type { Inspection, InspectionItemCondition } from '@/types';

const TYPE_LABELS: Record<string, string> = {
    move_in: 'Move-in',
    move_out: 'Move-out',
    periodic: 'Periodic',
};

const CONDITION_LABELS: Record<InspectionItemCondition, string> = {
    good: 'Good',
    fair: 'Fair',
    damaged: 'Damaged',
    not_applicable: 'Not applicable',
};

type InspectionFormData = {
    notes: string;
    damage_observations: string;
    items: {
        id: number;
        condition: InspectionItemCondition | null;
        notes: string;
    }[];
};

function backContext(inspection: Inspection): { href: string; label: string } {
    const archived = (record?: { deleted_at?: string | null } | null) =>
        Boolean(record?.deleted_at);

    if (
        inspection.lease &&
        !archived(inspection.property) &&
        !archived(inspection.unit) &&
        !archived(inspection.lease)
    ) {
        return {
            href: leases.workspace.inspections.url(inspection.lease),
            label: 'Lease inspections',
        };
    }

    if (
        inspection.property &&
        inspection.unit &&
        !archived(inspection.property) &&
        !archived(inspection.unit)
    ) {
        return {
            href: properties.units.inspections.url({
                property: inspection.property.slug,
                unit: inspection.unit.slug,
            }),
            label: `${inspection.unit.name} inspections`,
        };
    }

    if (inspection.property && !archived(inspection.property)) {
        return {
            href: properties.workspace.inspections.url(inspection.property),
            label: `${inspection.property.name} inspections`,
        };
    }

    return {
        href: inspectionRoutes.index.url(),
        label: 'All inspections',
    };
}

export default function InspectionShow({
    inspection,
    can,
}: {
    inspection: Inspection;
    can: { update: boolean; complete: boolean };
}) {
    const completed = inspection.status === 'completed';
    const context = backContext(inspection);
    const form = useForm<InspectionFormData>({
        notes: inspection.notes ?? '',
        damage_observations: inspection.damage_observations ?? '',
        items: (inspection.items ?? []).map((item) => ({
            id: item.id,
            condition: item.condition,
            notes: item.notes ?? '',
        })),
    });

    const completedCount = useMemo(
        () => form.data.items.filter((item) => item.condition !== null).length,
        [form.data.items],
    );

    function updateItem(
        id: number,
        key: 'condition' | 'notes',
        value: InspectionItemCondition | string | null,
    ) {
        form.setData(
            'items',
            form.data.items.map((item) =>
                item.id === id ? { ...item, [key]: value } : item,
            ),
        );
    }

    function save(event: React.FormEvent) {
        event.preventDefault();
        form.put(inspectionRoutes.update.url(inspection.id), {
            preserveScroll: true,
        });
    }

    function completeInspection() {
        form.put(inspectionRoutes.update.url(inspection.id), {
            preserveScroll: true,
            onSuccess: () =>
                router.post(inspectionRoutes.complete.url(inspection.id), {
                    preserveScroll: true,
                }),
        });
    }

    return (
        <EntityWorkspaceLayout
            title={inspection.template_name}
            subtitle={`${TYPE_LABELS[inspection.inspection_type]} · ${formatDate(inspection.inspection_date)}`}
            backRoute={context.href}
            backLabel={context.label}
            actions={
                <StatusBadge domain="inspection" value={inspection.status} />
            }
        >
            <div className="mx-auto max-w-5xl space-y-6">
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardDescription>{t('Property')}</CardDescription>
                            <CardTitle className="text-base">
                                {inspection.property &&
                                !inspection.property.deleted_at ? (
                                    <Link
                                        href={properties.show.url(
                                            inspection.property,
                                        )}
                                        className="hover:underline"
                                    >
                                        {inspection.property.name}
                                    </Link>
                                ) : (
                                    '—'
                                )}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>{t('Unit')}</CardDescription>
                            <CardTitle className="text-base">
                                {inspection.unit &&
                                !inspection.unit.deleted_at &&
                                !inspection.property?.deleted_at ? (
                                    <Link
                                        href={properties.units.show.url({
                                            property:
                                                inspection.property?.slug ?? '',
                                            unit: inspection.unit,
                                        })}
                                        className="hover:underline"
                                    >
                                        {inspection.unit.name}
                                    </Link>
                                ) : (
                                    t('Property only')
                                )}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>{t('Lease')}</CardDescription>
                            <CardTitle className="text-base">
                                {inspection.lease?.reference ??
                                    t('Not lease-scoped')}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <form onSubmit={save} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Checklist')}</CardTitle>
                            <CardDescription>
                                {completed
                                    ? t(
                                          ':count of :total items assessed. This record is locked.',
                                          {
                                              count: completedCount,
                                              total: form.data.items.length,
                                          },
                                      )
                                    : t(':count of :total items assessed.', {
                                          count: completedCount,
                                          total: form.data.items.length,
                                      })}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {inspection.items?.map((item, index) => {
                                const formItem = form.data.items[index];

                                return (
                                    <div
                                        key={item.id}
                                        className="space-y-4 rounded-lg border p-4"
                                    >
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <p className="font-medium">
                                                    {item.position + 1}.{' '}
                                                    {item.label}
                                                </p>
                                                {item.description && (
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        {item.description}
                                                    </p>
                                                )}
                                            </div>
                                            {completed ? (
                                                <StatusBadge
                                                    domain="inspection_condition"
                                                    value={
                                                        item.condition ??
                                                        'not_applicable'
                                                    }
                                                />
                                            ) : (
                                                <Select
                                                    value={
                                                        formItem?.condition ??
                                                        'unassessed'
                                                    }
                                                    onValueChange={(value) =>
                                                        updateItem(
                                                            item.id,
                                                            'condition',
                                                            value ===
                                                                'unassessed'
                                                                ? null
                                                                : (value as InspectionItemCondition),
                                                        )
                                                    }
                                                    disabled={
                                                        !can.update ||
                                                        form.processing
                                                    }
                                                >
                                                    <SelectTrigger className="w-full sm:w-44">
                                                        <SelectValue
                                                            placeholder={t(
                                                                'Assess item',
                                                            )}
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="unassessed">
                                                            {t('Not assessed')}
                                                        </SelectItem>
                                                        {Object.entries(
                                                            CONDITION_LABELS,
                                                        ).map(
                                                            ([
                                                                value,
                                                                label,
                                                            ]) => (
                                                                <SelectItem
                                                                    key={value}
                                                                    value={
                                                                        value
                                                                    }
                                                                >
                                                                    {label}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            )}
                                        </div>

                                        {completed ? (
                                            item.notes && (
                                                <p className="text-sm text-muted-foreground">
                                                    {item.notes}
                                                </p>
                                            )
                                        ) : (
                                            <Textarea
                                                value={formItem?.notes ?? ''}
                                                onChange={(event) =>
                                                    updateItem(
                                                        item.id,
                                                        'notes',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder={t('Item notes')}
                                                rows={2}
                                                disabled={
                                                    !can.update ||
                                                    form.processing
                                                }
                                            />
                                        )}

                                        <InspectionPhotoUploader
                                            inspectionId={inspection.id}
                                            itemId={item.id}
                                            photos={item.photos ?? []}
                                            disabled={completed || !can.update}
                                        />
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>

                    <div className="grid gap-6 md:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Notes')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {completed ? (
                                    <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                        {inspection.notes || '—'}
                                    </p>
                                ) : (
                                    <Textarea
                                        value={form.data.notes}
                                        onChange={(event) =>
                                            form.setData(
                                                'notes',
                                                event.target.value,
                                            )
                                        }
                                        rows={5}
                                        disabled={
                                            !can.update || form.processing
                                        }
                                    />
                                )}
                                <InputError message={form.errors.notes} />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {t('Damage observations')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {completed ? (
                                    <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                        {inspection.damage_observations || '—'}
                                    </p>
                                ) : (
                                    <Textarea
                                        value={form.data.damage_observations}
                                        onChange={(event) =>
                                            form.setData(
                                                'damage_observations',
                                                event.target.value,
                                            )
                                        }
                                        rows={5}
                                        disabled={
                                            !can.update || form.processing
                                        }
                                    />
                                )}
                                <InputError
                                    message={form.errors.damage_observations}
                                />
                            </CardContent>
                        </Card>
                    </div>

                    {!completed && can.update && (
                        <div className="flex flex-wrap justify-end gap-2">
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={form.processing}
                            >
                                <Save className="size-4" />
                                {t('Save draft')}
                            </Button>
                            {can.complete && (
                                <Button
                                    type="button"
                                    onClick={completeInspection}
                                    disabled={
                                        form.processing ||
                                        completedCount !==
                                            form.data.items.length
                                    }
                                >
                                    <Check className="size-4" />
                                    {t('Complete inspection')}
                                </Button>
                            )}
                        </div>
                    )}
                </form>

                <Card>
                    <CardContent className="grid gap-2 py-6 text-sm text-muted-foreground sm:grid-cols-2">
                        <p>
                            {t('Inspector')}:{' '}
                            {inspection.inspector?.name ?? '—'}
                        </p>
                        <p>
                            {t('Completed')}:{' '}
                            {formatDateTime(inspection.completed_at)}
                        </p>
                        {inspection.completed_by && (
                            <p>
                                {t('Completed by')}:{' '}
                                {inspection.completed_by.name}
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </EntityWorkspaceLayout>
    );
}
