<?php

namespace App\Enums;

enum DataTransferDataset: string
{
    case Properties = 'properties';
    case Units = 'units';
    case Tenants = 'tenants';
    case UnitRates = 'unit-rates';
    case PropertyTypes = 'property-types';
    case Expenses = 'expenses';

    public function label(): string
    {
        return match ($this) {
            self::Properties => 'Properties',
            self::Units => 'Units',
            self::Tenants => 'Tenants',
            self::UnitRates => 'Unit rates',
            self::PropertyTypes => 'Property types',
            self::Expenses => 'Expenses',
        };
    }

    public function isImportable(): bool
    {
        return $this !== self::PropertyTypes;
    }
}
