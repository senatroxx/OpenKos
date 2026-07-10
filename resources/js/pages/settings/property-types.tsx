import { router, useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import type { PropertyTypeOption } from '@/types';

const BASE = '/settings/property-types';

function SheetForm({ editing, closeSheet }: { editing: PropertyTypeOption | null; closeSheet: () => void }) {
    const { data, setData, processing, errors, post, patch } = useForm({
        label: editing?.label ?? '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        const url = editing ? `${BASE}/${editing.slug}` : BASE;
        (editing ? patch : post)(url, { onSuccess: closeSheet });
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-6 pt-4">
            <div className="grid gap-2">
                <Label htmlFor="label">Name</Label>
                <Input
                    id="label"
                    value={data.label}
                    onChange={e => setData('label', e.target.value)}
                    placeholder="e.g. Guesthouse"
                    required
                />
                <InputError message={errors.label} />
            </div>

            {editing && (
                <input
                    type="hidden"
                    name="is_active"
                    value={editing.is_active ? 1 : 0}
                />
            )}

            <div className="flex items-center justify-end gap-4">
                <Button
                    variant="outline"
                    type="button"
                    onClick={closeSheet}
                    disabled={processing}
                >
                    Cancel
                </Button>
                <Button disabled={processing}>
                    {editing ? 'Save' : 'Add'}
                </Button>
            </div>
        </form>
    );
}

export default function PropertyTypes({
    propertyTypes,
}: {
    propertyTypes: PropertyTypeOption[];
}) {
    const [editing, setEditing] = useState<PropertyTypeOption | null>(null);
    const [sheetOpen, setSheetOpen] = useState(false);
    const [deleteConfirm, setDeleteConfirm] =
        useState<PropertyTypeOption | null>(null);

    function openNew() {
        setEditing(null);
        setSheetOpen(true);
    }

    function openEdit(type: PropertyTypeOption) {
        setEditing(type);
        setSheetOpen(true);
    }

    function toggleActive(type: PropertyTypeOption, isActive: boolean) {
        router.patch(`${BASE}/${type.slug}`, {
            label: type.label,
            is_active: isActive,
        });
    }

    function destroy(type: PropertyTypeOption) {
        setDeleteConfirm(type);
    }

    function confirmDelete() {
        if (!deleteConfirm) {
            return;
        }

        router.delete(`${BASE}/${deleteConfirm.slug}`);
        setDeleteConfirm(null);
    }

    return (
        <div className="space-y-6">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <h2 className="text-lg font-medium">Property Types</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        The classifications available when creating a property.
                        Add your own (e.g. Kos, Riad, Guesthouse); deactivate
                        ones you don&apos;t use.
                    </p>
                </div>
                <Button onClick={openNew}>Add type</Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Types</CardTitle>
                    <CardDescription>
                        A type in use can be deactivated but not deleted.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                    <th className="px-4 py-3 font-medium">
                                        Label
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Slug
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        In use
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Active
                                    </th>
                                    <th className="px-4 py-3 font-medium" />
                                </tr>
                            </thead>
                            <tbody>
                                {propertyTypes.map((type) => (
                                    <tr
                                        key={type.slug}
                                        className="border-b last:border-0"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {type.label}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                            {type.slug}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground tabular-nums">
                                            {type.properties_count ?? 0}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Switch
                                                checked={type.is_active ?? true}
                                                onCheckedChange={(v) =>
                                                    toggleActive(type, v)
                                                }
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8"
                                                    onClick={() =>
                                                        openEdit(type)
                                                    }
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8"
                                                    disabled={
                                                        (type.properties_count ??
                                                            0) > 0
                                                    }
                                                    onClick={() =>
                                                        destroy(type)
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <Sheet
                key={editing?.slug ?? 'new'}
                open={sheetOpen}
                onOpenChange={setSheetOpen}
            >
                <SheetContent className="sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>
                            {editing ? 'Edit type' : 'New property type'}
                        </SheetTitle>
                        <SheetDescription>
                            {editing
                                ? 'Rename this type. Its slug stays fixed so existing properties keep working.'
                                : 'Add a classification. A slug is generated from the name.'}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="px-4">
                        <SheetForm editing={editing} closeSheet={() => setSheetOpen(false)} />
                    </div>
                </SheetContent>
            </Sheet>

            <Dialog
                open={deleteConfirm !== null}
                onOpenChange={() => setDeleteConfirm(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete type</DialogTitle>
                        <DialogDescription>
                            Delete{' '}
                            <span className="font-medium">
                                {deleteConfirm?.label}
                            </span>
                            ?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteConfirm(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={confirmDelete}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

PropertyTypes.layout = {
    breadcrumbs: [{ title: 'Property Types', href: BASE }],
};
