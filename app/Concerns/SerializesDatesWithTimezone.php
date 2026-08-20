<?php

namespace App\Concerns;

use Carbon\Carbon;
use DateTimeInterface;
use Exception;

trait SerializesDatesWithTimezone
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        try {
            return Carbon::instance($date)
                ->setTimezone(config('app.display_timezone', 'UTC'))
                ->toISOString(true);
        } catch (Exception) {
            return Carbon::instance($date)
                ->setTimezone('UTC')
                ->toISOString(true);
        }
    }
}
