import { useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
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
import { todayISO } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import type { InspectionTemplateOption, InspectionType } from '@/types';

const TYPE_LABELS: Record<InspectionType, string> = {
    move_in: 'Move-in',
    move_out: 'Move-out',
    periodic: 'Periodic',
};

export function InspectionFormSheet({
    open,
    onOpenChange,
    createUrl,
    context,
    templates,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    createUrl: string;
    context: 'property' | 'unit' | 'lease';
    templates: InspectionTemplateOption[];
}) {
    const availableTypes: InspectionType[] =
        context === 'lease'
            ? ['move_in', 'move_out', 'periodic']
            : ['periodic'];
    const initialType = availableTypes[0];
    const initialTemplate = templates.find(
        (template) => template.inspection_type === initialType,
    );
    const form = useForm<{
        inspection_type: InspectionType;
        inspection_template_id: number | '';
        inspection_date: string;
        notes: string;
        damage_observations: string;
    }>({
        inspection_type: initialType,
        inspection_template_id: initialTemplate?.id ?? '',
        inspection_date: todayISO(),
        notes: '',
        damage_observations: '',
    });

    const matchingTemplates = useMemo(
        () =>
            templates.filter(
                (template) =>
                    template.inspection_type === form.data.inspection_type,
            ),
        [form.data.inspection_type, templates],
    );

    function changeType(value: string) {
        const inspectionType = value as InspectionType;
        const template = templates.find(
            (item) => item.inspection_type === inspectionType,
        );

        form.setData((data) => ({
            ...data,
            inspection_type: inspectionType,
            inspection_template_id: template?.id ?? '',
        }));
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();
        form.post(createUrl, {
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>{t('New inspection')}</SheetTitle>
                    <SheetDescription>
                        {context === 'property'
                            ? t(
                                  'Property-only inspections use the periodic checklist type.',
                              )
                            : context === 'unit'
                              ? t('Create a periodic inspection for this unit.')
                              : t(
                                    'Create a move-in, move-out, or periodic inspection for this lease.',
                                )}
                    </SheetDescription>
                </SheetHeader>

                <form onSubmit={submit} className="flex flex-1 flex-col">
                    <div className="space-y-6 overflow-y-auto px-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="inspection_type">{t('Type')}</Label>
                            <Select
                                value={form.data.inspection_type}
                                onValueChange={changeType}
                                disabled={context !== 'lease'}
                            >
                                <SelectTrigger
                                    id="inspection_type"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableTypes.map((type) => (
                                        <SelectItem key={type} value={type}>
                                            {TYPE_LABELS[type]}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.inspection_type} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="inspection_template_id">
                                {t('Checklist')}
                            </Label>
                            <Select
                                value={form.data.inspection_template_id.toString()}
                                onValueChange={(value) =>
                                    form.setData(
                                        'inspection_template_id',
                                        Number(value),
                                    )
                                }
                            >
                                <SelectTrigger
                                    id="inspection_template_id"
                                    className="w-full"
                                >
                                    <SelectValue
                                        placeholder={t('Select a checklist')}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {matchingTemplates.map((template) => (
                                        <SelectItem
                                            key={template.id}
                                            value={template.id.toString()}
                                        >
                                            {template.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {matchingTemplates.length === 0 && (
                                <p className="text-sm text-destructive">
                                    {t(
                                        'Create an active checklist template before starting an inspection.',
                                    )}
                                </p>
                            )}
                            <InputError
                                message={form.errors.inspection_template_id}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="inspection_date">
                                {t('Inspection date')}
                            </Label>
                            <Input
                                id="inspection_date"
                                type="date"
                                value={form.data.inspection_date}
                                onChange={(event) =>
                                    form.setData(
                                        'inspection_date',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.inspection_date} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="notes">{t('Notes')}</Label>
                            <Textarea
                                id="notes"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                                rows={3}
                            />
                            <InputError message={form.errors.notes} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="damage_observations">
                                {t('Damage observations')}
                            </Label>
                            <Textarea
                                id="damage_observations"
                                value={form.data.damage_observations}
                                onChange={(event) =>
                                    form.setData(
                                        'damage_observations',
                                        event.target.value,
                                    )
                                }
                                rows={3}
                            />
                            <InputError
                                message={form.errors.damage_observations}
                            />
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
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                matchingTemplates.length === 0
                            }
                        >
                            {t('Create draft')}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
