import type { PublicListing, PublicUnitTypePage } from './listings';

export type PublicPortalMetadata = {
    title: string;
    description: string;
    canonical: string;
    siteName: string;
    image: string | null;
    openGraph: {
        title: string;
        description: string;
        siteName: string;
        url: string;
        type: string;
        image: string | null;
    };
    twitter: {
        card: string;
        title: string;
        description: string;
        image: string | null;
    };
};

export type PublicPortalSettingsPageProps = {
    settings: {
        public_site_name: string;
        public_homepage_title: string;
        public_homepage_description: string;
    };
    resolved: {
        siteName: string;
        homepageTitle: string;
        homepageDescription: string;
    };
    socialImageUrl: string | null;
    hasSocialImage: boolean;
};

export type PublicPortalListingPageProps = {
    listings: PublicListing[];
    metadata: PublicPortalMetadata;
};

export type PublicPortalPropertyPageProps = {
    listing: PublicListing;
    metadata: PublicPortalMetadata;
};

export type PublicPortalUnitTypePageProps = {
    listing: PublicUnitTypePage;
    metadata: PublicPortalMetadata;
};

export type PublicListingHeadProps = {
    metadata: PublicPortalMetadata;
};
