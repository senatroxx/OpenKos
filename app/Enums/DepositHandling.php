<?php

namespace App\Enums;

enum DepositHandling: string
{
    case CarryForward = 'carry_forward';
    case RefundAndCollectNew = 'refund_and_collect_new';
    case Forfeit = 'forfeit';

    public function label(): string
    {
        return match ($this) {
            self::CarryForward => 'Carry Forward',
            self::RefundAndCollectNew => 'Refund & Collect New',
            self::Forfeit => 'Forfeit',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
