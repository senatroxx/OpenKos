<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Lease;
use Illuminate\Http\JsonResponse;

class LeaseRentScheduleController extends Controller
{
    public function __invoke(Lease $lease): JsonResponse
    {
        if (! request()->user()->hasRole(Role::Owner->value)) {
            $this->authorize('view', $lease);
        }

        return response()->json([
            'schedule' => $lease->schedule(),
        ]);
    }
}
