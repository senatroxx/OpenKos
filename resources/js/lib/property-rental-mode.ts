import type { PropertyRentalMode } from '@/types/models';

export const propertyRentalModeOptions: {
    value: PropertyRentalMode;
    label: string;
    description: string;
}[] = [
    {
        value: 'unit',
        label: 'Individual units',
        description: 'Customers rent a unit or unit type.',
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
