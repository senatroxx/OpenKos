import { router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ImagePlus,
    Pencil,
    Star,
    Trash2,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
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
    presentation?: 'inline' | 'gallery';
};

export function MediaGalleryManager({
    items,
    idPrefix,
    uploadUrl,
    reorderUrl,
    updateUrl,
    destroyUrl,
    presentation = 'inline',
}: GalleryManagerProps) {
    const fileInputId = `${idPrefix}-gallery-file`;
    const uploadAltId = `${idPrefix}-gallery-alt`;
    const uploadCaptionId = `${idPrefix}-gallery-caption`;
    const [metadata, setMetadata] = useState<
        Record<number, { alt: string; caption: string }>
    >({});
    const [uploadOpen, setUploadOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<GalleryItem | null>(null);
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
                setUploadOpen(false);

                if (fileInput.current) {
                    fileInput.current.value = '';
                }
            },
        });
    }

    function saveMetadata(item: GalleryItem) {
        router.patch(updateUrl(item.id), itemMetadata(item), {
            onSuccess: () => setEditingItem(null),
        });
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

    function renderUploadForm(className: string) {
        return (
            <form onSubmit={uploadFile} className={className}>
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
                    <Button
                        type="submit"
                        disabled={upload.processing || !upload.data.file}
                    >
                        {t('Upload')}
                    </Button>
                </div>
            </form>
        );
    }

    function renderInlineGallery() {
        return (
            <>
                {renderUploadForm('rounded-lg border bg-muted/20 p-4')}

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
                                                            alt: event.target
                                                                .value,
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
                                                                event.target
                                                                    .value,
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
                                                    onClick={() =>
                                                        move(item, -1)
                                                    }
                                                    aria-label={t(
                                                        'Move photo up',
                                                    )}
                                                >
                                                    <ArrowUp className="size-4" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    disabled={
                                                        index ===
                                                        items.length - 1
                                                    }
                                                    onClick={() =>
                                                        move(item, 1)
                                                    }
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
                                                    aria-label={t(
                                                        'Delete photo',
                                                    )}
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
            </>
        );
    }

    if (presentation === 'inline') {
        return <div className="space-y-4">{renderInlineGallery()}</div>;
    }

    return (
        <div className="space-y-4">
            {items.length > 0 && (
                <div className="flex justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setUploadOpen(true)}
                    >
                        <ImagePlus className="size-4" />
                        {t('Add photos')}
                    </Button>
                </div>
            )}

            {items.length === 0 ? (
                <div className="rounded-lg border border-dashed p-8 text-center">
                    <p className="font-medium">
                        {t('No property photos yet.')}
                    </p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t(
                            'Photos can be used by the property public listing.',
                        )}
                    </p>
                    <Button
                        type="button"
                        className="mt-4"
                        onClick={() => setUploadOpen(true)}
                    >
                        <ImagePlus className="size-4" />
                        {t('Add your first photo')}
                    </Button>
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {items.map((item, index) => {
                        const values = itemMetadata(item);

                        return (
                            <article
                                key={item.id}
                                className="overflow-hidden rounded-lg border bg-card"
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
                                    <p className="truncate text-sm text-muted-foreground">
                                        {values.caption ||
                                            values.alt ||
                                            t('Property photo')}
                                    </p>
                                    <div className="flex flex-wrap items-center justify-between gap-2">
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
                                        </div>
                                        <div className="flex gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setEditingItem(item)
                                                }
                                                aria-label={t(
                                                    'Edit photo details',
                                                )}
                                            >
                                                <Pencil className="size-4" />
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
                                    {index !== 0 && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="w-full"
                                            onClick={() => makeCover(item)}
                                        >
                                            <Star className="size-4" />
                                            {t('Make cover')}
                                        </Button>
                                    )}
                                </div>
                            </article>
                        );
                    })}
                </div>
            )}

            <Sheet
                open={uploadOpen}
                onOpenChange={(open) => {
                    setUploadOpen(open);

                    if (!open) {
                        upload.reset();

                        if (fileInput.current) {
                            fileInput.current.value = '';
                        }
                    }
                }}
            >
                <SheetContent className="sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>{t('Add property photos')}</SheetTitle>
                        <SheetDescription>
                            {t('Add a photo and optional listing metadata.')}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="min-h-0 flex-1 overflow-y-auto px-4 pb-6">
                        {renderUploadForm('space-y-4')}
                    </div>
                </SheetContent>
            </Sheet>

            <Sheet
                open={editingItem !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditingItem(null);
                    }
                }}
            >
                <SheetContent className="sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>{t('Edit photo details')}</SheetTitle>
                        <SheetDescription>
                            {t(
                                'Update the metadata used by the property listing.',
                            )}
                        </SheetDescription>
                    </SheetHeader>
                    {editingItem && (
                        <>
                            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 pb-6">
                                <div className="aspect-video overflow-hidden rounded-lg bg-muted">
                                    <img
                                        src={editingItem.url}
                                        alt={itemMetadata(editingItem).alt}
                                        className="size-full object-cover"
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor={`${idPrefix}-edit-alt`}>
                                        {t('Alt text')}
                                    </Label>
                                    <Input
                                        id={`${idPrefix}-edit-alt`}
                                        value={itemMetadata(editingItem).alt}
                                        onChange={(event) =>
                                            setMetadata((current) => ({
                                                ...current,
                                                [editingItem.id]: {
                                                    ...itemMetadata(
                                                        editingItem,
                                                    ),
                                                    alt: event.target.value,
                                                },
                                            }))
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor={`${idPrefix}-edit-caption`}>
                                        {t('Caption')}
                                    </Label>
                                    <Textarea
                                        id={`${idPrefix}-edit-caption`}
                                        value={
                                            itemMetadata(editingItem).caption
                                        }
                                        onChange={(event) =>
                                            setMetadata((current) => ({
                                                ...current,
                                                [editingItem.id]: {
                                                    ...itemMetadata(
                                                        editingItem,
                                                    ),
                                                    caption: event.target.value,
                                                },
                                            }))
                                        }
                                    />
                                </div>
                            </div>
                            <SheetFooter className="border-t bg-background/95 sm:flex-row sm:justify-end">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setEditingItem(null)}
                                >
                                    {t('Cancel')}
                                </Button>
                                <Button
                                    type="button"
                                    onClick={() => saveMetadata(editingItem)}
                                >
                                    {t('Save metadata')}
                                </Button>
                            </SheetFooter>
                        </>
                    )}
                </SheetContent>
            </Sheet>
        </div>
    );
}
