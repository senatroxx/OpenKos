import type { PropertyRentalMode } from '@/types/models';

export const propertyRentalModeOptions: {
    value: PropertyRentalMode;
    label: string;
    description: string;
}[] = [
    {
        value: 'unit',
        label: 'Individual units',
        description:
            'Use this when the property contains multiple rooms or units that customers rent separately.',
    },
    {
        value: 'whole_property',
        label: 'Whole property',
        description:
            'The entire property is the rentable offering. Publication support is not available yet.',
    },
    {
        value: 'hybrid',
        label: 'Both',
        description: 'The property can support both models.',
    },
];

export function supportsUnitInventory(
    mode: PropertyRentalMode | null | undefined,
): boolean {
    return (mode ?? 'unit') !== 'whole_property';
}

export function supportsWholePropertyRental(
    mode: PropertyRentalMode | null | undefined,
): boolean {
    return mode === 'whole_property' || mode === 'hybrid';
}

export function supportsPropertyPricing(
    mode: PropertyRentalMode | null | undefined,
): boolean {
    return supportsWholePropertyRental(mode);
}
