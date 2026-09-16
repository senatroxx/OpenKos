import { Head } from '@inertiajs/react';

type Props = {
    title: string;
    description: string | null;
    canonicalUrl: string;
    imageUrl?: string | null;
};

export default function PublicListingHead({
    title,
    description,
    canonicalUrl,
    imageUrl,
}: Props) {
    const resolvedDescription =
        description?.trim() || 'Discover this property and its rental options.';

    return (
        <Head title={title}>
            <meta
                head-key="description"
                name="description"
                content={resolvedDescription}
            />
            <link head-key="canonical" rel="canonical" href={canonicalUrl} />
            <meta head-key="og:title" property="og:title" content={title} />
            <meta
                head-key="og:description"
                property="og:description"
                content={resolvedDescription}
            />
            <meta
                head-key="twitter:title"
                name="twitter:title"
                content={title}
            />
            <meta
                head-key="twitter:description"
                name="twitter:description"
                content={resolvedDescription}
            />
            <meta head-key="og:url" property="og:url" content={canonicalUrl} />
            <meta head-key="og:type" property="og:type" content="website" />
            <meta
                head-key="twitter:card"
                name="twitter:card"
                content={imageUrl ? 'summary_large_image' : 'summary'}
            />
            {imageUrl && (
                <>
                    <meta
                        head-key="og:image"
                        property="og:image"
                        content={imageUrl}
                    />
                    <meta
                        head-key="twitter:image"
                        name="twitter:image"
                        content={imageUrl}
                    />
                </>
            )}
        </Head>
    );
}
