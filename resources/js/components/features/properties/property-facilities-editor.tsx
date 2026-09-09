import { useForm } from '@inertiajs/react';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import properties from '@/routes/properties';
import type { Amenity, Property } from '@/types';

export default function PropertyFacilitiesEditor({
    property,
    amenities,
}: {
    property: Property;
    amenities: Amenity[];
}) {
    const facilityForm = useForm<{ amenity_ids: number[] }>({
        amenity_ids: property.facilities?.map((amenity) => amenity.id) ?? [],
    });
    const customForm = useForm<{ name: string }>({ name: '' });

    function toggleAmenity(id: number, checked: boolean | 'indeterminate') {
        if (checked === 'indeterminate') {
            return;
        }

        facilityForm.setData(
            'amenity_ids',
            checked
                ? [...facilityForm.data.amenity_ids, id]
                : facilityForm.data.amenity_ids.filter(
                      (current) => current !== id,
                  ),
        );
    }

    function saveFacilities(event: React.FormEvent) {
        event.preventDefault();
        facilityForm.put(properties.facilities.update.url(property));
    }

    function createCustomAmenity(event: React.FormEvent) {
        event.preventDefault();
        customForm.post(properties.amenities.store.url(property), {
            onSuccess: () => customForm.reset(),
        });
    }

    return (
        <section className="space-y-4">
            <div>
                <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                    {t('Property facilities')}
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                    {t('Choose the facilities available across this property.')}
                </p>
            </div>

            <form onSubmit={saveFacilities} className="space-y-4">
                <div className="grid gap-3 sm:grid-cols-2">
                    {amenities.map((amenity) => (
                        <label
                            key={amenity.id}
                            className="flex items-center gap-3 rounded-md border p-3 text-sm"
                        >
                            <Checkbox
                                checked={facilityForm.data.amenity_ids.includes(
                                    amenity.id,
                                )}
                                onCheckedChange={(checked) =>
                                    toggleAmenity(amenity.id, checked)
                                }
                            />
                            <span className="flex-1">{amenity.name}</span>
                            {!amenity.is_active && (
                                <span className="text-xs text-muted-foreground">
                                    {t('Inactive')}
                                </span>
                            )}
                        </label>
                    ))}
                </div>
                <InputError message={facilityForm.errors.amenity_ids} />
                <Button type="submit" disabled={facilityForm.processing}>
                    {t('Save facilities')}
                </Button>
            </form>

            <form
                onSubmit={createCustomAmenity}
                className="flex flex-col gap-3 rounded-lg border bg-muted/20 p-4 sm:flex-row sm:items-end"
            >
                <div className="grid flex-1 gap-2">
                    <Label htmlFor="custom-amenity">
                        {t('Add custom amenity')}
                    </Label>
                    <Input
                        id="custom-amenity"
                        value={customForm.data.name}
                        onChange={(event) =>
                            customForm.setData('name', event.target.value)
                        }
                        placeholder={t('e.g. Rooftop garden')}
                    />
                    <InputError message={customForm.errors.name} />
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    disabled={
                        customForm.processing || !customForm.data.name.trim()
                    }
                >
                    {t('Add amenity')}
                </Button>
            </form>
        </section>
    );
}
