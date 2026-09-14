<?php

namespace App\Enums;

enum DepositSettlementStatus: string
{
    case Draft = 'draft';
    case Settled = 'settled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Settled => 'Settled',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
