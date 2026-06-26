<?php

namespace App\Enums;

enum TenantDocumentType: string
{
    case Ktp = 'ktp';
    case Passport = 'passport';
    case Lease = 'lease';
    case Supporting = 'supporting';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Ktp => 'KTP',
            self::Passport => 'Passport',
            self::Lease => 'Lease/Agreement',
            self::Supporting => 'Supporting',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
