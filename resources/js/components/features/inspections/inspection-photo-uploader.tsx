import { router, useForm } from '@inertiajs/react';
import { ImagePlus, Trash2, Upload } from 'lucide-react';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';
import inspectionPhotos from '@/routes/inspections/items/photos';
import type { InspectionPhoto } from '@/types';

export function InspectionPhotoUploader({
    inspectionId,
    itemId,
    photos,
    disabled,
}: {
    inspectionId: number;
    itemId: number;
    photos: InspectionPhoto[];
    disabled: boolean;
}) {
    const form = useForm<{ file: File | null }>({ file: null });

    function upload() {
        if (!form.data.file) {
            return;
        }

        form.post(
            inspectionPhotos.store.url({
                inspection: inspectionId,
                item: itemId,
            }),
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => form.reset('file'),
            },
        );
    }

    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2 text-sm font-medium">
                <ImagePlus className="size-4 text-muted-foreground" />
                {t('Photos')}
            </div>

            {photos.length > 0 && (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    {photos.map((photo) => (
                        <div
                            key={photo.id}
                            className="group relative overflow-hidden rounded-lg border bg-muted/20"
                        >
                            <a
                                href={photo.url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <img
                                    src={photo.url}
                                    alt={photo.original_name}
                                    className="aspect-square w-full object-cover transition-opacity group-hover:opacity-80"
                                />
                            </a>
                            {!disabled && (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="icon-xs"
                                    className="absolute top-2 right-2 opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
                                    onClick={() =>
                                        router.delete(
                                            inspectionPhotos.destroy.url({
                                                inspection: inspectionId,
                                                item: itemId,
                                                media: photo.id,
                                            }),
                                            { preserveScroll: true },
                                        )
                                    }
                                    aria-label={t('Delete photo')}
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {!disabled && (
                <div className="flex flex-col gap-2 sm:flex-row sm:items-start">
                    <Input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        onChange={(event) =>
                            form.setData(
                                'file',
                                event.target.files?.[0] ?? null,
                            )
                        }
                        disabled={form.processing}
                    />
                    <Button
                        type="button"
                        variant="outline"
                        onClick={upload}
                        disabled={!form.data.file || form.processing}
                    >
                        <Upload className="size-4" />
                        {t('Upload')}
                    </Button>
                </div>
            )}
            <InputError message={form.errors.file} />
        </div>
    );
}
