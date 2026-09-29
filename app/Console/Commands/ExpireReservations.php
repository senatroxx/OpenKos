<?php

namespace App\Console\Commands;

use App\Actions\Reservations\ExpireReservations as ExpireReservationsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reservations:expire')]
#[Description('Expire confirmed reservations after their hold deadline.')]
class ExpireReservations extends Command
{
    public function handle(ExpireReservationsAction $action): int
    {
        $count = $action->execute();

        $this->info("Expired {$count} reservations.");

        return self::SUCCESS;
    }
}
