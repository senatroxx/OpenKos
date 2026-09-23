import { Head } from '@inertiajs/react';
import type { PublicListingHeadProps } from '@/types';

export default function PublicListingHead({
    metadata,
}: PublicListingHeadProps) {
    return (
        <Head title={metadata.title}>
            <meta
                head-key="description"
                name="description"
                content={metadata.description}
            />
            <link head-key="canonical" rel="canonical" href={metadata.canonical} />
            <meta head-key="og:title" property="og:title" content={metadata.openGraph.title} />
            <meta
                head-key="og:description"
                property="og:description"
                content={metadata.openGraph.description}
            />
            <meta
                head-key="og:site_name"
                property="og:site_name"
                content={metadata.openGraph.siteName}
            />
            <meta
                head-key="twitter:title"
                name="twitter:title"
                content={metadata.twitter.title}
            />
            <meta
                head-key="twitter:description"
                name="twitter:description"
                content={metadata.twitter.description}
            />
            <meta head-key="og:url" property="og:url" content={metadata.openGraph.url} />
            <meta head-key="og:type" property="og:type" content={metadata.openGraph.type} />
            <meta
                head-key="twitter:card"
                name="twitter:card"
                content={metadata.twitter.card}
            />
            <meta head-key="og:image" property="og:image" content={metadata.openGraph.image ?? ''} />
            <meta head-key="twitter:image" name="twitter:image" content={metadata.twitter.image ?? ''} />
        </Head>
    );
}
