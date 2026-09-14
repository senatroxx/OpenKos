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
    location: {
        address: string | null;
        postal_code: string | null;
        city: string | null;
        region: string | null;
    };
    description: string | null;
    amenities: PublicAmenity[];
    gallery: PublicGalleryItem[];
    inventory: PublicInventory;
    unit_types: PublicUnitType[];
};

export type PublicUnitTypePage = {
    property: {
        slug: string;
        name: string;
    };
    unit_type: PublicUnitType;
};
