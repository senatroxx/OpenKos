<?php

namespace App\Repositories;

use App\Models\Lease;
use App\Models\Unit;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class OccupancyRepository
{
    public function activeOccupantCount(Unit $unit): int
    {
        return DB::table('lease_tenant')
            ->join('leases', 'leases.id', '=', 'lease_tenant.lease_id')
            ->where('leases.unit_id', $unit->id)
            ->whereIn('leases.id', Lease::query()->active()->select('id'))
            ->count();
    }

    public function activeOccupantCountAtDate(Unit $unit, string $date): int
    {
        return DB::table('lease_tenant')
            ->join('leases', 'leases.id', '=', 'lease_tenant.lease_id')
            ->where('leases.unit_id', $unit->id)
            ->whereIn('leases.id', Lease::query()->active()->select('id'))
            ->where(function (QueryBuilder $query) use ($date): void {
                $query->whereNull('leases.end_date')->orWhereDate('leases.end_date', '>=', $date);
            })
            ->count();
    }
}
