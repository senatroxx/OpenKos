<?php

namespace App\Concerns;

use App\Support\DateTimeFormatter;
use DateTimeInterface;

trait SerializesDatesWithTimezone
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return DateTimeFormatter::iso($date);
    }
}
