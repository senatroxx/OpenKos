import type { ReactNode } from 'react';

export type PublicAmenity = {
    name: string;
    icon: string | null;
};

export type PublicGalleryItem = {
    url: string;
    position: number;
    alt: string | null;
    caption: string | null;
    mime_type: string;
};

export type PublicInventory = {
    total_units: number;
    available_units: number;
};

export type PublicStartingPrice = {
    amount: string;
    currency: string;
    billing_interval: number;
    billing_unit: string;
    billing_label: string;
};

export type PublicWholePropertyOffering = {
    type: 'whole_property';
    availability: 'available_for_inquiry' | 'unavailable';
    starting_price: PublicStartingPrice;
    rates: PublicStartingPrice[];
};

export type PublicUnitType = {
    slug: string;
    name: string;
    description: string | null;
    bedrooms: number | null;
    bathrooms: string | null;
    size_sqm: string | null;
    furnishing: string | null;
    amenities: PublicAmenity[];
    gallery: PublicGalleryItem[];
    inventory: PublicInventory;
    starting_prices: PublicStartingPrice[];
};

export type PublicListing = {
    slug: string;
    name: string;
    type: string;
    type_label: string;
    rental_mode: 'unit' | 'whole_property' | 'hybrid';
    location: {
        address: string | null;
        postal_code: string | null;
        city: string | null;
        region: string | null;
    };
    description: string | null;
    amenities: PublicAmenity[];
    gallery: PublicGalleryItem[];
    inventory?: PublicInventory;
    unit_types?: PublicUnitType[];
    whole_property_offering?: PublicWholePropertyOffering | null;
};

export type PublicUnitTypePage = {
    property: {
        slug: string;
        name: string;
        rental_mode: 'unit' | 'whole_property' | 'hybrid';
    };
    unit_type: PublicUnitType;
};

export type PublicPropertyPageProps = {
    listing: PublicListing;
    canonicalUrl: string;
    open_application: PublicOpenApplication | null;
};

export type PublicWholePropertyOfferingProps = {
    offering: PublicWholePropertyOffering;
    existingApplication: PublicOpenApplication | null;
    applyHref: string;
    applyLabel: string;
    onApply: () => void;
    applicationForm?: ReactNode;
};

export type PublicUnitTypePageProps = {
    listing: PublicUnitTypePage;
    canonicalUrl: string;
    open_application: PublicOpenApplication | null;
};

export type PublicOpenApplication = {
    id: number;
    status: 'new' | 'reviewing';
};

export type PublicApplicationFormTarget = {
    target_type: 'whole_property' | 'unit_type';
    property_slug: string;
    property_name: string;
    unit_type_slug: string | null;
    unit_type_name: string | null;
    rental_options: PublicStartingPrice[];
};

export type PublicApplicationFormProps = {
    target: PublicApplicationFormTarget;
    existingApplication?: PublicOpenApplication | null;
};

export type PublicUnitTypeAttributesProps = {
    unitType: PublicUnitType;
};

export type PublicPricingOptionsProps = {
    prices: PublicStartingPrice[];
};

export type PublicRentalSummaryProps = {
    unitType: PublicUnitType;
    existingApplication: PublicOpenApplication | null;
    applyHref: string;
    applyLabel: string;
    onApply: () => void;
    applicationForm?: ReactNode;
};
