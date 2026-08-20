<?php

namespace App\Models;

use App\Concerns\SerializesDatesWithTimezone;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use SerializesDatesWithTimezone;

    protected $fillable = [
        'name',
        'guard_name',
    ];
}
