<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use Illuminate\Http\JsonResponse;

class LeaseRentScheduleController extends Controller
{
    public function __invoke(Lease $lease): JsonResponse
    {
        $this->authorize('view', $lease);

        return response()->json([
            'schedule' => $lease->schedule(),
        ]);
    }
}
