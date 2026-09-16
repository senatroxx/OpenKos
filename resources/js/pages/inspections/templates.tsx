import { useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { InputError } from '@/components/shared';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import inspectionTemplates from '@/routes/inspections/templates';
import type { InspectionTemplate, InspectionType } from '@/types';

const TYPE_LABELS: Record<InspectionType, string> = {
    move_in: 'Move-in',
    move_out: 'Move-out',
    periodic: 'Periodic',
};

type TemplateFormData = {
    name: string;
    inspection_type: InspectionType;
    is_active: boolean;
    items: {
        id?: number;
        label: string;
        description: string;
    }[];
};

function TemplateFormSheet({
    editing,
    open,
    onOpenChange,
}: {
    editing: InspectionTemplate | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm<TemplateFormData>({
        name: editing?.name ?? '',
        inspection_type: editing?.inspection_type ?? 'periodic',
        is_active: editing?.is_active ?? true,
        items: editing?.items.map((item) => ({
            id: item.id,
            label: item.label,
            description: item.description ?? '',
        })) ?? [{ label: '', description: '' }],
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        const options = {
            onSuccess: () => onOpenChange(false),
        };

        if (editing) {
            form.patch(inspectionTemplates.update.url(editing.id), options);
        } else {
            form.post(inspectionTemplates.store.url(), options);
        }
    }

    function updateItem(
        index: number,
        key: 'label' | 'description',
        value: string,
    ) {
        const items = [...form.data.items];
        items[index] = { ...items[index], [key]: value };
        form.setData('items', items);
    }

    function removeItem(index: number) {
        if (form.data.items.length === 1) {
            return;
        }

        form.setData(
            'items',
            form.data.items.filter((_, itemIndex) => itemIndex !== index),
        );
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-xl">
                <SheetHeader>
                    <SheetTitle>
                        {editing
                            ? t('Edit inspection template')
                            : t('New inspection template')}
                    </SheetTitle>
                    <SheetDescription>
                        {t(
                            'Template edits apply only to inspections created in the future. Existing records keep their snapshots.',
                        )}
                    </SheetDescription>
                </SheetHeader>

                <form onSubmit={submit} className="flex flex-1 flex-col">
                    <div className="space-y-6 overflow-y-auto px-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="template-name">{t('Name')}</Label>
                            <Input
                                id="template-name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                placeholder={t(
                                    'e.g. Standard move-in checklist',
                                )}
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="template-type">
                                {t('Inspection type')}
                            </Label>
                            <Select
                                value={form.data.inspection_type}
                                onValueChange={(value) =>
                                    form.setData(
                                        'inspection_type',
                                        value as InspectionType,
                                    )
                                }
                            >
                                <SelectTrigger
                                    id="template-type"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(TYPE_LABELS).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.inspection_type} />
                        </div>

                        {editing && (
                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <div>
                                    <Label htmlFor="template-active">
                                        {t('Active')}
                                    </Label>
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            'Inactive templates cannot be used for new inspections.',
                                        )}
                                    </p>
                                </div>
                                <Switch
                                    id="template-active"
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) =>
                                        form.setData('is_active', checked)
                                    }
                                />
                            </div>
                        )}

                        <div className="space-y-4">
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <Label>{t('Checklist items')}</Label>
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            'Items are copied into each inspection as a historical snapshot.',
                                        )}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        form.setData('items', [
                                            ...form.data.items,
                                            { label: '', description: '' },
                                        ])
                                    }
                                >
                                    <Plus className="size-4" />
                                    {t('Add item')}
                                </Button>
                            </div>

                            {form.data.items.map((item, index) => (
                                <div
                                    key={item.id ?? `new-${index}`}
                                    className="flex flex-col space-y-3 rounded-lg border p-3"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <Label htmlFor={`item-${index}-label`}>
                                            {t('Item :number', {
                                                number: index + 1,
                                            })}
                                        </Label>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => removeItem(index)}
                                            disabled={
                                                form.data.items.length === 1
                                            }
                                        >
                                            <Trash2 className="size-4 text-red-500" />
                                        </Button>
                                    </div>
                                    <div className="flex gap-2">
                                        <div className="flex flex-1 flex-col gap-2">
                                            <Input
                                                id={`item-${index}-label`}
                                                value={item.label}
                                                onChange={(event) =>
                                                    updateItem(
                                                        index,
                                                        'label',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder={t(
                                                    'e.g. Walls and paint',
                                                )}
                                            />
                                            <InputError
                                                message={
                                                    form.errors[
                                                        `items.${index}.label`
                                                    ]
                                                }
                                            />
                                        </div>
                                    </div>
                                    <Textarea
                                        value={item.description}
                                        onChange={(event) =>
                                            updateItem(
                                                index,
                                                'description',
                                                event.target.value,
                                            )
                                        }
                                        placeholder={t(
                                            'Optional guidance for the inspector',
                                        )}
                                        rows={2}
                                    />
                                    <InputError
                                        message={
                                            form.errors[
                                                `items.${index}.description`
                                            ]
                                        }
                                    />
                                </div>
                            ))}
                        </div>
                    </div>

                    <SheetFooter className="border-t">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={form.processing}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {editing
                                ? t('Save template')
                                : t('Create template')}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}

export default function InspectionTemplates({
    templates,
}: {
    templates: InspectionTemplate[];
}) {
    const [editing, setEditing] = useState<InspectionTemplate | null>(null);
    const [sheetOpen, setSheetOpen] = useState(false);

    function openNew() {
        setEditing(null);
        setSheetOpen(true);
    }

    function openEdit(template: InspectionTemplate) {
        setEditing(template);
        setSheetOpen(true);
    }

    return (
        <div className="space-y-6 px-4 py-6">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <h2 className="text-lg font-medium">
                        {t('Inspection Templates')}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t(
                            'Reusable installation-wide checklists for move-in, move-out, and periodic inspections.',
                        )}
                    </p>
                </div>
                <Button onClick={openNew}>
                    <Plus className="size-4" />
                    {t('New template')}
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{t('Checklists')}</CardTitle>
                    <CardDescription>
                        {t(
                            'Deactivated templates remain available as lineage for historical inspections.',
                        )}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                    <th className="px-4 py-3 font-medium">
                                        {t('Name')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('Type')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('Items')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('Status')}
                                    </th>
                                    <th className="px-4 py-3 font-medium" />
                                </tr>
                            </thead>
                            <tbody>
                                {templates.map((template) => (
                                    <tr
                                        key={template.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {template.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {
                                                TYPE_LABELS[
                                                    template.inspection_type
                                                ]
                                            }
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {template.items.length}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge
                                                domain="property"
                                                value={
                                                    template.is_active
                                                        ? 'active'
                                                        : 'inactive'
                                                }
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    openEdit(template)
                                                }
                                            >
                                                <Pencil className="size-4" />
                                                {t('Edit')}
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <TemplateFormSheet
                key={editing?.id ?? 'new'}
                editing={editing}
                open={sheetOpen}
                onOpenChange={setSheetOpen}
            />
        </div>
    );
}
