import { router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ImagePlus, Star, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import type { GalleryItem } from '@/types';

type GalleryManagerProps = {
    items: GalleryItem[];
    idPrefix: string;
    uploadUrl: string;
    reorderUrl: string;
    updateUrl: (mediaId: number) => string;
    destroyUrl: (mediaId: number) => string;
};

export function MediaGalleryManager({
    items,
    idPrefix,
    uploadUrl,
    reorderUrl,
    updateUrl,
    destroyUrl,
}: GalleryManagerProps) {
    const fileInputId = `${idPrefix}-gallery-file`;
    const uploadAltId = `${idPrefix}-gallery-alt`;
    const uploadCaptionId = `${idPrefix}-gallery-caption`;
    const [metadata, setMetadata] = useState<
        Record<number, { alt: string; caption: string }>
    >({});
    const upload = useForm<{
        file: File | null;
        alt: string;
        caption: string;
    }>({ file: null, alt: '', caption: '' });
    const fileInput = useRef<HTMLInputElement>(null);

    function itemMetadata(item: GalleryItem) {
        return (
            metadata[item.id] ?? {
                alt: item.alt ?? '',
                caption: item.caption ?? '',
            }
        );
    }

    function uploadFile(event: React.FormEvent) {
        event.preventDefault();

        upload.post(uploadUrl, {
            forceFormData: true,
            onSuccess: () => {
                upload.reset();

                if (fileInput.current) {
                    fileInput.current.value = '';
                }
            },
        });
    }

    function saveMetadata(item: GalleryItem) {
        router.patch(updateUrl(item.id), itemMetadata(item));
    }

    function move(item: GalleryItem, direction: -1 | 1) {
        const index = items.findIndex((current) => current.id === item.id);
        const target = index + direction;

        if (index < 0 || target < 0 || target >= items.length) {
            return;
        }

        const ordered = [...items];
        [ordered[index], ordered[target]] = [ordered[target], ordered[index]];
        router.post(reorderUrl, {
            media_ids: ordered.map((current) => current.id),
        });
    }

    function makeCover(item: GalleryItem) {
        const ordered = [
            item,
            ...items.filter((current) => current.id !== item.id),
        ];

        router.post(reorderUrl, {
            media_ids: ordered.map((current) => current.id),
        });
    }

    return (
        <div className="space-y-4">
            <form
                onSubmit={uploadFile}
                className="rounded-lg border bg-muted/20 p-4"
            >
                <div className="flex items-center gap-2">
                    <ImagePlus className="size-4" />
                    <p className="text-sm font-medium">{t('Add photo')}</p>
                </div>
                <div className="mt-4 flex flex-col gap-4">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor={fileInputId}>{t('Photo')}</Label>
                        <Input
                            id={fileInputId}
                            ref={fileInput}
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={(event) =>
                                upload.setData(
                                    'file',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={upload.errors.file} />
                    </div>
                    <div className="flex flex-col gap-4 sm:flex-row">
                        <div className="flex flex-1 flex-col gap-2">
                            <Label htmlFor={uploadAltId}>{t('Alt text')}</Label>
                            <Input
                                id={uploadAltId}
                                value={upload.data.alt}
                                onChange={(event) =>
                                    upload.setData('alt', event.target.value)
                                }
                                placeholder={t('Describe the image')}
                            />
                            <InputError message={upload.errors.alt} />
                        </div>
                        <div className="flex flex-[2] flex-col gap-2">
                            <Label htmlFor={uploadCaptionId}>
                                {t('Caption')}
                            </Label>
                            <Textarea
                                id={uploadCaptionId}
                                value={upload.data.caption}
                                onChange={(event) =>
                                    upload.setData(
                                        'caption',
                                        event.target.value,
                                    )
                                }
                                placeholder={t('Visible description')}
                            />
                            <InputError message={upload.errors.caption} />
                        </div>
                    </div>
                </div>
                <div className="mt-4 flex justify-end">
                    <Button disabled={upload.processing || !upload.data.file}>
                        {t('Upload')}
                    </Button>
                </div>
            </form>

            {items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('No photos yet.')}
                </p>
            ) : (
                <div className="grid gap-4 md:grid-cols-2">
                    {items.map((item, index) => {
                        const values = itemMetadata(item);
                        const altId = `${idPrefix}-media-${item.id}-alt`;
                        const captionId = `${idPrefix}-media-${item.id}-caption`;

                        return (
                            <div
                                key={item.id}
                                className="overflow-hidden rounded-lg border"
                            >
                                <div className="relative aspect-video bg-muted">
                                    <img
                                        src={item.url}
                                        alt={values.alt}
                                        className="size-full object-cover"
                                    />
                                    {item.position === 0 && (
                                        <span className="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full bg-background/90 px-2 py-1 text-xs font-medium">
                                            <Star className="size-3 fill-current" />
                                            {t('Cover')}
                                        </span>
                                    )}
                                </div>
                                <div className="space-y-3 p-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor={altId}>
                                            {t('Alt text')}
                                        </Label>
                                        <Input
                                            id={altId}
                                            value={values.alt}
                                            onChange={(event) =>
                                                setMetadata((current) => ({
                                                    ...current,
                                                    [item.id]: {
                                                        ...values,
                                                        alt: event.target.value,
                                                    },
                                                }))
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor={captionId}>
                                            {t('Caption')}
                                        </Label>
                                        <Textarea
                                            id={captionId}
                                            value={values.caption}
                                            onChange={(event) =>
                                                setMetadata((current) => ({
                                                    ...current,
                                                    [item.id]: {
                                                        ...values,
                                                        caption:
                                                            event.target.value,
                                                    },
                                                }))
                                            }
                                        />
                                    </div>
                                    <div className="flex flex-wrap justify-between gap-2">
                                        <div className="flex gap-1">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                disabled={index === 0}
                                                onClick={() => move(item, -1)}
                                                aria-label={t('Move photo up')}
                                            >
                                                <ArrowUp className="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                disabled={
                                                    index === items.length - 1
                                                }
                                                onClick={() => move(item, 1)}
                                                aria-label={t(
                                                    'Move photo down',
                                                )}
                                            >
                                                <ArrowDown className="size-4" />
                                            </Button>
                                            {index !== 0 && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() =>
                                                        makeCover(item)
                                                    }
                                                >
                                                    <Star className="size-4" />
                                                    {t('Make cover')}
                                                </Button>
                                            )}
                                        </div>
                                        <div className="flex gap-1">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    saveMetadata(item)
                                                }
                                            >
                                                {t('Save metadata')}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="text-destructive"
                                                onClick={() =>
                                                    router.delete(
                                                        destroyUrl(item.id),
                                                    )
                                                }
                                                aria-label={t('Delete photo')}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
