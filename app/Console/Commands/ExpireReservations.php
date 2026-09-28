<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reservations:expire')]
#[Description('Expire confirmed reservations after their hold deadline.')]
class ExpireReservations extends Command
{
    public function handle(): int
    {
        $expiredAt = now();

        $count = Reservation::query()
            ->where('status', ReservationStatus::Confirmed->value)
            ->where('expires_at', '<=', $expiredAt)
            ->update([
                'status' => ReservationStatus::Expired->value,
                'expired_at' => $expiredAt,
                'updated_at' => $expiredAt,
            ]);

        $this->info("Expired {$count} reservations.");

        return self::SUCCESS;
    }
}
