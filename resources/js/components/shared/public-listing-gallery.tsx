import { ImageOff } from 'lucide-react';
import type { PublicGalleryItem } from '@/types';

export default function PublicListingGallery({
    items,
}: {
    items: PublicGalleryItem[];
}) {
    if (items.length === 0) {
        return (
            <div className="flex min-h-48 flex-col items-center justify-center gap-3 rounded-2xl border border-dashed bg-muted/30 p-6 text-center text-muted-foreground">
                <ImageOff className="size-8" aria-hidden="true" />
                <p className="text-sm">No photos available</p>
            </div>
        );
    }

    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {items.map((item, index) => (
                <figure
                    key={`${item.url}-${item.position}`}
                    className={index === 0 ? 'sm:col-span-2 sm:row-span-2' : ''}
                >
                    <img
                        src={item.url}
                        alt={item.alt || 'Property photo'}
                        loading={index === 0 ? 'eager' : 'lazy'}
                        className="aspect-[4/3] w-full rounded-xl object-cover"
                    />
                    {item.caption && (
                        <figcaption className="mt-2 text-sm text-muted-foreground">
                            {item.caption}
                        </figcaption>
                    )}
                </figure>
            ))}
        </div>
    );
}
