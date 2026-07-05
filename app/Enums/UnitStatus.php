<?php

namespace App\Enums;

enum UnitStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Maintenance = 'maintenance';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Occupied => 'Occupied',
            self::Maintenance => 'Maintenance',
            self::Unavailable => 'Unavailable',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

// ponytail: alias file removed; all callers migrated to UnitStatus
class_alias(UnitStatus::class, 'App\Enums\RoomStatus');
